<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\Response;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    /**
     * invoice.paid — grant monthly credits to the subscriber.
     * Fired on new subscriptions and every renewal.
     */
    protected function handleInvoicePaid(array $payload): Response
    {
        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;

        if (! $stripeCustomerId) {
            return $this->successMethod();
        }

        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if (! $user) {
            return $this->successMethod();
        }

        $priceId = $payload['data']['object']['lines']['data'][0]['price']['id'] ?? null;

        $plan = Plan::where('stripe_monthly_id', $priceId)
            ->orWhere('stripe_yearly_id', $priceId)
            ->first();

        if ($plan) {
            $this->creditService->grant(
                $user,
                $plan->monthly_credits,
                "Monthly credit grant — {$plan->name} plan",
            );
        }

        return $this->successMethod();
    }

    /**
     * customer.subscription.deleted — subscription cancelled or expired.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        // Cashier's parent method handles marking the subscription as cancelled.
        // No credit deduction needed — credits already spent are non-refundable.
        return parent::handleCustomerSubscriptionDeleted($payload);
    }

    /**
     * customer.subscription.updated — plan change (upgrade/downgrade).
     * On upgrade, grant the credit difference immediately.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $result = parent::handleCustomerSubscriptionUpdated($payload);

        $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
        $user = $stripeCustomerId
            ? User::where('stripe_id', $stripeCustomerId)->first()
            : null;

        if (! $user) {
            return $result;
        }

        $previousPriceId = $payload['data']['previous_attributes']['items']['data'][0]['price']['id'] ?? null;
        $newPriceId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;

        if (! $previousPriceId || ! $newPriceId || $previousPriceId === $newPriceId) {
            return $result;
        }

        $previousPlan = Plan::where('stripe_monthly_id', $previousPriceId)
            ->orWhere('stripe_yearly_id', $previousPriceId)
            ->first();

        $newPlan = Plan::where('stripe_monthly_id', $newPriceId)
            ->orWhere('stripe_yearly_id', $newPriceId)
            ->first();

        // Grant the credit difference on upgrade only
        if ($previousPlan && $newPlan && $newPlan->monthly_credits > $previousPlan->monthly_credits) {
            $creditDiff = $newPlan->monthly_credits - $previousPlan->monthly_credits;
            $this->creditService->grant(
                $user,
                $creditDiff,
                "Upgrade credit top-up — {$previousPlan->name} → {$newPlan->name}",
            );
        }

        return $result;
    }
}
