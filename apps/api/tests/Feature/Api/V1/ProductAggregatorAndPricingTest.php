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

    public function test_cannot_publish_product_from_supplier_without_active_subscription(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'Unsubscribed Supplier',
            'auth_method' => 'manual',
            'status' => Supplier::STATUS_INACTIVE,
        ]);

        $supplierProduct = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'external_id' => 'MANUAL-01',
            'name' => 'E-Book Premium',
            'cost_price' => 30000,
            'stock_status' => 'available',
        ]);

        // Attempt direct publish -> must be blocked (422)
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'supplier_product_id' => $supplierProduct->id,
                'name' => 'E-Book Premium Storefront',
                'status' => 'active',
                'pricing_mode' => 'fixed',
                'markup_value' => 10000,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SUPPLIER_SUBSCRIPTION_REQUIRED');

        // Draft creation is allowed, then publish attempt must still be blocked
        $draftResp = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/products', [
                'supplier_product_id' => $supplierProduct->id,
                'name' => 'E-Book Premium Draft',
                'status' => 'draft',
                'pricing_mode' => 'fixed',
                'markup_value' => 10000,
            ])->assertCreated();

        $productId = $draftResp->json('data.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/products/{$productId}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'SUPPLIER_SUBSCRIPTION_REQUIRED');
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
