<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UserService
{
    /**
     * Create a new user and send email verification notification.
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->sendEmailVerificationNotification();

        return $user;
    }

    /**
     * Issue a Sanctum API token for the given user.
     */
    public function issueToken(User $user, string $name = 'auth_token'): string
    {
        return $user->createToken($name)->plainTextToken;
    }

    /**
     * Find or create a user from a Socialite OAuth callback.
     * OAuth emails are treated as pre-verified.
     */
    public function findOrCreateFromSocialite(string $provider, SocialiteUser $socialUser): User
    {
        $social = SocialAccount::where('provider', $provider)
            ->where('provider_user_id', $socialUser->getId())
            ->with('user')
            ->first();

        if ($social) {
            return $social->user;
        }

        $user = User::firstOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'name' => $socialUser->getName(),
                'avatar_url' => $socialUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => null,
            ],
        );

        $user->socialAccounts()->create([
            'provider' => $provider,
            'provider_user_id' => $socialUser->getId(),
            'token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken,
        ]);

        return $user;
    }
}
