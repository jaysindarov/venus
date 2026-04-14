<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Contracts\ImageGeneratorInterface;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;

/**
 * Wraps the Laravel AI SDK for all SDK-backed providers (OpenAI, Gemini, xAI).
 * Switching between providers is a config-only change — no code changes needed.
 *
 * submit() is synchronous: the SDK calls the API, gets base64 back, writes to a
 * local temp file, and returns the absolute path. GenerateImageJob then hands
 * the path directly to Spatie Media Library via addMedia().
 */
class SdkImageGenerator implements ImageGeneratorInterface
{
    public function submit(array $params): string
    {
        $modelConfig = config("ai_models.{$params['model']}");

        $response = Image::of($this->buildPrompt($params))
            ->size($this->resolveSize($params))
            ->generate($modelConfig['sdk_provider'], $modelConfig['sdk_model']);

        $generated = $response->firstImage();
        $extension = $this->mimeToExtension($generated->mime ?? 'image/png');
        $tempPath = 'temp/generation_'.uniqid('', true).'.'.$extension;

        Storage::disk('local')->put($tempPath, $generated->content());

        return Storage::disk('local')->path($tempPath);
    }

    public function getStatus(string $jobId): array
    {
        // SDK is synchronous — this is never called in practice.
        // The temp file path from submit() is handed straight to the job.
        return ['status' => 'completed', 'image_url' => $jobId];
    }

    public function isSynchronous(): bool
    {
        return true;
    }

    private function buildPrompt(array $params): string
    {
        $prompt = $params['prompt'];

        if (! empty($params['style_suffix'])) {
            $prompt .= ', '.$params['style_suffix'];
        }

        return $prompt;
    }

    /**
     * Map generation params to the SDK's size/aspect-ratio format.
     * The SDK accepts aspect-ratio strings like '1:1', '16:9', '3:2'.
     */
    private function resolveSize(array $params): string
    {
        if (! empty($params['aspect_ratio'])) {
            return $params['aspect_ratio'];
        }

        $width = $params['width'] ?? 1024;
        $height = $params['height'] ?? 1024;

        if ($width === $height) {
            return '1:1';
        }

        return $width > $height ? '3:2' : '2:3';
    }

    private function mimeToExtension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };
    }
}
