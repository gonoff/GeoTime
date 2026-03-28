<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function createTenantAndUser(): array
    {
        $tenant = Tenant::create([
            'name' => 'Test Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        $user = User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => 'Password123!',
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }

    public function test_user_can_login_via_api(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'role'],
                    'tenant' => ['id', 'name', 'plan'],
                    'token',
                ],
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(422);
    }

    public function test_authenticated_request_resolves_tenant(): void
    {
        [$tenant, $user] = $this->createTenantAndUser();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'user' => ['email' => 'test@test.com'],
                    'tenant' => ['name' => 'Test Co'],
                ],
            ]);
    }
}
