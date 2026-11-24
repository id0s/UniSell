<?php

namespace Tests\Feature\Api\V1;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_phase_1_tables_exist(): void
    {
        $expectedTables = [
            'users',
            'roles',
            'suppliers',
            'supplier_credentials',
            'supplier_packages',
            'supplier_subscriptions',
            'supplier_products',
            'product_categories',
            'products',
            'product_mappings',
            'product_prices',
            'orders',
            'order_items',
            'payment_transactions',
            'supplier_orders',
            'deliveries',
            'refunds',
            'notifications',
            'webhook_logs',
            'audit_logs',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }
    }

    public function test_admin_can_login_access_dashboard_and_logout(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@unisell.local',
            'password' => 'Admin12345!',
            'device_name' => 'phpunit',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'role'],
                ],
            ])
            ->assertJsonPath('data.user.role', Role::ADMIN);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@unisell.local');

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.status', 'ok');

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();
    }

    public function test_customer_cannot_access_admin_dashboard(): void
    {
        $this->seed(DatabaseSeeder::class);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'customer@unisell.local',
            'password' => 'Customer12345!',
        ]);

        $token = $loginResponse->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_invalid_credentials_return_uniform_error_format(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@unisell.local',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonStructure([
                'error' => ['code', 'message'],
            ])
            ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
    }

    public function test_authenticated_user_can_save_theme_preferences(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/v1/auth/theme', [
                'presetId' => 'emerald',
                'mode' => 'dark',
                'radius' => 'md',
                'density' => 'compact',
            ])
            ->assertOk()
            ->assertJsonPath('data.theme_preferences.presetId', 'emerald');
    }
}
