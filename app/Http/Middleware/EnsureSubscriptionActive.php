<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip subscription checks for platform admin routes
        if ($request->is('admin/*')) {
            return $next($request);
        }

        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        if (! $tenant) {
            return $next($request);
        }

        if ($tenant->status === 'suspended') {
            return response()->json([
                'message' => 'Account suspended. Please contact support.',
            ], 403);
        }

        if ($tenant->status === 'cancelled') {
            return response()->json([
                'message' => 'Subscription cancelled. Please resubscribe to continue.',
            ], 403);
        }

        if ($tenant->status === 'active' || $tenant->onTrial()) {
            return $next($request);
        }

        if ($tenant->status === 'past_due' || ($tenant->status === 'trial' && ! $tenant->onTrial())) {
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return $next($request);
            }

            return response()->json([
                'message' => 'Subscription inactive. Read-only mode. Please update your payment method.',
                'read_only' => true,
            ], 402);
        }

        return $next($request);
    }
}
