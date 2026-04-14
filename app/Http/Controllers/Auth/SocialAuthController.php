<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(UserService $userService): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver('google')->user();
            $user = $userService->findOrCreateFromSocialite('google', $socialUser);

            Auth::login($user, remember: true);

            return redirect()->intended(route('dashboard'));
        } catch (\Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }
    }
}
