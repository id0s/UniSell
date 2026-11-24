<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category:id,name,slug', 'price:id,product_id,sell_price'])
            ->where('status', Product::STATUS_ACTIVE);

        if ($categorySlug = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($q = $request->query('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        $products = $query->latest()->get()->map(fn (Product $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'description' => $p->description,
            'status' => $p->status,
            'category' => $p->category,
            'sell_price' => (int) ($p->price?->sell_price ?? 0),
        ]);

        return response()->json(['data' => $products]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = Product::with(['category:id,name,slug', 'price:id,product_id,sell_price'])
            ->where('slug', $slug)
            ->where('status', Product::STATUS_ACTIVE)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'status' => $product->status,
                'category' => $product->category,
                'sell_price' => (int) ($product->price?->sell_price ?? 0),
            ],
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = ProductCategory::withCount([
            'products' => fn ($q) => $q->where('status', Product::STATUS_ACTIVE),
        ])->orderBy('name')->get();

        return response()->json(['data' => $categories]);
    }

    public function search(Request $request): JsonResponse
    {
        return $this->index($request);
    }
}
