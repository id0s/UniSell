<?php

namespace App\Services\Products;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductMapping;
use App\Models\ProductPrice;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\SupplierDriverManager;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductAggregatorService
{
    public function __construct(
        private readonly SupplierDriverManager $driverManager,
        private readonly PricingService $pricingService,
    ) {}

    /**
     * Sync products from a supplier into supplier_products table.
     */
    public function syncFromSupplier(Supplier $supplier, ?int $actorUserId = null): int
    {
        $driver = $this->driverManager->resolve($supplier);
        $items = $driver->syncProducts($supplier);
        $synced = 0;

        foreach ($items as $item) {
            SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'external_id' => $item['external_id'],
                ],
                [
                    'name' => $item['name'],
                    'cost_price' => (int) $item['cost_price'],
                    'stock_status' => $item['stock_status'] ?? 'available',
                    'raw' => $item['raw'] ?? [],
                    'synced_at' => now(),
                ]
            );
            $synced++;
        }

        AuditLog::create([
            'user_id' => $actorUserId,
            'action' => 'supplier.products.synced',
            'subject_type' => Supplier::class,
            'subject_id' => $supplier->id,
            'diff' => ['synced_count' => $synced],
        ]);

        return $synced;
    }

    /**
     * Create a storefront product mapped to a supplier product with pricing.
     *
     * @param  array<string, mixed>  $data
     */
    public function createStorefrontProduct(array $data, ?int $actorUserId = null): Product
    {
        return DB::transaction(function () use ($data, $actorUserId) {
            $supplierProduct = SupplierProduct::with('supplier')->findOrFail($data['supplier_product_id']);
            $desiredStatus = $data['status'] ?? Product::STATUS_DRAFT;

            if ($desiredStatus === Product::STATUS_ACTIVE && ! $supplierProduct->supplier->hasActiveSubscription()) {
                throw new DomainException('Produk dari supplier tanpa langganan aktif tidak dapat dipublikasikan.');
            }

            $slug = $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4));

            $product = Product::create([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'status' => $desiredStatus,
            ]);

            ProductMapping::create([
                'product_id' => $product->id,
                'supplier_product_id' => $supplierProduct->id,
                'priority' => $data['priority'] ?? 1,
                'is_active' => true,
            ]);

            $mode = $data['pricing_mode'] ?? ProductPrice::MODE_FIXED;
            $markupValue = (int) ($data['markup_value'] ?? 0);
            $sellPrice = $this->pricingService->calculateSellPrice(
                costPrice: (int) $supplierProduct->cost_price,
                mode: $mode,
                markupValue: $markupValue,
                manualSellPrice: isset($data['sell_price']) ? (int) $data['sell_price'] : null,
            );

            ProductPrice::create([
                'product_id' => $product->id,
                'mode' => $mode,
                'markup_value' => $markupValue,
                'sell_price' => $sellPrice,
            ]);

            AuditLog::create([
                'user_id' => $actorUserId,
                'action' => 'product.created',
                'subject_type' => Product::class,
                'subject_id' => $product->id,
                'diff' => [
                    'supplier_product_id' => $supplierProduct->id,
                    'status' => $desiredStatus,
                    'sell_price' => $sellPrice,
                ],
            ]);

            return $product->load(['category', 'price', 'mappings.supplierProduct.supplier']);
        });
    }

    public function publishProduct(Product $product, ?int $actorUserId = null): Product
    {
        $product->load(['activeMappings.supplierProduct.supplier']);

        $hasActiveSupplier = $product->activeMappings->contains(function ($mapping) {
            return $mapping->supplierProduct?->supplier?->hasActiveSubscription() === true;
        });

        if (! $hasActiveSupplier) {
            throw new DomainException('Produk dari supplier tanpa langganan aktif tidak dapat dipublikasikan.');
        }

        $product->update(['status' => Product::STATUS_ACTIVE]);

        AuditLog::create([
            'user_id' => $actorUserId,
            'action' => 'product.published',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
            'diff' => ['status' => Product::STATUS_ACTIVE],
        ]);

        return $product->fresh(['category', 'price', 'mappings.supplierProduct.supplier']);
    }

    public function updatePricing(
        Product $product,
        string $mode,
        int $markupValue,
        ?int $manualSellPrice = null,
        ?int $actorUserId = null
    ): ProductPrice {
        $primaryMapping = $product->activeMappings()->with('supplierProduct')->first();
        $costPrice = (int) ($primaryMapping?->supplierProduct?->cost_price ?? 0);

        $sellPrice = $this->pricingService->calculateSellPrice(
            costPrice: $costPrice,
            mode: $mode,
            markupValue: $markupValue,
            manualSellPrice: $manualSellPrice,
        );

        $price = ProductPrice::updateOrCreate(
            ['product_id' => $product->id],
            [
                'mode' => $mode,
                'markup_value' => $markupValue,
                'sell_price' => $sellPrice,
            ]
        );

        AuditLog::create([
            'user_id' => $actorUserId,
            'action' => 'product.pricing.updated',
            'subject_type' => Product::class,
            'subject_id' => $product->id,
            'diff' => [
                'mode' => $mode,
                'markup_value' => $markupValue,
                'sell_price' => $sellPrice,
            ],
        ]);

        return $price;
    }
}
