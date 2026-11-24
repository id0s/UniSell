<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Products\PricingService;
use App\Services\Products\ProductAggregatorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductAggregatorService $aggregatorService,
        private readonly PricingService $pricingService,
    ) {}

    public function index(): JsonResponse
    {
        $products = Product::with([
            'category:id,name,slug',
            'price',
            'mappings.supplierProduct.supplier:id,name,status',
        ])->latest()->get();

        return response()->json(['data' => $products]);
    }

    public function supplierProducts(Request $request): JsonResponse
    {
        $query = SupplierProduct::with('supplier:id,name,status,auth_method')->latest('synced_at');

        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function syncSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        try {
            $synced = $this->aggregatorService->syncFromSupplier($supplier, $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SYNC_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'supplier_id' => $supplier->id,
                'synced_count' => $synced,
            ],
        ]);
    }

    public function storeManualSupplierProduct(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'external_id' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:200'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'stock_status' => ['sometimes', 'in:available,out_of_stock,discontinued'],
        ]);

        $supplierProduct = SupplierProduct::updateOrCreate(
            [
                'supplier_id' => $supplier->id,
                'external_id' => $validated['external_id'],
            ],
            [
                'name' => $validated['name'],
                'cost_price' => $validated['cost_price'],
                'stock_status' => $validated['stock_status'] ?? 'available',
                'raw' => ['manual' => true],
                'synced_at' => now(),
            ]
        );

        return response()->json(['data' => $supplierProduct], Response::HTTP_CREATED);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_product_id' => ['required', 'integer', 'exists:supplier_products,id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'name' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:220', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active'],
            'pricing_mode' => ['required', 'in:manual,fixed,percent'],
            'markup_value' => ['required', 'integer', 'min:0'],
            'sell_price' => ['nullable', 'integer', 'min:0'],
        ]);

        try {
            $product = $this->aggregatorService->createStorefrontProduct($validated, $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SUPPLIER_SUBSCRIPTION_REQUIRED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $product], Response::HTTP_CREATED);
    }

    public function publish(Request $request, Product $product): JsonResponse
    {
        try {
            $updated = $this->aggregatorService->publishProduct($product, $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'SUPPLIER_SUBSCRIPTION_REQUIRED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $updated]);
    }

    public function updatePricing(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:manual,fixed,percent'],
            'markup_value' => ['required', 'integer', 'min:0'],
            'sell_price' => ['nullable', 'integer', 'min:0'],
        ]);

        $price = $this->aggregatorService->updatePricing(
            product: $product,
            mode: $validated['mode'],
            markupValue: $validated['markup_value'],
            manualSellPrice: $validated['sell_price'] ?? null,
            actorUserId: $request->user()?->id,
        );

        return response()->json(['data' => $price]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:150', 'unique:product_categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
        ]);

        $category = ProductCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? Str::slug($validated['name']),
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return response()->json(['data' => $category], Response::HTTP_CREATED);
    }

    public function simulatePricing(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cost_price' => ['required', 'integer', 'min:0'],
            'mode' => ['required', 'in:manual,fixed,percent'],
            'markup_value' => ['required', 'integer', 'min:0'],
            'sell_price' => ['nullable', 'integer', 'min:0'],
            'gateway_fee' => ['sometimes', 'integer', 'min:0'],
            'subscription_allocation' => ['sometimes', 'integer', 'min:0'],
            'subscription_cost' => ['sometimes', 'integer', 'min:0'],
        ]);

        $sellPrice = $this->pricingService->calculateSellPrice(
            costPrice: $validated['cost_price'],
            mode: $validated['mode'],
            markupValue: $validated['markup_value'],
            manualSellPrice: $validated['sell_price'] ?? null,
        );

        $netProfit = $this->pricingService->calculateNetProfit(
            sellPrice: $sellPrice,
            costPrice: $validated['cost_price'],
            gatewayFee: (int) ($validated['gateway_fee'] ?? 0),
            subscriptionAllocation: (int) ($validated['subscription_allocation'] ?? 0),
        );

        $breakEven = $this->pricingService->simulateBreakEven(
            subscriptionCost: (int) ($validated['subscription_cost'] ?? 0),
            profitPerTx: $netProfit,
        );

        return response()->json([
            'data' => [
                'cost_price' => $validated['cost_price'],
                'sell_price' => $sellPrice,
                'net_profit' => $netProfit,
                'break_even' => $breakEven,
            ],
        ]);
    }
}
