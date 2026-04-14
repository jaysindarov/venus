<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebRegisterTest extends TestCase
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

    public function test_register_page_is_accessible_to_guests(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_authenticated_user_is_redirected_away_from_register(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect();
    }

    public function test_user_can_register_and_is_redirected_to_verify_email(): void
    {
        Notification::fake();

        $this->post('/register', $this->validPayload())
            ->assertRedirect(route('verification.notice'));
    }

    public function test_user_is_authenticated_after_registration(): void
    {
        Notification::fake();

        $this->post('/register', $this->validPayload());

        $this->assertAuthenticated();
    }

    public function test_user_is_persisted_on_registration(): void
    {
        Notification::fake();

        $this->post('/register', $this->validPayload());

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_verification_email_is_sent_after_registration(): void
    {
        Notification::fake();

        $this->post('/register', $this->validPayload());

        $user = User::where('email', 'jane@example.com')->firstOrFail();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        Notification::fake();

        User::factory()->create(['email' => 'jane@example.com']);

        $this->post('/register', $this->validPayload())
            ->assertSessionHasErrors('email');
    }

    public function test_registration_fails_when_passwords_do_not_match(): void
    {
        $this->post('/register', $this->validPayload([
            'password_confirmation' => 'different',
        ]))->assertSessionHasErrors('password');
    }

    public function test_registration_fails_with_short_password(): void
    {
        $this->post('/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))->assertSessionHasErrors('password');
    }

    public function test_registration_fails_when_name_is_missing(): void
    {
        $this->post('/register', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_registration_fails_when_email_is_invalid(): void
    {
        $this->post('/register', $this->validPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }
}
