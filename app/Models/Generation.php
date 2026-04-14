<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenerationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Generation extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'uuid',
        'user_id',
        'model',
        'provider',
        'prompt',
        'negative_prompt',
        'params',
        'status',
        'credits_cost',
        'provider_job_id',
        'error_message',
        'is_public',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GenerationStatus::class,
            'params' => 'array',
            'is_public' => 'boolean',
            'completed_at' => 'datetime',
            'credits_cost' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $generation): void {
            $generation->uuid ??= (string) Str::uuid();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('generations')->singleFile();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('generations') ?: null;
    }
}
