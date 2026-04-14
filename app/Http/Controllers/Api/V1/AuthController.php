<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user  = $this->userService->register($request->validated());
        $token = $this->userService->issueToken($user);

        return response()->json([
            'data' => [
                'user' => [
                    'id'    => $user->uuid,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ],
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var \App\Models\User $user */
        $user  = Auth::user();
        $token = $this->userService->issueToken($user);

        return response()->json([
            'data' => [
                'user' => [
                    'id'    => $user->uuid,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
