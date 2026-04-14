<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateImageRequest;
use App\Models\Generation;
use App\Services\ImageGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationController extends Controller
{
    public function __construct(
        private readonly ImageGenerationService $service,
    ) {}

    public function store(GenerateImageRequest $request): JsonResponse
    {
        try {
            $generation = $this->service->dispatch(
                user: $request->user(),
                params: $request->validated(),
            );
        } catch (InsufficientCreditsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INSUFFICIENT_CREDITS',
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $generation->uuid,
                'status' => $generation->status->value,
                'credits_cost' => $generation->credits_cost,
                'estimated_seconds' => 15,
                'poll_url' => route('api.generations.status', $generation->uuid),
            ],
        ], 202);
    }

    public function status(Request $request, string $uuid): JsonResponse
    {
        $generation = Generation::where('uuid', $uuid)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $generation->uuid,
                'status' => $generation->status->value,
                'image_url' => $generation->image_url,
                'completed_at' => $generation->completed_at?->toIso8601String(),
                'error' => $generation->error_message,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $generations = Generation::where('user_id', $request->user()->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $generations->map(fn ($g) => [
                'id' => $g->uuid,
                'prompt' => $g->prompt,
                'model' => $g->model,
                'status' => $g->status->value,
                'image_url' => $g->image_url,
                'credits_cost' => $g->credits_cost,
                'created_at' => $g->created_at->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $generations->currentPage(),
                'last_page' => $generations->lastPage(),
                'per_page' => $generations->perPage(),
                'total' => $generations->total(),
            ],
        ]);
    }
}
