<?php

namespace Tests\Feature\Api\V1;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\Products\PricingService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductAggregatorAndPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_publish_without_subscription_but_blocked_when_supplier_suspended(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $suspendedSupplier = Supplier::create([
            'name' => 'Suspended Supplier',
            'auth_method' => 'manual',
            'status' => Supplier::STATUS_SUSPENDED,
        ]);

        $suspendedSp = SupplierProduct::create([
            'supplier_id' => $suspendedSupplier->id,
            'external_id' => 'SUSP-01',
            'name' => 'Suspended Product',
            'cost_price' => 30000,
            'stock_status' => 'available',
        ]);

        // Suspended supplier -> blocked (422)
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'supplier_product_id' => $suspendedSp->id,
                'name' => 'Suspended Product Storefront',
                'status' => 'active',
                'pricing_mode' => 'fixed',
                'markup_value' => 10000,
            ])
            ->assertUnprocessable();

        // Active supplier without subscription -> allowed & bulk publish works
        $directSupplier = Supplier::create([
            'name' => 'Digiflazz Direct',
            'auth_method' => 'digiflazz',
            'status' => Supplier::STATUS_ACTIVE,
        ]);

        SupplierProduct::create([
            'supplier_id' => $directSupplier->id,
            'external_id' => 'DF-TEST-01',
            'name' => 'Pulsa 10.000',
            'cost_price' => 10500,
            'stock_status' => 'available',
            'raw' => ['category' => 'Pulsa', 'brand' => 'Telkomsel'],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/suppliers/{$directSupplier->id}/publish-all", [
                'pricing_mode' => 'fixed',
                'markup_value' => 1500,
            ])
            ->assertOk()
            ->assertJsonPath('data.published_count', 1);
    }

    public function test_pricing_modes_net_profit_and_break_even_simulation(): void
    {
        $pricing = new PricingService();

        $this->assertSame(25000, $pricing->calculateSellPrice(20000, 'manual', 0, 25000));
        $this->assertSame(23500, $pricing->calculateSellPrice(20000, 'fixed', 3500));
        $this->assertSame(24000, $pricing->calculateSellPrice(20000, 'percent', 20));

        // laba_bersih = 25000 - 20000 - 500 - 1000 = 3500
        $net = $pricing->calculateNetProfit(25000, 20000, 500, 1000);
        $this->assertSame(3500, $net);

        // Break-even: Rp150.000 / Rp3.500 = ceil(42.85) = 43 transactions
        $sim = $pricing->simulateBreakEven(150000, 3500);
        $this->assertTrue($sim['profitable']);
        $this->assertSame(43, $sim['break_even_transactions']);
    }
}
