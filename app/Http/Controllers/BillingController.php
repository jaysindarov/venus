<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Exceptions\IncompletePayment;

class BillingController extends Controller
{
    public function __construct(
        private readonly CreditService $creditService,
    ) {}

    public function plans(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Billing/Plans', [
            'plans' => Plan::active()->get(),
            'currentPlan' => $user->subscription('default')?->stripe_price,
            'onTrial' => $user->onTrial(),
            'subscribed' => $user->subscribed(),
        ]);
    }

    public function subscribe(Request $request): RedirectResponse
    {
        $request->validate([
            'price_id' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $user->newSubscription('default', $request->price_id)
                ->create($request->input('payment_method'));

            return redirect()->route('billing.plans')
                ->with('success', 'Subscription activated successfully.');
        } catch (IncompletePayment $e) {
            return redirect()->route(
                'cashier.payment',
                [$e->payment->id, 'redirect' => route('billing.plans')]
            );
        }
    }

    public function portal(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $user->redirectToBillingPortal(route('billing.plans'));
    }

    public function cancel(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->subscription('default')?->cancel();

        return redirect()->route('billing.plans')
            ->with('success', 'Subscription cancelled. You keep access until the end of the billing period.');
    }
}
