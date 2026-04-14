<?php

declare(strict_types=1);

namespace App\Services\AI\Contracts;

interface ImageGeneratorInterface
{
    /**
     * Submit a generation request.
     * Returns a provider job ID (async) or image URL (sync/SDK).
     */
    public function submit(array $params): string;

    /**
     * Poll/retrieve result for a given job ID.
     * For synchronous providers this simply returns completed status.
     *
     * @return array{status: string, image_url?: string, error?: string}
     */
    public function getStatus(string $jobId): array;

    /**
     * Whether this provider resolves synchronously (no polling needed).
     * Laravel AI SDK providers are synchronous.
     * Replicate, fal.ai are async.
     */
    public function isSynchronous(): bool;
}
