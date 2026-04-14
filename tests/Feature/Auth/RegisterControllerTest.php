<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_user_can_register_with_valid_data(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.user.name', 'Jane Doe');
    }

    public function test_user_is_persisted_to_database_on_registration(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->validPayload());

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ]);
    }

    public function test_registration_returns_a_sanctum_token(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $token = $response->json('data.token');

        $this->assertNotEmpty($token);
        $this->assertStringContainsString('|', $token); // Sanctum plain-text token format: "{id}|{token}"
    }

    public function test_verification_email_is_sent_after_registration(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->validPayload());

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    // ─── Duplicate email ──────────────────────────────────────────────────────

    public function test_registration_fails_with_duplicate_email(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_duplicate_email_error_message_is_human_readable(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response = $this->postJson('/api/v1/auth/register', $this->validPayload());

        $response->assertJsonPath(
            'errors.email.0',
            'An account with this email address already exists.'
        );
    }

    // ─── Weak / invalid password ──────────────────────────────────────────────

    public function test_registration_fails_with_password_too_short(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_registration_fails_when_passwords_do_not_match(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'password_confirmation' => 'different_password',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    // ─── Required field validation ────────────────────────────────────────────

    public function test_registration_fails_when_name_is_missing(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload(['name' => '']));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_registration_fails_when_email_is_invalid(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->validPayload([
            'email' => 'not-an-email',
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
