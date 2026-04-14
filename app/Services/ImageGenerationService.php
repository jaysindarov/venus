<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Exceptions\InsufficientCreditsException;
use App\Exceptions\UnsupportedModelException;
use App\Jobs\GenerateImageJob;
use App\Models\Generation;
use App\Models\User;
use App\Services\AI\Contracts\ImageGeneratorInterface;

class ImageGenerationService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function dispatch(User $user, array $params): Generation
    {
        $modelConfig = $this->resolveModelConfig($params['model']);
        $creditCost = $this->calculateCreditCost($params, $modelConfig);

        if (! $this->creditService->canAfford($user, $creditCost)) {
            throw new InsufficientCreditsException;
        }

        $this->creditService->reserve($user, $creditCost);

        $generation = Generation::create([
            'user_id' => $user->id,
            'model' => $params['model'],
            'provider' => $modelConfig['sdk_provider'] ?? $modelConfig['provider'],
            'prompt' => $params['prompt'],
            'negative_prompt' => $params['negative_prompt'] ?? null,
            'params' => $params,
            'credits_cost' => $creditCost,
            'status' => GenerationStatus::Queued,
        ]);

        GenerateImageJob::dispatch($generation)->onQueue('ai_generation');

        return $generation;
    }

    public function resolveGenerator(string $modelSlug): ImageGeneratorInterface
    {
        $config = $this->resolveModelConfig($modelSlug);
        $provider = $config['sdk_provider'] ?? $config['provider'];

        return app("ai.{$provider}");
    }

    private function resolveModelConfig(string $modelSlug): array
    {
        $config = config("ai_models.{$modelSlug}");

        if (! $config) {
            throw new UnsupportedModelException("Model '{$modelSlug}' is not configured.");
        }

        return $config;
    }

    private function calculateCreditCost(array $params, array $modelConfig): int
    {
        $width = $params['width'] ?? 1024;
        $map = $modelConfig['credits_map'];

        $tier = collect($map)
            ->keys()
            ->filter(fn ($res) => $width <= $res)
            ->first() ?? array_key_last($map);

        return $map[$tier];
    }
}
