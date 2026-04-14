# Backend Guide

## Stack
- **Laravel 11** (PHP 8.3+)
- **MySQL 8.0** (primary database)
- **Redis** (cache + queues)
- **Laravel Horizon** (queue monitoring)
- **Spatie packages** (permissions, media, activity log)

---

## Coding Standards

- **PSR-12** code style (enforced via Laravel Pint)
- **Strict types**: `declare(strict_types=1)` in all PHP files
- **PHP 8.3 features**: Enums, readonly properties, named arguments, match expressions
- **Service layer**: Never put business logic in controllers — controllers are thin
- **FormRequests**: All validation in dedicated `FormRequest` classes

---

## Service Layer Pattern

All business logic lives in `app/Services/`. Controllers call services, services call models/external APIs.

```php
// app/Services/ImageGenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\GenerateImageJob;
use App\Models\Generation;
use App\Models\User;

class ImageGenerationService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function dispatch(User $user, array $params): Generation
    {
        $creditCost = $this->calculateCreditCost($params);

        if (!$this->creditService->canAfford($user, $creditCost)) {
            throw new InsufficientCreditsException();
        }

        // Reserve credits optimistically
        $this->creditService->reserve($user, $creditCost);

        $generation = Generation::create([
            'user_id'      => $user->id,
            'model'        => $params['model'],
            'prompt'       => $params['prompt'],
            'params'       => $params,
            'credits_cost' => $creditCost,
            'status'       => GenerationStatus::Queued,
        ]);

        GenerateImageJob::dispatch($generation)
            ->onQueue('ai_generation');

        return $generation;
    }

    private function calculateCreditCost(array $params): int
    {
        $width = $params['width'] ?? 1024;
        return match (true) {
            $width >= 2048 => 4,
            $width >= 1024 => 2,
            default        => 1,
        };
    }
}
```

---

## AI Provider Abstraction

```php
// app/Services/AI/Contracts/ImageGeneratorInterface.php

namespace App\Services\AI\Contracts;

interface ImageGeneratorInterface
{
    /**
     * Submit a generation and return provider job ID.
     */
    public function submit(array $params): string;

    /**
     * Poll status. Returns ['status' => 'completed|processing|failed', 'image_url' => '...']
     */
    public function getStatus(string $jobId): array;
}
```

```php
// app/Services/AI/OpenAIImageGenerator.php

namespace App\Services\AI;

use App\Services\AI\Contracts\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;

class OpenAIImageGenerator implements ImageGeneratorInterface
{
    public function submit(array $params): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/images/generations', [
                'model'           => 'dall-e-3',
                'prompt'          => $params['prompt'],
                'n'               => 1,
                'size'            => "{$params['width']}x{$params['height']}",
                'response_format' => 'url',
            ])
            ->throw()
            ->json();

        // OpenAI is synchronous — store URL directly
        return $response['data'][0]['url'];
    }

    public function getStatus(string $jobId): array
    {
        // OpenAI DALL-E 3 returns synchronously, so status is always 'completed'
        return ['status' => 'completed', 'image_url' => $jobId];
    }
}
```

---

## Queue Job

```php
// app/Jobs/GenerateImageJob.php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\AI\Contracts\ImageGeneratorInterface;
use App\Services\CreditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly Generation $generation
    ) {}

    public function handle(CreditService $creditService): void
    {
        $this->generation->update(['status' => GenerationStatus::Processing]);

        try {
            /** @var ImageGeneratorInterface $provider */
            $provider = app("ai.{$this->generation->provider}");

            $jobId = $provider->submit($this->generation->params);
            $result = $provider->getStatus($jobId);

            // Download and store image
            $imageContent = Http::get($result['image_url'])->body();
            $filename = "generation-{$this->generation->uuid}.jpg";

            $this->generation
                ->addMediaFromString($imageContent)
                ->usingFileName($filename)
                ->toMediaCollection('generations');

            $this->generation->update(['status' => GenerationStatus::Completed]);
            $creditService->confirm($this->generation->user, $this->generation->credits_cost);

        } catch (\Throwable $e) {
            $this->generation->update([
                'status'        => GenerationStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);
            $creditService->refund($this->generation->user, $this->generation->credits_cost);

            report($e);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Job exhausted all retries
        $this->generation->update(['status' => GenerationStatus::Failed]);
        (new CreditService())->refund(
            $this->generation->user,
            $this->generation->credits_cost
        );
    }
}
```

---

## Credit Service

```php
// app/Services/CreditService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditLedger;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function balance(User $user): int
    {
        return Cache::remember("credits:balance:{$user->id}", 300, function () use ($user) {
            return (int) CreditLedger::where('user_id', $user->id)->sum('amount');
        });
    }

    public function canAfford(User $user, int $cost): bool
    {
        return $this->balance($user) >= $cost;
    }

    public function reserve(User $user, int $amount): void
    {
        $this->createEntry($user, CreditTransactionType::Reserve, -$amount, 'Credit reserve');
    }

    public function confirm(User $user, int $amount): void
    {
        // Reserve already deducted — just clear cache
        $this->clearCache($user);
    }

    public function refund(User $user, int $amount): void
    {
        $this->createEntry($user, CreditTransactionType::Refund, $amount, 'Generation refund');
    }

    public function grant(User $user, int $amount, string $description = 'Monthly grant'): void
    {
        $this->createEntry($user, CreditTransactionType::Grant, $amount, $description);
    }

    private function createEntry(User $user, CreditTransactionType $type, int $amount, string $desc): void
    {
        DB::transaction(function () use ($user, $type, $amount, $desc) {
            $balance = $this->balance($user) + $amount;

            CreditLedger::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => max(0, $balance),
                'description'   => $desc,
            ]);

            $this->clearCache($user);
        });
    }

    private function clearCache(User $user): void
    {
        Cache::forget("credits:balance:{$user->id}");
    }
}
```

---

## Enums (PHP 8.1+)

```php
// app/Enums/GenerationStatus.php
namespace App\Enums;

enum GenerationStatus: string
{
    case Queued     = 'queued';
    case Processing = 'processing';
    case Completed  = 'completed';
    case Failed     = 'failed';
}

// app/Enums/CreditTransactionType.php
namespace App\Enums;

enum CreditTransactionType: string
{
    case Grant        = 'grant';
    case Reserve      = 'reserve';
    case Confirm      = 'confirm';
    case Refund       = 'refund';
    case Topup        = 'topup';
    case ManualAdjust = 'manual_adjust';
}
```

---

## Controller Convention

Controllers are thin — validate, call service, return response.

```php
// app/Http/Controllers/GenerationController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GenerateImageRequest;
use App\Services\ImageGenerationService;
use Illuminate\Http\JsonResponse;

class GenerationController extends Controller
{
    public function __construct(
        private readonly ImageGenerationService $service
    ) {}

    public function store(GenerateImageRequest $request): JsonResponse
    {
        $generation = $this->service->dispatch(
            user: $request->user(),
            params: $request->validated()
        );

        return response()->json([
            'data' => [
                'id'               => $generation->uuid,
                'status'           => $generation->status,
                'credits_cost'     => $generation->credits_cost,
                'estimated_seconds' => 15,
                'poll_url'         => route('api.generations.status', $generation->uuid),
            ]
        ], 202);
    }
}
```

---

## Scheduled Jobs

```php
// routes/console.php

use App\Jobs\ResetMonthlyCreditsJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(ResetMonthlyCreditsJob::class)
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping();

Schedule::command('telescope:prune --hours=48')->daily();
Schedule::command('activitylog:clean --days=60')->weekly();
```

---

## Horizon Configuration

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-ai' => [
            'connection' => 'redis',
            'queue'      => ['ai_generation'],
            'balance'    => 'auto',
            'processes'  => 3,
            'tries'      => 3,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['notifications', 'default'],
            'balance'    => 'simple',
            'processes'  => 2,
            'tries'      => 3,
        ],
    ],
]
```
