# Subscription & Billing

## Overview

Billing is handled by **Stripe** via **Laravel Cashier**. The platform uses subscription-based credit grants — users subscribe to a plan that grants credits at the start of each billing cycle.

---

## Stripe Setup

### Required Stripe Products & Prices

Create in Stripe Dashboard (or via Stripe CLI for local dev):

```
Product: VisionaryAI Basic
  Price: price_basic_monthly  → $9.00/month
  Price: price_basic_yearly   → $86.40/year ($7.20/mo)

Product: VisionaryAI Pro
  Price: price_pro_monthly    → $29.00/month
  Price: price_pro_yearly     → $278.40/year ($23.20/mo)

Product: VisionaryAI Creator
  Price: price_creator_monthly → $79.00/month
  Price: price_creator_yearly  → $758.40/year ($63.20/mo)
```

### Credit Top-up Products
```
Product: Credit Pack 500     → one-time $4.99
Product: Credit Pack 2000    → one-time $17.99
Product: Credit Pack 5000    → one-time $39.99
```

### Required Webhook Events (Stripe Dashboard → Webhooks)
```
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
invoice.paid
invoice.payment_failed
payment_intent.succeeded       # for top-ups
```

---

## Cashier Configuration

```php
// config/cashier.php (key settings)
'key'      => env('STRIPE_KEY'),
'secret'   => env('STRIPE_SECRET'),
'webhook'  => ['secret' => env('STRIPE_WEBHOOK_SECRET'), 'tolerance' => 300],
'currency' => 'usd',
'model'    => App\Models\User::class,
```

Add to `User` model:
```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

---

## Subscription Service

```php
// app/Services/SubscriptionService.php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\User;
use Laravel\Cashier\Exceptions\IncompletePayment;

class SubscriptionService
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function subscribe(User $user, string $planSlug, string $paymentMethodId, string $period = 'monthly'): void
    {
        $plan = Plan::where('slug', $planSlug)->firstOrFail();
        $priceId = $period === 'yearly' ? $plan->stripe_yearly_id : $plan->stripe_monthly_id;

        $user->createOrGetStripeCustomer();
        $user->updateDefaultPaymentMethod($paymentMethodId);

        try {
            $user->newSubscription('default', $priceId)
                ->trialDays($plan->trial_days ?? 0)
                ->create($paymentMethodId);
        } catch (IncompletePayment $e) {
            // Payment requires additional action (3DS)
            throw $e;
        }
    }

    public function changePlan(User $user, string $newPlanSlug, string $period = 'monthly'): void
    {
        $plan = Plan::where('slug', $newPlanSlug)->firstOrFail();
        $priceId = $period === 'yearly' ? $plan->stripe_yearly_id : $plan->stripe_monthly_id;

        $user->subscription('default')->swap($priceId);
    }

    public function cancel(User $user): void
    {
        $user->subscription('default')->cancel(); // cancels at period end
    }

    public function resume(User $user): void
    {
        $user->subscription('default')->resume();
    }

    public function grantCreditsForPlan(User $user): void
    {
        $planSlug = $this->getCurrentPlanSlug($user);
        $plan = Plan::where('slug', $planSlug)->first();

        if ($plan) {
            $this->creditService->grant(
                $user,
                $plan->monthly_credits,
                "Monthly {$plan->name} credits"
            );
        }
    }

    public function getCurrentPlanSlug(User $user): string
    {
        if (!$user->subscribed('default')) {
            return 'free';
        }

        return Plan::whereIn('stripe_monthly_id', [$user->subscription('default')->stripe_price])
            ->orWhereIn('stripe_yearly_id', [$user->subscription('default')->stripe_price])
            ->value('slug') ?? 'free';
    }
}
```

---

## Webhook Handler

```php
// app/Http/Controllers/WebhookController.php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionService;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class WebhookController extends CashierWebhookController
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

    /**
     * Handle subscription renewal — grant monthly credits.
     */
    public function handleInvoicePaid(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        $stripeCustomerId = $payload['data']['object']['customer'];
        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if ($user && $payload['data']['object']['billing_reason'] === 'subscription_cycle') {
            $this->subscriptionService->grantCreditsForPlan($user);
        }

        return $this->successMethod();
    }

    /**
     * Handle failed payment — send notification but keep access during grace period.
     */
    public function handleInvoicePaymentFailed(array $payload): \Symfony\Component\HttpFoundation\Response
    {
        $stripeCustomerId = $payload['data']['object']['customer'];
        $user = User::where('stripe_id', $stripeCustomerId)->first();

        if ($user) {
            // Notify user their payment failed
            $user->notify(new \App\Notifications\PaymentFailedNotification());
        }

        return $this->successMethod();
    }
}
```

---

## Credit Top-up Flow

Top-ups are one-time Stripe Payment Intents (not subscriptions):

```php
// app/Services/CreditTopupService.php

public function createPaymentIntent(User $user, int $creditPack): array
{
    $packs = [
        500  => 499,   // $4.99
        2000 => 1799,  // $17.99
        5000 => 3999,  // $39.99
    ];

    $amount = $packs[$creditPack] ?? throw new \InvalidArgumentException('Invalid pack size');

    $intent = \Stripe\PaymentIntent::create([
        'amount'   => $amount,
        'currency' => 'usd',
        'customer' => $user->stripe_id,
        'metadata' => ['user_id' => $user->id, 'credits' => $creditPack],
    ]);

    return ['client_secret' => $intent->client_secret, 'credits' => $creditPack];
}

public function handleSuccess(array $paymentIntentPayload): void
{
    $userId  = $paymentIntentPayload['metadata']['user_id'];
    $credits = (int) $paymentIntentPayload['metadata']['credits'];
    $user    = User::findOrFail($userId);

    $this->creditService->topup($user, $credits, 'Credit top-up purchase');
}
```

---

## Frontend Pricing Page

Key states to handle in `pages/Billing/Plans.vue`:
1. **Not subscribed** → Show all plans with CTA
2. **On current plan** → Highlight active plan, show "Current Plan" badge
3. **Upgrading** → Show price difference, prorate note
4. **Downgrading** → Warn about credit reduction, effective at next billing cycle
5. **Cancelled (grace period)** → Show "Reactivate" option with expiry date

---

## Free Tier

- Users on the free tier get 50 credits on registration (one-time welcome grant)
- No monthly renewal — credits do not reset for free users
- Limited model access (only 512×512 resolutions)
- No API access
- To "convert" free user: they subscribe via Stripe checkout

---

## Revenue Recognition Notes

- Credits are granted on `invoice.paid` webhook, not on subscription creation
- Trials: grant credits immediately on trial start, but do not charge until trial ends
- Yearly plans: grant 12 months of credits upfront in a single large grant
- Refund policy: prorated via Stripe, but credits already used are non-refundable (document in Terms)
