<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function index(): Response
    {
        $models = collect(config('ai_models'))
            ->filter(fn ($m) => $m['provider_tier'] === 'sdk' || isset($m['provider']))
            ->map(fn ($m, $slug) => [
                'slug' => $slug,
                'name' => $m['name'],
                'description' => $m['description'],
                'supports_negative_prompt' => $m['supports_negative_prompt'],
                'aspect_ratios' => $m['aspect_ratios'],
                'credits_map' => $m['credits_map'],
                'available_plans' => $m['available_plans'],
            ])
            ->values();

        return Inertia::render('Generate', [
            'models' => $models,
        ]);
    }
}
