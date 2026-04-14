<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_reset_link_is_sent_for_existing_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_link_request_does_not_reveal_nonexistent_email(): void
    {
        // Laravel returns a generic "we sent a link" message regardless —
        // but if the email is unknown, no notification should be sent.
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'ghost@example.com']);

        Notification::assertNothingSent();
    }

    public function test_reset_password_page_is_accessible_with_token(): void
    {
        $this->get('/reset-password/fake-token?email=user@example.com')->assertOk();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertRedirect(route('login'));
    }

    public function test_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'bad-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_fails_when_passwords_do_not_match(): void
    {
        $this->post('/reset-password', [
            'token' => 'any-token',
            'email' => 'user@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
    }

    public function test_reset_fails_with_short_password(): void
    {
        $this->post('/reset-password', [
            'token' => 'any-token',
            'email' => 'user@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }
}
