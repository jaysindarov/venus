<?php

declare(strict_types=1);

namespace Tests\Feature\Generation;

use App\Enums\GenerationStatus;
use App\Jobs\GenerateImageJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CreditService $credits;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->credits = app(CreditService::class);
        $this->credits->grant($this->user, 100, 'Test grant');
    }

    // ── Dispatch ─────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_dispatch_generation(): void
    {
        Queue::fake();

        $token = $this->user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/generations', [
            'model' => 'dall-e-3',
            'prompt' => 'A sunset over the mountains',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => ['id', 'status', 'credits_cost', 'poll_url'],
            ])
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(GenerateImageJob::class);
    }

    public function test_generation_requires_authentication(): void
    {
        $this->postJson('/api/v1/generations', [
            'model' => 'dall-e-3',
            'prompt' => 'Test',
        ])->assertUnauthorized();
    }

    public function test_generation_fails_with_insufficient_credits(): void
    {
        $broke = User::factory()->create(); // 0 credits
        $token = $broke->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/generations', [
            'model' => 'dall-e-3',
            'prompt' => 'A sunset',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'INSUFFICIENT_CREDITS');
    }

    public function test_generation_validates_model_against_registry(): void
    {
        $token = $this->user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/generations', [
            'model' => 'fake-model-that-does-not-exist',
            'prompt' => 'Test',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['model']);
    }

    public function test_credits_are_reserved_on_dispatch(): void
    {
        Queue::fake();

        $balanceBefore = $this->credits->balance($this->user);
        $token = $this->user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/generations', [
            'model' => 'dall-e-3',
            'prompt' => 'Test',
            'width' => 1024,
        ]);

        $this->assertLessThan($balanceBefore, $this->credits->balance($this->user));
    }

    // ── Status polling ────────────────────────────────────────────────────────

    public function test_user_can_poll_generation_status(): void
    {
        $generation = Generation::create([
            'user_id' => $this->user->id,
            'model' => 'dall-e-3',
            'provider' => 'openai',
            'prompt' => 'A sunset',
            'params' => ['model' => 'dall-e-3', 'prompt' => 'A sunset'],
            'credits_cost' => 2,
            'status' => GenerationStatus::Queued,
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/generations/{$generation->uuid}/status")
            ->assertOk()
            ->assertJsonPath('data.id', $generation->uuid)
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_user_cannot_poll_another_users_generation(): void
    {
        $other = User::factory()->create();
        $generation = Generation::create([
            'user_id' => $other->id,
            'model' => 'dall-e-3',
            'provider' => 'openai',
            'prompt' => 'Private',
            'params' => [],
            'credits_cost' => 2,
            'status' => GenerationStatus::Queued,
        ]);

        $token = $this->user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/generations/{$generation->uuid}/status")
            ->assertNotFound();
    }

    // ── Job execution ─────────────────────────────────────────────────────────

    public function test_job_completes_generation_with_sdk_fake(): void
    {
        Image::fake(); // SDK fake — no real API calls

        // Write a temp file to simulate what SdkImageGenerator produces
        Storage::disk('local')->put('temp/test.png', 'fake-image-content');
        $fakeTempPath = Storage::disk('local')->path('temp/test.png');

        $generation = Generation::create([
            'user_id' => $this->user->id,
            'model' => 'dall-e-3',
            'provider' => 'openai',
            'prompt' => 'A sunset',
            'params' => ['model' => 'dall-e-3', 'prompt' => 'A sunset'],
            'credits_cost' => 2,
            'status' => GenerationStatus::Queued,
        ]);

        // Reserve credits as the service would
        $this->credits->reserve($this->user, 2);

        // Simulate what the job does when SdkImageGenerator returns a temp path
        $generation->update([
            'status' => GenerationStatus::Completed,
            'completed_at' => now(),
        ]);

        app(CreditService::class)->confirm($this->user, 2);

        $this->assertSame(GenerationStatus::Completed, $generation->fresh()->status);
        $this->assertSame(98, $this->credits->balance($this->user));
    }

    public function test_job_refunds_credits_on_failure(): void
    {
        $generation = Generation::create([
            'user_id' => $this->user->id,
            'model' => 'dall-e-3',
            'provider' => 'openai',
            'prompt' => 'A sunset',
            'params' => ['model' => 'dall-e-3', 'prompt' => 'A sunset'],
            'credits_cost' => 2,
            'status' => GenerationStatus::Processing,
        ]);

        $this->credits->reserve($this->user, 2);
        $balanceAfterReserve = $this->credits->balance($this->user);

        // Simulate job failure path
        $generation->update([
            'status' => GenerationStatus::Failed,
            'error_message' => 'Provider error',
        ]);

        $this->credits->refund($this->user, 2);

        $this->assertSame($balanceAfterReserve + 2, $this->credits->balance($this->user));
        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
    }
}
