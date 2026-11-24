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

class DigiflazzDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_digiflazz_balance_pricelist_sync_and_testing_transaction(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Digiflazz H2H',
            'website' => 'https://digiflazz.com',
            'docs_url' => 'https://developer.digiflazz.com/api/',
            'auth_method' => 'digiflazz',
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        SupplierCredential::create([
            'supplier_id' => $supplier->id,
            'payload' => [
                'username' => 'unisell_dev',
                'api_key' => 'dev-key-12345',
                'sandbox' => true,
            ],
        ]);

        Http::fake([
            'https://api.digiflazz.com/v1/cek-saldo' => Http::response([
                'data' => ['deposit' => 250000],
            ], 200),
            'https://api.digiflazz.com/v1/price-list' => Http::response([
                'data' => [
                    [
                        'buyer_sku_code' => 'xld25',
                        'product_name' => 'XL Data 25GB',
                        'price' => 24800,
                        'buyer_product_status' => true,
                        'seller_product_status' => true,
                    ],
                    [
                        'buyer_sku_code' => 'ml86',
                        'product_name' => 'Mobile Legends 86 Diamonds',
                        'price' => 19100,
                        'buyer_product_status' => true,
                        'seller_product_status' => true,
                    ],
                ],
            ], 200),
            'https://api.digiflazz.com/v1/transaction' => Http::response([
                'data' => [
                    'ref_id' => 'US-DF-001',
                    'status' => 'Sukses',
                    'sn' => 'SN-DIGIFLAZZ-778899',
                    'message' => 'Transaksi Sukses',
                ],
            ], 200),
        ]);

        // 1. Health & Balance
        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/suppliers/{$supplier->id}/health")
            ->assertOk()
            ->assertJsonPath('data.health_status', Supplier::HEALTH_HEALTHY)
            ->assertJsonPath('data.balance', 250000);

        // 2. Sync Products
        $synced = app(ProductAggregatorService::class)->syncFromSupplier($supplier, $admin->id);
        $this->assertSame(2, $synced);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'external_id' => 'ml86',
            'cost_price' => 19100,
        ]);

        // 3. Transaction in testing mode
        $driver = app(SupplierDriverManager::class)->resolve($supplier);
        $result = $driver->placeOrder($supplier, [
            'order_no' => 'US-DF-001',
            'external_product_id' => 'xld25',
            'qty' => 1,
            'customer_input' => ['target_account' => '087800001230'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('SN-DIGIFLAZZ-778899', $result['delivery_payload']);

        Http::assertSent(function ($request) {
            if ($request->url() !== 'https://api.digiflazz.com/v1/transaction') {
                return false;
            }
            $expectedSign = md5('unisell_dev'.'dev-key-12345'.'US-DF-001');

            return $request['sign'] === $expectedSign && $request['testing'] === true;
        });
    }
}
