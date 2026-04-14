<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditLedger;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreditServiceTest extends TestCase
{
    use RefreshDatabase;

    private CreditService $credits;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->credits = new CreditService();
        $this->user    = User::factory()->create();
    }

    // ─── balance() ────────────────────────────────────────────────────────────

    public function test_balance_returns_zero_for_new_user(): void
    {
        $this->assertSame(0, $this->credits->balance($this->user));
    }

    public function test_balance_sums_all_positive_and_negative_entries(): void
    {
        $this->credits->grant($this->user, 1000);
        $this->credits->reserve($this->user, 4);

        $this->assertSame(996, $this->credits->balance($this->user));
    }

    public function test_balance_is_served_from_cache_on_second_call(): void
    {
        $this->credits->grant($this->user, 500);

        // Prime the cache with a first call
        $this->credits->balance($this->user);

        // Count DB queries — the second call must not touch the DB
        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void { $queryCount++; });

        $this->credits->balance($this->user);

        $this->assertSame(0, $queryCount, 'Second balance() call should be served from cache.');
    }

    public function test_balance_cache_is_busted_after_new_ledger_entry(): void
    {
        $this->credits->grant($this->user, 500);
        $this->assertSame(500, $this->credits->balance($this->user)); // primes cache

        $this->credits->grant($this->user, 100); // busts cache

        $this->assertSame(600, $this->credits->balance($this->user)); // fresh DB read
    }

    // ─── canAfford() ──────────────────────────────────────────────────────────

    public function test_can_afford_returns_false_when_balance_is_zero(): void
    {
        $this->assertFalse($this->credits->canAfford($this->user, 1));
    }

    public function test_can_afford_returns_true_when_balance_equals_cost(): void
    {
        $this->credits->grant($this->user, 2);

        $this->assertTrue($this->credits->canAfford($this->user, 2));
    }

    public function test_can_afford_returns_true_when_balance_exceeds_cost(): void
    {
        $this->credits->grant($this->user, 100);

        $this->assertTrue($this->credits->canAfford($this->user, 2));
    }

    public function test_can_afford_returns_false_when_cost_exceeds_balance(): void
    {
        $this->credits->grant($this->user, 1);

        $this->assertFalse($this->credits->canAfford($this->user, 2));
    }

    // ─── grant() ──────────────────────────────────────────────────────────────

    public function test_grant_creates_positive_ledger_entry(): void
    {
        $this->credits->grant($this->user, 4000, 'Pro plan — April');

        $entry = CreditLedger::where('user_id', $this->user->id)->sole();

        $this->assertSame(CreditTransactionType::Grant, $entry->type);
        $this->assertSame(4000, $entry->amount);
        $this->assertSame(4000, (int) $entry->balance_after);
        $this->assertSame('Pro plan — April', $entry->description);
    }

    public function test_grant_increases_balance(): void
    {
        $this->credits->grant($this->user, 4000);

        $this->assertSame(4000, $this->credits->balance($this->user));
    }

    public function test_grant_uses_default_description_when_none_given(): void
    {
        $this->credits->grant($this->user, 100);

        $entry = CreditLedger::where('user_id', $this->user->id)->sole();

        $this->assertSame('Monthly credit grant', $entry->description);
    }

    // ─── reserve() ────────────────────────────────────────────────────────────

    public function test_reserve_creates_negative_ledger_entry(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);

        $entry = CreditLedger::where('user_id', $this->user->id)
            ->where('type', CreditTransactionType::Reserve)
            ->sole();

        $this->assertSame(CreditTransactionType::Reserve, $entry->type);
        $this->assertSame(-2, $entry->amount);
        $this->assertSame(98, (int) $entry->balance_after);
    }

    public function test_reserve_reduces_balance(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);

        $this->assertSame(98, $this->credits->balance($this->user));
    }

    public function test_multiple_reserves_reduce_balance_cumulatively(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);
        $this->credits->reserve($this->user, 2);

        $this->assertSame(96, $this->credits->balance($this->user));
    }

    // ─── confirm() ────────────────────────────────────────────────────────────

    public function test_confirm_does_not_write_a_ledger_row(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);

        $countBefore = CreditLedger::count();
        $this->credits->confirm($this->user, 2);

        $this->assertSame($countBefore, CreditLedger::count());
    }

    public function test_confirm_does_not_change_balance(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);

        $balanceBefore = $this->credits->balance($this->user);
        $this->credits->confirm($this->user, 2);

        $this->assertSame($balanceBefore, $this->credits->balance($this->user));
    }

    public function test_confirm_busts_the_cache(): void
    {
        $this->credits->grant($this->user, 100);
        $cacheKey = "credits:balance:{$this->user->id}";

        $this->credits->balance($this->user); // prime cache
        $this->assertTrue(Cache::has($cacheKey));

        $this->credits->confirm($this->user, 2);

        $this->assertFalse(Cache::has($cacheKey));
    }

    // ─── refund() ─────────────────────────────────────────────────────────────

    public function test_refund_creates_positive_ledger_entry(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);
        $this->credits->refund($this->user, 2);

        $entry = CreditLedger::where('type', CreditTransactionType::Refund)->sole();

        $this->assertSame(CreditTransactionType::Refund, $entry->type);
        $this->assertSame(2, $entry->amount);
        $this->assertSame(100, (int) $entry->balance_after);
    }

    public function test_refund_restores_balance_to_pre_reserve_amount(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->reserve($this->user, 2);
        $this->credits->refund($this->user, 2);

        $this->assertSame(100, $this->credits->balance($this->user));
    }

    // ─── topup() ──────────────────────────────────────────────────────────────

    public function test_topup_creates_positive_ledger_entry(): void
    {
        $this->credits->topup($this->user, 500);

        $entry = CreditLedger::where('user_id', $this->user->id)->sole();

        $this->assertSame(CreditTransactionType::Topup, $entry->type);
        $this->assertSame(500, $entry->amount);
    }

    public function test_topup_increases_balance(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->topup($this->user, 500);

        $this->assertSame(600, $this->credits->balance($this->user));
    }

    // ─── manualAdjust() ───────────────────────────────────────────────────────

    public function test_manual_adjust_can_add_credits(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->manualAdjust($this->user, 50, 'Compensation for outage');

        $this->assertSame(150, $this->credits->balance($this->user));

        $entry = CreditLedger::where('type', CreditTransactionType::ManualAdjust)->sole();
        $this->assertSame(50, $entry->amount);
        $this->assertSame('Compensation for outage', $entry->description);
    }

    public function test_manual_adjust_can_deduct_credits(): void
    {
        $this->credits->grant($this->user, 100);
        $this->credits->manualAdjust($this->user, -30, 'Admin correction');

        $this->assertSame(70, $this->credits->balance($this->user));
    }

    // ─── balance_after snapshot accuracy ──────────────────────────────────────

    public function test_balance_after_tracks_running_total_correctly(): void
    {
        $this->credits->grant($this->user, 1000);
        $this->credits->reserve($this->user, 2);
        $this->credits->refund($this->user, 2);
        $this->credits->topup($this->user, 500);

        $snapshots = CreditLedger::where('user_id', $this->user->id)
            ->orderBy('id')
            ->pluck('balance_after')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->assertSame([1000, 998, 1000, 1500], $snapshots);
    }

    public function test_balance_after_is_floored_at_zero_on_negative_balance(): void
    {
        // Reserve more than the user has — balance_after snapshot must not go below 0
        $this->credits->grant($this->user, 1);
        $this->credits->reserve($this->user, 5);

        $entry = CreditLedger::where('type', CreditTransactionType::Reserve)->sole();

        $this->assertSame(0, (int) $entry->balance_after);
    }

    // ─── Full lifecycle sequences ──────────────────────────────────────────────

    public function test_full_happy_path_grant_reserve_confirm(): void
    {
        // User granted credits, reserves for a job, job succeeds
        $this->credits->grant($this->user, 4000, 'Pro — May');
        $this->credits->reserve($this->user, 2);
        $this->credits->confirm($this->user, 2);

        // confirm() doesn't restore — the deduction is permanent
        $this->assertSame(3998, $this->credits->balance($this->user));
        $this->assertSame(2, CreditLedger::count()); // only grant + reserve rows
    }

    public function test_full_failure_path_grant_reserve_refund(): void
    {
        // User granted credits, reserves for a job, job fails — credits restored
        $this->credits->grant($this->user, 4000, 'Pro — May');
        $this->credits->reserve($this->user, 2);
        $this->credits->refund($this->user, 2);

        $this->assertSame(4000, $this->credits->balance($this->user));
        $this->assertSame(3, CreditLedger::count()); // grant + reserve + refund
    }

    public function test_multiple_users_balances_are_isolated(): void
    {
        $other = User::factory()->create();

        $this->credits->grant($this->user, 1000);
        $this->credits->grant($other, 500);

        $this->assertSame(1000, $this->credits->balance($this->user));
        $this->assertSame(500, $this->credits->balance($other));
    }
}
