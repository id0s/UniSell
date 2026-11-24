<?php

namespace Tests\Feature\Api\V1;

use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\User;
use App\Services\Products\ProductAggregatorService;
use App\Services\Suppliers\SupplierDriverManager;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SekaliPayDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_sekalipay_sync_products_health_balance_and_order_via_http(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'SekaliPay Reseller',
            'website' => 'https://sekalipay.com',
            'docs_url' => 'https://sekalipay.com/api',
            'auth_method' => 'sekalipay',
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        SupplierCredential::create([
            'supplier_id' => $supplier->id,
            'payload' => [
                'api_key' => 'sp_live_test_key_123',
                'api_secret' => 'sp_secret_xyz',
                'base_url' => 'https://sekalipay.com/api',
                'sandbox' => true,
            ],
        ]);

        Http::fake([
            'https://sekalipay.com/api/v1/balance' => Http::response([
                'status' => true,
                'data' => ['balance' => 1250000],
            ], 200),
            'https://sekalipay.com/api/v1/item' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'id' => 'SP-ML-86',
                        'name' => 'SekaliPay ML 86 Diamonds',
                        'price' => 19200,
                        'is_available' => true,
                    ],
                    [
                        'id' => 'SP-PLN-50',
                        'name' => 'Token PLN 50.000',
                        'price' => 50500,
                        'is_available' => true,
                    ],
                ],
            ], 200),
            'https://sekalipay.com/api/v1/order/sandbox' => Http::response([
                'status' => true,
                'data' => [
                    'trx_id' => 'SP-TRX-9988',
                    'status' => 'success',
                    'sn' => 'SN-1234-5678',
                ],
            ], 200),
        ]);

        // 1. Health & Balance
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/suppliers/{$supplier->id}/health")
            ->assertOk()
            ->assertJsonPath('data.health_status', Supplier::HEALTH_HEALTHY)
            ->assertJsonPath('data.balance', 1250000);

        // 2. Sync Products
        $synced = app(ProductAggregatorService::class)->syncFromSupplier($supplier, $admin->id);
        $this->assertSame(2, $synced);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'external_id' => 'SP-ML-86',
            'cost_price' => 19200,
        ]);

        // 3. Place Order (sandbox endpoint)
        $driver = app(SupplierDriverManager::class)->resolve($supplier);
        $orderResult = $driver->placeOrder($supplier, [
            'order_no' => 'US-TEST-001',
            'external_product_id' => 'SP-ML-86',
            'qty' => 1,
            'customer_input' => ['target_account' => '12345678'],
        ]);

        $this->assertTrue($orderResult['success']);
        $this->assertSame('SP-TRX-9988', $orderResult['external_ref']);
        $this->assertStringContainsString('SN-1234-5678', $orderResult['delivery_payload']);
    }
}
