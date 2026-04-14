<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\SdkImageGenerator;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ── Tier 1: Laravel AI SDK providers ──────────────────────────
        // All share one SdkImageGenerator class.
        // Provider routing is handled internally by the SDK via model config.
        $this->app->bind('ai.openai', SdkImageGenerator::class);
        $this->app->bind('ai.gemini', SdkImageGenerator::class);
        $this->app->bind('ai.xai',    SdkImageGenerator::class);

        // ── Tier 2: Future custom providers ───────────────────────────
        // When you need a provider the SDK does not yet support:
        //   1. Create app/Services/AI/{Provider}ImageGenerator.php
        //   2. Implement ImageGeneratorInterface
        //   3. Uncomment the binding here
        //   4. Add model entries to config/ai_models.php
        //   5. Add API key to .env + config/services.php
        //
        // $this->app->bind('ai.replicate', ReplicateImageGenerator::class);
        // $this->app->bind('ai.fal',       FalAIImageGenerator::class);
        // $this->app->bind('ai.stability', StabilityImageGenerator::class);
    }

    public function boot(): void {}
}
