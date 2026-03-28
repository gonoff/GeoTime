<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ]);

        $result = DB::transaction(function () use ($validated) {
            $tenant = Tenant::create([
                'name' => $validated['company_name'],
                'timezone' => $validated['timezone'] ?? 'America/New_York',
                'workweek_start_day' => 1,
                'plan' => 'business',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            $user = User::withoutGlobalScopes()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'tenant_id' => $tenant->id,
                'role' => 'admin',
            ]);

            $token = $user->createToken('api')->plainTextToken;

            return compact('tenant', 'user', 'token');
        });

        return response()->json([
            'data' => [
                'tenant' => [
                    'id' => $result['tenant']->id,
                    'name' => $result['tenant']->name,
                    'plan' => $result['tenant']->plan,
                    'status' => $result['tenant']->status,
                    'trial_ends_at' => $result['tenant']->trial_ends_at->toIso8601String(),
                ],
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'role' => $result['user']->role,
                ],
                'token' => $result['token'],
            ],
        ], 201);
    }
}
