<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tenant = app('current_tenant');

        return response()->json([
            'data' => [
                'plan' => $tenant->plan,
                'status' => $tenant->status,
                'on_trial' => $tenant->onTrial(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'has_subscription' => $tenant->subscribed('default'),
            ],
        ]);
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'plan' => ['required', 'in:starter,business'],
        ]);

        $tenant = app('current_tenant');

        $priceId = config("billing.prices.{$request->plan}");

        $activeEmployeeCount = $tenant->users()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count();

        $checkout = $tenant->newSubscription('default', $priceId)
            ->quantity(max(1, $activeEmployeeCount))
            ->checkout([
                'success_url' => config('app.frontend_url') . '/billing/success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('app.frontend_url') . '/billing/cancel',
            ]);

        return response()->json([
            'data' => [
                'checkout_url' => $checkout->url,
            ],
        ]);
    }
}
