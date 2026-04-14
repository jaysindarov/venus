<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CreditService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'       => $user->uuid,
                    'name'     => $user->name,
                    'email'    => $user->email,
                    'role'     => $user->role,
                    'avatar'   => $user->avatar_url,
                ] : null,
                'credits' => $user ? fn () => $this->creditService->balance($user) : null,
                'plan'    => $user ? fn () => $user->subscription('default')?->stripe_price ?? 'free' : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
