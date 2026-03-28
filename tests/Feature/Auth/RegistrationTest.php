<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tenant_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'Acme Construction',
            'name' => 'John Owner',
            'email' => 'john@acme.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'America/New_York',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'tenant' => ['id', 'name', 'plan', 'status', 'trial_ends_at'],
                    'user' => ['id', 'name', 'email', 'role'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Construction',
            'plan' => 'business',
            'status' => 'trial',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@acme.com',
            'role' => 'admin',
        ]);

        $tenant = Tenant::where('name', 'Acme Construction')->first();
        $this->assertTrue($tenant->trial_ends_at->isFuture());
        $this->assertTrue(abs($tenant->trial_ends_at->diffInDays(now())) >= 13);
    }

    public function test_registration_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['company_name', 'name', 'email', 'password']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        $tenant = Tenant::create([
            'name' => 'Existing Co',
            'timezone' => 'UTC',
            'workweek_start_day' => 1,
            'plan' => 'starter',
            'status' => 'active',
        ]);

        User::withoutGlobalScopes()->create([
            'name' => 'Existing',
            'email' => 'taken@test.com',
            'password' => bcrypt('password'),
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'company_name' => 'New Co',
            'name' => 'New User',
            'email' => 'taken@test.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'UTC',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
