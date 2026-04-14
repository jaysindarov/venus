<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'monthly_credits',
        'stripe_monthly_id',
        'stripe_yearly_id',
        'monthly_price',
        'yearly_price',
        'features',
        'max_resolution',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'monthly_credits' => 'integer',
            'max_resolution' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isFree(): bool
    {
        return $this->slug === 'free';
    }

    public static function free(): self
    {
        return static::where('slug', 'free')->firstOrFail();
    }

    public static function active(): Builder
    {
        return static::where('is_active', true)->orderBy('sort_order');
    }
}
