<?php

namespace Tests\Feature\Api\V1;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Products\ProductAggregatorService;
use App\Services\Suppliers\SupplierSubscriptionService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_credentials_are_encrypted_in_db_and_never_leak_in_api(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $secretKey = 'sk_live_super_secret_987654321';

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/suppliers', [
                'name' => 'DigiFlazz Mock',
                'website' => 'https://supplier.example.com',
                'auth_method' => 'api_key',
                'credentials' => [
                    'api_key' => 'pub_key_123',
                    'api_secret' => $secretKey,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.has_credentials', true);

        // Secret must NEVER appear anywhere in API response body
        $this->assertStringNotContainsString($secretKey, $response->getContent());

        // Verify DB column is encrypted (raw value does not contain plaintext secret)
        $rawPayload = DB::table('supplier_credentials')->value('payload');
        $this->assertNotNull($rawPayload);
        $this->assertStringNotContainsString($secretKey, $rawPayload);

        // Verify GET /suppliers also never leaks credentials
        $listResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/suppliers');
        $listResponse->assertOk();
        $this->assertStringNotContainsString($secretKey, $listResponse->getContent());
    }

    public function test_subscription_activation_and_cancellation_locks_and_unlocks_products(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Supplier Lock Test',
            'auth_method' => 'api_key',
            'status' => Supplier::STATUS_INACTIVE,
        ]);

        $pkgResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/suppliers/{$supplier->id}/packages", [
                'name' => 'Pro 30 Hari',
                'price' => 150000,
                'period_days' => 30,
                'product_limit' => 100,
            ])->assertCreated();

        $packageId = $pkgResponse->json('data.id');

        // Activate subscription
        $subResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/subscribe', [
                'supplier_id' => $supplier->id,
                'package_id' => $packageId,
            ])->assertCreated();

        $subscriptionId = $subResponse->json('data.id');

        // Sync products & publish one
        $aggregator = app(ProductAggregatorService::class);
        $aggregator->syncFromSupplier($supplier, $admin->id);
        $supplierProduct = $supplier->supplierProducts()->firstOrFail();

        $product = $aggregator->createStorefrontProduct([
            'supplier_product_id' => $supplierProduct->id,
            'name' => 'ML 86 Diamonds',
            'status' => Product::STATUS_ACTIVE,
            'pricing_mode' => 'fixed',
            'markup_value' => 3000,
        ], $admin->id);

        $this->assertSame(Product::STATUS_ACTIVE, $product->status);

        // Cancel subscription -> product MUST be locked (not deleted)
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/cancel', [
                'subscription_id' => $subscriptionId,
            ])->assertOk();

        $this->assertSame(Product::STATUS_LOCKED, $product->fresh()->status);

        // Renew subscription -> product MUST be unlocked back to active
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/subscriptions/renew', [
                'subscription_id' => $subscriptionId,
            ])->assertOk();

        $this->assertSame(Product::STATUS_ACTIVE, $product->fresh()->status);

        // Expire overdue subscription -> locks again
        $supplier->subscriptions()->update(['ends_at' => now()->subDay()]);
        app(SupplierSubscriptionService::class)->expireOverdueSubscriptions();
        $this->assertSame(Product::STATUS_LOCKED, $product->fresh()->status);
    }
}
