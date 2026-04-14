<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\CreditService;
use App\Services\ImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly Generation $generation,
    ) {}

    public function handle(ImageGenerationService $service, CreditService $creditService): void
    {
        $this->generation->update(['status' => GenerationStatus::Processing]);

        try {
            $generator = $service->resolveGenerator($this->generation->model);
            $result = $generator->submit($this->generation->params->toArray()
                + ['model' => $this->generation->model]);

            $filename = "generation-{$this->generation->uuid}.png";

            if ($generator->isSynchronous()) {
                // result is an absolute path to a temp file written by SdkImageGenerator
                $this->generation
                    ->addMedia($result)
                    ->usingFileName($filename)
                    ->toMediaCollection('generations');

                $this->cleanupTempFile($result);
            } else {
                // result is an async provider job ID — poll until complete
                $imageUrl = $this->pollUntilComplete($generator, $result);

                $this->generation
                    ->addMediaFromUrl($imageUrl)
                    ->usingFileName($filename)
                    ->toMediaCollection('generations');
            }

            $this->generation->update([
                'status' => GenerationStatus::Completed,
                'completed_at' => now(),
            ]);

            $creditService->confirm($this->generation->user, $this->generation->credits_cost);

        } catch (\Throwable $e) {
            $this->generation->update([
                'status' => GenerationStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            $creditService->refund($this->generation->user, $this->generation->credits_cost);

            report($e);
        }
    }

    /**
     * Poll async providers (Replicate, fal.ai, etc.) until the image is ready.
     * SDK providers are synchronous and never reach this method.
     */
    private function pollUntilComplete(mixed $generator, string $jobId): string
    {
        $maxAttempts = 30;
        $pollInterval = 3;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $result = $generator->getStatus($jobId);

            if ($result['status'] === 'completed') {
                return $result['image_url'];
            }

            if ($result['status'] === 'failed') {
                throw new \RuntimeException(
                    'Provider generation failed: '.($result['error'] ?? 'unknown'),
                );
            }

            sleep($pollInterval);
        }

        throw new \RuntimeException("Generation timed out after {$maxAttempts} polling attempts.");
    }

    private function cleanupTempFile(string $absolutePath): void
    {
        $relativePath = str_replace(
            Storage::disk('local')->path(''),
            '',
            $absolutePath,
        );

        Storage::disk('local')->delete($relativePath);
    }

    public function failed(\Throwable $exception): void
    {
        $this->generation->update(['status' => GenerationStatus::Failed]);

        app(CreditService::class)->refund(
            $this->generation->user,
            $this->generation->credits_cost,
        );
    }
}
