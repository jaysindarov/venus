# AI Integration Guide

## Architecture Philosophy

The integration layer is built on **two tiers**:

1. **Laravel AI SDK** (`laravel/ai`) — the primary, first-party integration for all SDK-supported providers. Use this for everything it supports. It handles failover, queue integration, testing fakes, and provider switching as a config change.

2. **Custom `ImageGeneratorInterface`** — a thin wrapper that sits above both the SDK and any future providers (Replicate, fal.ai, Stability AI, etc.) that the SDK does not yet support. This means your controllers and jobs **never** know or care which tier they're talking to. Adding a new provider in 6 months = implement one interface, register one binding. Zero controller changes.

```
Controller / Job
      │
      ▼
ImageGenerationService          ← single entry point, always
      │
      ├── SdkImageGenerator     ← wraps Laravel AI SDK (OpenAI, Gemini, xAI)
      │         └── laravel/ai  ← handles provider details, failover, retries
      │
      └── ReplicateImageGenerator   ← future: direct Http::facade
      └── FalAIImageGenerator       ← future: direct Http::facade
      └── StabilityImageGenerator   ← future: direct Http::facade
```

This means the `ImageGeneratorInterface` is not redundant even when using the SDK — it's the extensibility seam that protects you when the SDK's supported model list doesn't cover what your users want.

---

## Installation & Setup

```bash
composer require laravel/ai
php artisan vendor:publish --tag=ai-config
```

### SDK Configuration (`config/ai.php`)

The Laravel AI SDK currently supports OpenAI, Anthropic, Gemini, Groq, and xAI for text generation. For image generation specifically, it supports OpenAI, Gemini, and xAI.

```php
// config/ai.php (published by vendor:publish)
return [
    'default' => env('AI_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
        ],
        'xai' => [
            'api_key' => env('XAI_API_KEY'),
        ],
    ],
];
```

---

## Model Registry (`config/ai_models.php`)

This is the **single source of truth** for all models — SDK-backed and future custom providers alike. Adding a new model in the future means adding one entry here and implementing the interface if it's a new provider tier.

```php
// config/ai_models.php

return [

    // ─────────────────────────────────────────────────────────────────
    // TIER 1: Laravel AI SDK backed models (OpenAI, Gemini, xAI)
    // To add a new SDK model: add an entry below with provider_tier=sdk.
    // No code changes needed — just config + API key.
    // ─────────────────────────────────────────────────────────────────

    'dall-e-3' => [
        'name'                     => 'DALL-E 3',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'openai',
        'sdk_model'                => 'dall-e-3',
        'description'              => 'OpenAI flagship — highest prompt accuracy',
        'supports_negative_prompt' => false,
        'supports_image_to_image'  => false,
        'max_resolution'           => 1792,
        'aspect_ratios'            => ['1:1', '16:9', '9:16'],
        'available_plans'          => ['basic', 'pro', 'creator'],
        'credits_map'              => [512 => 1, 1024 => 2, 1792 => 4],
    ],

    'gemini-imagen' => [
        'name'                     => 'Gemini Imagen',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'gemini',
        'sdk_model'                => 'imagen-3.0-generate-002',
        'description'              => 'Google Imagen — photorealistic, great for scenes',
        'supports_negative_prompt' => true,
        'supports_image_to_image'  => true,
        'max_resolution'           => 1024,
        'aspect_ratios'            => ['1:1', '3:4', '4:3', '16:9', '9:16'],
        'available_plans'          => ['basic', 'pro', 'creator'],
        'credits_map'              => [512 => 1, 1024 => 2],
    ],

    'grok-imagine' => [
        'name'                     => 'Grok Imagine',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'xai',
        'sdk_model'                => 'grok-2-image',
        'description'              => 'xAI Grok — fast, creative, great aesthetics',
        'supports_negative_prompt' => false,
        'supports_image_to_image'  => false,
        'max_resolution'           => 1024,
        'aspect_ratios'            => ['1:1', '16:9'],
        'available_plans'          => ['pro', 'creator'],
        'credits_map'              => [1024 => 2],
    ],

    // ─────────────────────────────────────────────────────────────────
    // TIER 2: Custom provider models (future scale)
    //
    // Use when the Laravel AI SDK does not support the model/provider.
    // Each requires:
    //   1. A class implementing ImageGeneratorInterface
    //   2. A binding in AIServiceProvider (e.g. 'ai.replicate')
    //   3. An entry here with provider_tier = 'custom'
    //
    // Uncomment and fill in when ready to add.
    // ─────────────────────────────────────────────────────────────────

    // 'flux-dev' => [
    //     'name'                     => 'Flux Dev',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'replicate',
    //     'replicate_model'          => 'black-forest-labs/flux-dev',
    //     'description'              => 'Open-source, excellent realism and detail',
    //     'supports_negative_prompt' => true,
    //     'supports_image_to_image'  => true,
    //     'max_resolution'           => 1024,
    //     'aspect_ratios'            => ['1:1', '3:4', '4:3', '16:9'],
    //     'available_plans'          => ['pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 2],
    // ],

    // 'sdxl' => [
    //     'name'                     => 'Stable Diffusion XL',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'replicate',
    //     'replicate_model'          => 'stability-ai/sdxl:...',
    //     'description'              => 'Open-source powerhouse, great for art styles',
    //     'supports_negative_prompt' => true,
    //     'supports_image_to_image'  => false,
    //     'max_resolution'           => 1024,
    //     'available_plans'          => ['free', 'basic', 'pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 2],
    // ],

    // 'flux-schnell' => [
    //     'name'                     => 'Flux Schnell',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'fal',
    //     'fal_model'                => 'fal-ai/flux/schnell',
    //     'description'              => 'Ultra-fast generation in ~2 seconds',
    //     'supports_negative_prompt' => false,
    //     'supports_image_to_image'  => false,
    //     'max_resolution'           => 1024,
    //     'available_plans'          => ['basic', 'pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 1],
    // ],
];
```

---

## The Interface Contract

All generators — SDK and custom — implement this interface. The rest of the app only ever calls this contract.

```php
// app/Services/AI/Contracts/ImageGeneratorInterface.php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

interface ImageGeneratorInterface
{
    /**
     * Submit a generation request.
     * Returns a provider job ID (async) or image URL (sync/SDK).
     */
    public function submit(array $params): string;

    /**
     * Poll/retrieve result for a given job ID.
     * For synchronous providers, this simply returns completed status.
     * Returns ['status' => 'completed|processing|failed', 'image_url' => '...']
     */
    public function getStatus(string $jobId): array;

    /**
     * Whether this provider resolves synchronously (no polling needed).
     * Laravel AI SDK providers are synchronous.
     * Replicate, fal.ai are async.
     */
    public function isSynchronous(): bool;
}
```

---

## SdkImageGenerator — wraps Laravel AI SDK

This single class handles **all SDK-backed providers** (OpenAI, Gemini, xAI). Switching between them is a model config lookup — no code changes needed.

```php
// app/Services/AI/SdkImageGenerator.php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\ImageGeneratorInterface;
use Laravel\Ai\Facades\AI;

class SdkImageGenerator implements ImageGeneratorInterface
{
    public function submit(array $params): string
    {
        $modelConfig = config("ai_models.{$params['model']}");

        $image = AI::image()
            ->using("{$modelConfig['sdk_provider']}/{$modelConfig['sdk_model']}")
            ->prompt($this->buildPrompt($params))
            ->when(
                isset($params['width'], $params['height']),
                fn ($b) => $b->dimensions($params['width'], $params['height'])
            )
            ->when(
                !empty($params['aspect_ratio']),
                fn ($b) => $b->aspectRatio($params['aspect_ratio'])
            )
            ->when(
                !empty($params['reference_image_url']),   // image-to-image (Gemini)
                fn ($b) => $b->attach($params['reference_image_url'])
            )
            ->generate();

        // SDK is synchronous — returns image URL directly
        return $image->url();
    }

    public function getStatus(string $jobId): array
    {
        // jobId IS the image URL for SDK providers
        return ['status' => 'completed', 'image_url' => $jobId];
    }

    public function isSynchronous(): bool
    {
        return true;
    }

    private function buildPrompt(array $params): string
    {
        $prompt = $params['prompt'];

        if (!empty($params['style_suffix'])) {
            $prompt .= ', ' . $params['style_suffix'];
        }

        return $prompt;
    }
}
```

---

## Provider Registration

```php
// app/Providers/AIServiceProvider.php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\SdkImageGenerator;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Tier 1: Laravel AI SDK providers ──────────────────────────
        // All share one SdkImageGenerator class.
        // Provider routing is handled internally by the SDK via model config.
        $this->app->bind('ai.openai', SdkImageGenerator::class);
        $this->app->bind('ai.gemini', SdkImageGenerator::class);
        $this->app->bind('ai.xai',    SdkImageGenerator::class);

        // ── Tier 2: Future custom providers ───────────────────────────
        // When you need a provider the SDK does not yet support:
        //   1. Create app/Services/AI/{Provider}ImageGenerator.php
        //   2. Implement ImageGeneratorInterface
        //   3. Uncomment the binding here
        //   4. Add model entries to config/ai_models.php
        //   5. Add API key to .env + config/services.php
        //
        // $this->app->bind('ai.replicate', ReplicateImageGenerator::class);
        // $this->app->bind('ai.fal',       FalAIImageGenerator::class);
        // $this->app->bind('ai.stability', StabilityImageGenerator::class);
    }
}
```

---

## ImageGenerationService — single entry point

```php
// app/Services/ImageGenerationService.php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\UnsupportedModelException;
use App\Jobs\GenerateImageJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\AI\Contracts\ImageGeneratorInterface;

class ImageGenerationService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function dispatch(User $user, array $params): Generation
    {
        $modelConfig = $this->resolveModelConfig($params['model']);
        $creditCost  = $this->calculateCreditCost($params, $modelConfig);

        if (!$this->creditService->canAfford($user, $creditCost)) {
            throw new InsufficientCreditsException();
        }

        $this->creditService->reserve($user, $creditCost);

        $generation = Generation::create([
            'user_id'       => $user->id,
            'model'         => $params['model'],
            'provider'      => $modelConfig['sdk_provider'] ?? $modelConfig['provider'],
            'provider_tier' => $modelConfig['provider_tier'],
            'prompt'        => $params['prompt'],
            'params'        => $params,
            'credits_cost'  => $creditCost,
            'status'        => GenerationStatus::Queued,
        ]);

        GenerateImageJob::dispatch($generation)->onQueue('ai_generation');

        return $generation;
    }

    public function resolveGenerator(string $modelSlug): ImageGeneratorInterface
    {
        $config   = $this->resolveModelConfig($modelSlug);
        $provider = $config['sdk_provider'] ?? $config['provider'];

        return app("ai.{$provider}");
    }

    private function resolveModelConfig(string $modelSlug): array
    {
        $config = config("ai_models.{$modelSlug}");

        if (!$config) {
            throw new UnsupportedModelException("Model '{$modelSlug}' is not configured.");
        }

        return $config;
    }

    private function calculateCreditCost(array $params, array $modelConfig): int
    {
        $width = $params['width'] ?? 1024;
        $map   = $modelConfig['credits_map'];

        $tier = collect($map)
            ->keys()
            ->filter(fn ($res) => $width <= $res)
            ->first() ?? array_key_last($map);

        return $map[$tier];
    }
}
```

---

## GenerateImageJob — tier-aware, no provider logic

The job simply asks the generator whether it's synchronous or async, and behaves accordingly. Adding an async provider in the future costs zero job changes.

```php
// app/Jobs/GenerateImageJob.php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\CreditService;
use App\Services\ImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries    = 3;
    public int $timeout  = 120;
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly Generation $generation
    ) {}

    public function handle(ImageGenerationService $service, CreditService $creditService): void
    {
        $this->generation->update(['status' => GenerationStatus::Processing]);

        try {
            $generator = $service->resolveGenerator($this->generation->model);
            $jobId     = $generator->submit($this->generation->params);

            // Sync providers (SDK): jobId is the image URL — done immediately
            // Async providers (Replicate, fal): jobId is a prediction ID — poll
            $imageUrl = $generator->isSynchronous()
                ? $jobId
                : $this->pollUntilComplete($generator, $jobId);

            $this->generation
                ->addMediaFromUrl($imageUrl)
                ->usingFileName("generation-{$this->generation->uuid}.jpg")
                ->toMediaCollection('generations');

            $this->generation->update([
                'status'       => GenerationStatus::Completed,
                'completed_at' => now(),
            ]);

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

    /**
     * Only called for async custom providers (Replicate, fal.ai, etc.)
     * SDK providers are synchronous and never reach this method.
     */
    private function pollUntilComplete($generator, string $jobId): string
    {
        $maxAttempts  = 30;
        $pollInterval = 3;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $result = $generator->getStatus($jobId);

            if ($result['status'] === 'completed') {
                return $result['image_url'];
            }

            if ($result['status'] === 'failed') {
                throw new \RuntimeException(
                    'Provider generation failed: ' . ($result['error'] ?? 'unknown')
                );
            }

            sleep($pollInterval);
        }

        throw new \RuntimeException("Generation timed out after {$maxAttempts} polling attempts.");
    }

    public function failed(\Throwable $exception): void
    {
        $this->generation->update(['status' => GenerationStatus::Failed]);
        app(CreditService::class)->refund(
            $this->generation->user,
            $this->generation->credits_cost
        );
    }
}
```

---

## Testing

The Laravel AI SDK ships with built-in fakes so you never make real API calls in tests:

```php
use Laravel\Ai\Facades\AI;

// In your test
AI::fake([
    'image' => 'https://fake-image-url.com/generated.jpg',
]);

// For custom providers, use Http::fake() as before
Http::fake([
    'api.replicate.com/*' => Http::response([...]),
]);
```

---

## Adding a Future Provider — Complete Checklist

When the Laravel AI SDK does not support a model you want:

```
1. [ ] Create app/Services/AI/{Provider}ImageGenerator.php
         └── implement ImageGeneratorInterface
         └── isSynchronous() returns false for async providers

2. [ ] Register binding in AIServiceProvider
         └── $this->app->bind('ai.{provider}', {Provider}ImageGenerator::class);

3. [ ] Add model entry in config/ai_models.php
         └── provider_tier: 'custom'
         └── provider: '{provider}'

4. [ ] Add API key to .env.example + config/services.php

5. [ ] Write unit test with Http::fake()

6. [ ] Done — zero changes to controllers, jobs, or credit system
```

---

## SDK vs Custom Provider — Quick Reference

| Concern | SDK (OpenAI, Gemini, xAI) | Custom (Replicate, fal.ai) |
|---|---|---|
| Implementation class | `SdkImageGenerator` (shared) | Dedicated class per provider |
| Adding a new model | Entry in `config/ai_models.php` only | Interface + binding + config |
| Failover / retries | Built into SDK automatically | Manual retry in job |
| Testing | `AI::fake()` built-in | `Http::fake()` |
| Async polling | Never needed (sync) | `pollUntilComplete()` in job |
| Prompt following | Excellent | Varies by model |
| Model variety | Limited to SDK-supported list | Unlimited |
