<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug'             => 'free',
                'name'             => 'Free',
                'monthly_credits'  => 50,
                'stripe_monthly_id' => null,
                'stripe_yearly_id'  => null,
                'monthly_price'    => 0,
                'yearly_price'     => 0,
                'features'         => [
                    'models'          => ['dall-e-3'],
                    'max_resolution'  => 1024,
                    'api_access'      => false,
                    'priority_queue'  => false,
                    'gallery_public'  => false,
                    'collections'     => false,
                    'style_presets'   => false,
                ],
                'max_resolution' => 1024,
                'is_active'      => true,
                'sort_order'     => 0,
            ],
            [
                'slug'              => 'basic',
                'name'              => 'Basic',
                'monthly_credits'   => 500,
                'stripe_monthly_id' => env('STRIPE_PRICE_BASIC_MONTHLY'),
                'stripe_yearly_id'  => env('STRIPE_PRICE_BASIC_YEARLY'),
                'monthly_price'     => 9.00,
                'yearly_price'      => 79.00,
                'features'          => [
                    'models'         => ['dall-e-3', 'gemini-imagen'],
                    'max_resolution' => 1024,
                    'api_access'     => false,
                    'priority_queue' => false,
                    'gallery_public' => true,
                    'collections'    => true,
                    'style_presets'  => true,
                ],
                'max_resolution' => 1024,
                'is_active'      => true,
                'sort_order'     => 1,
            ],
            [
                'slug'              => 'pro',
                'name'              => 'Pro',
                'monthly_credits'   => 2000,
                'stripe_monthly_id' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'stripe_yearly_id'  => env('STRIPE_PRICE_PRO_YEARLY'),
                'monthly_price'     => 29.00,
                'yearly_price'      => 239.00,
                'features'          => [
                    'models'         => ['dall-e-3', 'gemini-imagen', 'grok-imagine'],
                    'max_resolution' => 1536,
                    'api_access'     => true,
                    'priority_queue' => true,
                    'gallery_public' => true,
                    'collections'    => true,
                    'style_presets'  => true,
                ],
                'max_resolution' => 1536,
                'is_active'      => true,
                'sort_order'     => 2,
            ],
            [
                'slug'              => 'creator',
                'name'              => 'Creator',
                'monthly_credits'   => 6000,
                'stripe_monthly_id' => env('STRIPE_PRICE_CREATOR_MONTHLY'),
                'stripe_yearly_id'  => env('STRIPE_PRICE_CREATOR_YEARLY'),
                'monthly_price'     => 79.00,
                'yearly_price'      => 649.00,
                'features'          => [
                    'models'         => ['dall-e-3', 'gemini-imagen', 'grok-imagine'],
                    'max_resolution' => 1792,
                    'api_access'     => true,
                    'priority_queue' => true,
                    'gallery_public' => true,
                    'collections'    => true,
                    'style_presets'  => true,
                ],
                'max_resolution' => 1792,
                'is_active'      => true,
                'sort_order'     => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
