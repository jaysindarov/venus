# Testing Strategy

## Testing Pyramid

```
         /\
        /E2E\         — Browser tests (Playwright) — slow, few
       /------\
      / Feature \     — Laravel Feature tests — medium, many
     /------------\
    /  Unit Tests  \  — PHPUnit Unit + Vitest — fast, most
   /----------------\
```

**Minimum coverage target: 70% overall, 90% on Services and Jobs**

---

## Backend Testing (PHPUnit / Laravel)

### Test Environment Setup

```bash
# .env.testing
APP_ENV=testing
DB_CONNECTION=mysql
DB_DATABASE=visionaryai_testing
QUEUE_CONNECTION=sync          # Run jobs synchronously in tests
MAIL_MAILER=array              # Capture emails, don't send
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
```

### Running Tests
```bash
php artisan test                           # All tests
php artisan test --parallel                # Parallel (faster)
php artisan test --filter GenerationTest   # Single test class
php artisan test --coverage               # With coverage report
php artisan test tests/Feature/           # Feature tests only
php artisan test tests/Unit/              # Unit tests only
```

---

## Unit Tests

Test individual classes in isolation. Mock all dependencies.

```php
// tests/Unit/Services/CreditServiceTest.php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CreditService();
    }

    public function test_balance_returns_zero_for_new_user(): void
    {
        $user = User::factory()->create();
        $this->assertEquals(0, $this->service->balance($user));
    }

    public function test_grant_increases_balance(): void
    {
        $user = User::factory()->create();
        $this->service->grant($user, 1000, 'Test grant');
        $this->assertEquals(1000, $this->service->balance($user));
    }

    public function test_reserve_decreases_balance(): void
    {
        $user = User::factory()->create();
        $this->service->grant($user, 100);
        $this->service->reserve($user, 10);
        $this->assertEquals(90, $this->service->balance($user));
    }

    public function test_refund_restores_balance(): void
    {
        $user = User::factory()->create();
        $this->service->grant($user, 100);
        $this->service->reserve($user, 10);
        $this->service->refund($user, 10);
        $this->assertEquals(100, $this->service->balance($user));
    }

    public function test_can_afford_returns_true_when_sufficient(): void
    {
        $user = User::factory()->create();
        $this->service->grant($user, 50);
        $this->assertTrue($this->service->canAfford($user, 2));
    }

    public function test_can_afford_returns_false_when_insufficient(): void
    {
        $user = User::factory()->create();
        $this->assertFalse($this->service->canAfford($user, 100));
    }
}
```

```php
// tests/Unit/Services/AI/OpenAIImageGeneratorTest.php

namespace Tests\Unit\Services\AI;

use App\Services\AI\OpenAIImageGenerator;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIImageGeneratorTest extends TestCase
{
    public function test_submit_returns_image_url_on_success(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['url' => 'https://example.com/image.jpg']]
            ], 200)
        ]);

        $generator = new OpenAIImageGenerator();
        $result = $generator->submit([
            'prompt' => 'A beautiful sunset',
            'width'  => 1024,
            'height' => 1024,
        ]);

        $this->assertEquals('https://example.com/image.jpg', $result);
    }

    public function test_submit_throws_on_api_error(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'Rate limit']], 429)
        ]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        $generator = new OpenAIImageGenerator();
        $generator->submit(['prompt' => 'test', 'width' => 1024, 'height' => 1024]);
    }
}
```

---

## Feature Tests

Test full HTTP request → response cycles including DB.

```php
// tests/Feature/GenerationControllerTest.php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_submit_generation(): void
    {
        $user = User::factory()->create();
        $creditService = app(CreditService::class);
        $creditService->grant($user, 100);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/generations', [
                'model'  => 'dall-e-3',
                'prompt' => 'A beautiful mountain landscape',
                'width'  => 1024,
                'height' => 1024,
            ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonStructure(['data' => ['id', 'status', 'credits_cost', 'poll_url']]);

        $this->assertDatabaseHas('generations', [
            'user_id' => $user->id,
            'status'  => GenerationStatus::Queued->value,
            'model'   => 'dall-e-3',
        ]);
    }

    public function test_generation_fails_with_insufficient_credits(): void
    {
        $user = User::factory()->create();
        // No credits granted

        $response = $this->actingAs($user)
            ->postJson('/api/v1/generations', [
                'model'  => 'dall-e-3',
                'prompt' => 'A landscape',
                'width'  => 1024,
                'height' => 1024,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INSUFFICIENT_CREDITS');
    }

    public function test_unauthenticated_user_cannot_generate(): void
    {
        $this->postJson('/api/v1/generations', ['prompt' => 'test'])
            ->assertStatus(401);
    }

    public function test_user_can_only_view_own_generations(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $generation = Generation::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->getJson("/api/v1/generations/{$generation->uuid}")
            ->assertStatus(403);
    }

    public function test_generation_status_polling(): void
    {
        $user = User::factory()->create();
        $generation = Generation::factory()
            ->for($user)
            ->completed()
            ->create();

        $this->actingAs($user)
            ->getJson("/api/v1/generations/{$generation->uuid}/status")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonStructure(['data' => ['status', 'image_url', 'thumbnail_url']]);
    }
}
```

---

## Job Tests

```php
// tests/Unit/Jobs/GenerateImageJobTest.php

namespace Tests\Unit\Jobs;

use App\Enums\GenerationStatus;
use App\Jobs\GenerateImageJob;
use App\Models\Generation;
use App\Services\AI\Contracts\ImageGeneratorInterface;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GenerateImageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_marks_generation_completed_on_success(): void
    {
        $generation = Generation::factory()->create(['status' => GenerationStatus::Queued]);

        $mockProvider = Mockery::mock(ImageGeneratorInterface::class);
        $mockProvider->shouldReceive('submit')->once()->andReturn('job-id-123');
        $mockProvider->shouldReceive('getStatus')->once()->andReturn([
            'status'    => 'completed',
            'image_url' => 'https://example.com/image.jpg',
        ]);

        $this->app->bind("ai.{$generation->provider}", fn() => $mockProvider);

        // Mock Media Library storage
        // ...

        $job = new GenerateImageJob($generation);
        $job->handle(app(CreditService::class));

        $this->assertEquals(GenerationStatus::Completed, $generation->fresh()->status);
    }

    public function test_job_refunds_credits_on_failure(): void
    {
        $user = User::factory()->create();
        $creditService = app(CreditService::class);
        $creditService->grant($user, 100);
        $creditService->reserve($user, 2);

        $generation = Generation::factory()
            ->for($user)
            ->create(['status' => GenerationStatus::Processing, 'credits_cost' => 2]);

        $mockProvider = Mockery::mock(ImageGeneratorInterface::class);
        $mockProvider->shouldReceive('submit')->once()->andThrow(new \RuntimeException('API error'));

        $this->app->bind("ai.{$generation->provider}", fn() => $mockProvider);

        $job = new GenerateImageJob($generation);
        $job->handle($creditService);

        $this->assertEquals(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertEquals(100, $creditService->balance($user));  // Credits refunded
    }
}
```

---

## Factory Definitions

```php
// database/factories/GenerationFactory.php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'model'        => 'dall-e-3',
            'provider'     => 'openai',
            'prompt'       => $this->faker->sentence(10),
            'params'       => ['width' => 1024, 'height' => 1024],
            'status'       => GenerationStatus::Queued,
            'credits_cost' => 2,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => GenerationStatus::Completed]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'        => GenerationStatus::Failed,
            'error_message' => 'Provider error',
        ]);
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }
}
```

---

## Frontend Testing (Vitest)

```bash
# Run frontend tests
npm run test
npm run test:coverage
```

```js
// resources/js/composables/__tests__/useGeneration.test.js

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { useGeneration } from '../useGeneration'

describe('useGeneration', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('starts with idle status', () => {
    const { status } = useGeneration()
    expect(status.value).toBe(null)
  })

  it('sets status to submitting on generate call', async () => {
    const mockAxios = vi.fn().mockResolvedValueOnce({
      data: { data: { id: 'test-uuid', status: 'queued' } }
    })

    vi.mock('@/lib/axios', () => ({ default: { post: mockAxios, get: vi.fn() } }))

    const { status, generate } = useGeneration()
    generate({ model: 'dall-e-3', prompt: 'test' })

    expect(status.value).toBe('submitting')
  })
})
```

---

## Subscription / Billing Tests

```php
// tests/Feature/BillingTest.php

public function test_stripe_webhook_grants_credits_on_invoice_paid(): void
{
    $user = User::factory()->create(['stripe_id' => 'cus_test123']);

    // Simulate Stripe invoice.paid webhook
    $payload = [
        'type' => 'invoice.paid',
        'data' => [
            'object' => [
                'customer'       => 'cus_test123',
                'billing_reason' => 'subscription_cycle',
            ]
        ]
    ];

    $signature = $this->generateStripeSignature($payload);

    $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => $signature
    ])->assertStatus(200);

    $this->assertGreaterThan(0, app(CreditService::class)->balance($user));
}
```

---

## Testing Checklist (Before Each PR)

- [ ] `php artisan test` passes with 0 failures
- [ ] `./vendor/bin/pint --test` passes (code style)
- [ ] New feature has at least one feature test (happy path)
- [ ] Error paths tested (unauthorized, invalid input, insufficient credits)
- [ ] New service classes have unit tests
- [ ] `npm run test` passes for frontend changes
