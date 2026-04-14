<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditLedger;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreditService
{
    private const CACHE_TTL = 300; // 5 minutes

    // ─── Read ─────────────────────────────────────────────────────────────────

    /**
     * Return the user's current credit balance.
     * Computed as SUM(amount) over the ledger — cached for CACHE_TTL seconds.
     */
    public function balance(User $user): int
    {
        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL,
            fn (): int => (int) CreditLedger::where('user_id', $user->id)->sum('amount'),
        );
    }

    public function canAfford(User $user, int $cost): bool
    {
        return $this->balance($user) >= $cost;
    }

    // ─── Write ────────────────────────────────────────────────────────────────

    /**
     * Grant credits (plan activation, monthly reset, promotional award).
     */
    public function grant(User $user, int $amount, string $description = 'Monthly credit grant'): void
    {
        $this->createEntry($user, CreditTransactionType::Grant, $amount, $description);
    }

    /**
     * Reserve credits before a generation job is dispatched.
     * Writes a negative entry immediately so the balance reflects in-flight cost.
     */
    public function reserve(User $user, int $amount): void
    {
        $this->createEntry($user, CreditTransactionType::Reserve, -$amount, 'Credit reservation');
    }

    /**
     * Confirm a completed generation.
     * The reserve entry already deducted the credits — this call only busts
     * the cache so the next balance() reads fresh from the ledger.
     */
    public function confirm(User $user, int $amount): void
    {
        // No new ledger row — the reserve entry is the deduction.
        // Clear the cache so the settled balance is immediately visible.
        $this->clearCache($user);
    }

    /**
     * Refund a failed generation. Restores the reserved credits.
     */
    public function refund(User $user, int $amount): void
    {
        $this->createEntry($user, CreditTransactionType::Refund, $amount, 'Generation refund');
    }

    /**
     * Credit top-up purchased by the user (one-time purchase via Stripe).
     */
    public function topup(User $user, int $amount): void
    {
        $this->createEntry($user, CreditTransactionType::Topup, $amount, 'Credit top-up');
    }

    /**
     * Admin-issued manual adjustment. Amount may be positive or negative.
     */
    public function manualAdjust(User $user, int $amount, string $description): void
    {
        $this->createEntry($user, CreditTransactionType::ManualAdjust, $amount, $description);
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Persist a single ledger row inside a transaction.
     * Computes balance_after as a running-total snapshot for audit queries.
     * Busts the cached balance after the row is committed.
     *
     * @param array<string, mixed>|null $metadata
     */
    private function createEntry(
        User $user,
        CreditTransactionType $type,
        int $amount,
        string $description,
        ?array $metadata = null,
    ): void {
        DB::transaction(function () use ($user, $type, $amount, $description, $metadata): void {
            // Snapshot the running total BEFORE this entry is committed so
            // balance_after = what the user sees after this row lands.
            $runningBalance = $this->balance($user) + $amount;

            CreditLedger::create([
                'user_id'       => $user->id,
                'type'          => $type,
                'amount'        => $amount,
                'balance_after' => max(0, $runningBalance),
                'description'   => $description,
                'metadata'      => $metadata,
            ]);

            $this->clearCache($user);
        });
    }

    private function clearCache(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return "credits:balance:{$user->id}";
    }
}
