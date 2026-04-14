<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GenerationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    // ── Auth ─────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
        });
    });

    // ── Generations (auth required) ──────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/generations', [GenerationController::class, 'store'])->name('api.generations.store');
        Route::get('/generations', [GenerationController::class, 'index'])->name('api.generations.index');
        Route::get('/generations/{uuid}/status', [GenerationController::class, 'status'])->name('api.generations.status');
    });

});
