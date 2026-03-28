<?php

namespace App\Http\Controllers\Billing;

use App\Models\Tenant;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class WebhookController extends CashierWebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload): void
    {
        parent::handleCustomerSubscriptionCreated($payload);

        $stripeId = $payload['data']['object']['customer'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            $planId = $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;
            $plan = $this->resolvePlanFromPriceId($planId);

            $tenant->update([
                'status' => 'active',
                'plan' => $plan,
            ]);
        }
    }

    protected function handleCustomerSubscriptionUpdated(array $payload): void
    {
        parent::handleCustomerSubscriptionUpdated($payload);

        $stripeId = $payload['data']['object']['customer'];
        $status = $payload['data']['object']['status'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            if ($status === 'past_due') {
                $tenant->update(['status' => 'past_due']);
            } elseif ($status === 'active') {
                $tenant->update(['status' => 'active']);
            }
        }
    }

    protected function handleCustomerSubscriptionDeleted(array $payload): void
    {
        parent::handleCustomerSubscriptionDeleted($payload);

        $stripeId = $payload['data']['object']['customer'];
        $tenant = Tenant::where('stripe_id', $stripeId)->first();

        if ($tenant) {
            $tenant->update(['status' => 'cancelled']);
        }
    }

    private function resolvePlanFromPriceId(?string $priceId): string
    {
        if ($priceId === config('billing.prices.business')) {
            return 'business';
        }

        return 'starter';
    }
}
