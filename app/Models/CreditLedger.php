<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedger extends Model
{
    /**
     * Append-only ledger — no updated_at column exists.
     * updated_at is explicitly excluded; created_at is managed by the DB default.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'generation_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type'     => CreditTransactionType::class,
            'metadata' => 'array',
            'amount'   => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
