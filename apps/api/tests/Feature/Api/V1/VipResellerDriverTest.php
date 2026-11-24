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

class VipResellerDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_vip_reseller_profile_sync_and_order(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'VIP-Reseller',
            'website' => 'https://vip-reseller.co.id',
            'auth_method' => 'vip_reseller',
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        SupplierCredential::create([
            'supplier_id' => $supplier->id,
            'payload' => [
                'api_id' => 'vip-id-001',
                'api_key' => 'vip-key-abc',
            ],
        ]);

        Http::fake([
            'https://vip-reseller.co.id/api/profile' => Http::response([
                'result' => true,
                'data' => ['username' => 'unisell', 'balance' => 85000, 'level' => 'Basic'],
            ], 200),
            'https://vip-reseller.co.id/api/game-feature' => Http::sequence()
                ->push([
                    'result' => true,
                    'data' => [
                        ['code' => 'ML86', 'name' => 'MLBB 86 Diamonds', 'price' => ['basic' => 19300], 'status' => 'available'],
                    ],
                ], 200)
                ->push([
                    'result' => true,
                    'data' => ['trxid' => 'VIP-TRX-551', 'status' => 'success', 'note' => 'SN-VIP-999'],
                ], 200),
            'https://vip-reseller.co.id/api/prepaid' => Http::response([
                'result' => true,
                'data' => [
                    ['code' => 'PLN20', 'name' => 'Token PLN 20K', 'price' => ['basic' => 20400], 'status' => 'available'],
                ],
            ], 200),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/admin/suppliers/{$supplier->id}/health")
            ->assertOk()
            ->assertJsonPath('data.health_status', Supplier::HEALTH_HEALTHY)
            ->assertJsonPath('data.balance', 85000);

        $synced = app(ProductAggregatorService::class)->syncFromSupplier($supplier, $admin->id);
        $this->assertSame(2, $synced);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'external_id' => 'game:ML86',
            'cost_price' => 19300,
        ]);

        $driver = app(SupplierDriverManager::class)->resolve($supplier);
        $res = $driver->placeOrder($supplier, [
            'order_no' => 'US-VIP-001',
            'external_product_id' => 'game:ML86',
            'qty' => 1,
            'customer_input' => ['target_account' => '12345678', 'zone_id' => '2044'],
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame('VIP-TRX-551', $res['external_ref']);
        $this->assertStringContainsString('SN-VIP-999', $res['delivery_payload']);
    }
}
