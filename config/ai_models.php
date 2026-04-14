<?php

declare(strict_types=1);

/**
 * AI Model Registry — single source of truth for all image generation models.
 *
 * TIER 1: Laravel AI SDK backed (OpenAI, Gemini, xAI)
 *   → Adding a new SDK model: add an entry with provider_tier='sdk'. No code changes.
 *
 * TIER 2: Custom provider implementations (Replicate, fal.ai, etc.)
 *   → Requires: implement ImageGeneratorInterface + binding in AIServiceProvider + entry here.
 *
 * ARCHITECTURE RULE: Never add a model without a config entry here.
 */
return [

    // ─────────────────────────────────────────────────────────────────
    // TIER 1: Laravel AI SDK backed models
    // ─────────────────────────────────────────────────────────────────

    'dall-e-3' => [
        'name'                     => 'DALL-E 3',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'openai',
        'sdk_model'                => 'dall-e-3',
        'description'              => 'OpenAI flagship — highest prompt accuracy',
        'supports_negative_prompt' => false,
        'supports_image_to_image'  => false,
        'max_resolution'           => 1792,
        'aspect_ratios'            => ['1:1', '16:9', '9:16'],
        'available_plans'          => ['basic', 'pro', 'creator'],
        'credits_map'              => [512 => 1, 1024 => 2, 1792 => 4],
    ],

    'gemini-imagen' => [
        'name'                     => 'Gemini Imagen',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'gemini',
        'sdk_model'                => 'imagen-3.0-generate-002',
        'description'              => 'Google Imagen — photorealistic, great for scenes',
        'supports_negative_prompt' => true,
        'supports_image_to_image'  => true,
        'max_resolution'           => 1024,
        'aspect_ratios'            => ['1:1', '3:4', '4:3', '16:9', '9:16'],
        'available_plans'          => ['basic', 'pro', 'creator'],
        'credits_map'              => [512 => 1, 1024 => 2],
    ],

    'grok-imagine' => [
        'name'                     => 'Grok Imagine',
        'provider_tier'            => 'sdk',
        'sdk_provider'             => 'xai',
        'sdk_model'                => 'grok-2-image',
        'description'              => 'xAI Grok — fast, creative, great aesthetics',
        'supports_negative_prompt' => false,
        'supports_image_to_image'  => false,
        'max_resolution'           => 1024,
        'aspect_ratios'            => ['1:1', '16:9'],
        'available_plans'          => ['pro', 'creator'],
        'credits_map'              => [1024 => 2],
    ],

    // ─────────────────────────────────────────────────────────────────
    // TIER 2: Custom provider models (future scale — uncomment when ready)
    // ─────────────────────────────────────────────────────────────────

    // 'flux-dev' => [
    //     'name'                     => 'Flux Dev',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'replicate',
    //     'replicate_model'          => 'black-forest-labs/flux-dev',
    //     'description'              => 'Open-source, excellent realism and detail',
    //     'supports_negative_prompt' => true,
    //     'supports_image_to_image'  => true,
    //     'max_resolution'           => 1024,
    //     'aspect_ratios'            => ['1:1', '3:4', '4:3', '16:9'],
    //     'available_plans'          => ['pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 2],
    // ],

    // 'sdxl' => [
    //     'name'                     => 'Stable Diffusion XL',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'replicate',
    //     'replicate_model'          => 'stability-ai/sdxl:...',
    //     'description'              => 'Open-source powerhouse, great for art styles',
    //     'supports_negative_prompt' => true,
    //     'supports_image_to_image'  => false,
    //     'max_resolution'           => 1024,
    //     'available_plans'          => ['free', 'basic', 'pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 2],
    // ],

    // 'flux-schnell' => [
    //     'name'                     => 'Flux Schnell',
    //     'provider_tier'            => 'custom',
    //     'provider'                 => 'fal',
    //     'fal_model'                => 'fal-ai/flux/schnell',
    //     'description'              => 'Ultra-fast generation in ~2 seconds',
    //     'supports_negative_prompt' => false,
    //     'supports_image_to_image'  => false,
    //     'max_resolution'           => 1024,
    //     'available_plans'          => ['basic', 'pro', 'creator'],
    //     'credits_map'              => [512 => 1, 1024 => 1],
    // ],

];
