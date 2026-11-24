<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $deliveredOrderIds = Order::where('status', Order::STATUS_DELIVERED)->pluck('id');
        $omzet = (int) Order::whereIn('id', $deliveredOrderIds)->sum('total');
        $modal = (int) DB::table('order_items')
            ->whereIn('order_id', $deliveredOrderIds)
            ->selectRaw('COALESCE(SUM(unit_cost * qty), 0) as total_cost')
            ->value('total_cost');
        $gatewayFee = (int) PaymentTransaction::whereIn('order_id', $deliveredOrderIds)
            ->where('status', PaymentTransaction::STATUS_PAID)
            ->sum('fee');
        $subscriptionCost = (int) DB::table('supplier_subscriptions')
            ->join('supplier_packages', 'supplier_subscriptions.package_id', '=', 'supplier_packages.id')
            ->where('supplier_subscriptions.status', SupplierSubscription::STATUS_ACTIVE)
            ->sum('supplier_packages.price');

        return response()->json([
            'data' => [
                'status' => 'ok',
                'phase' => 7,
                'summary' => [
                    'suppliers_count' => Supplier::count(),
                    'active_suppliers_count' => Supplier::where('status', Supplier::STATUS_ACTIVE)->count(),
                    'products_count' => Product::count(),
                    'active_products_count' => Product::where('status', Product::STATUS_ACTIVE)->count(),
                    'orders_count' => Order::count(),
                    'manual_review_count' => Order::where('status', Order::STATUS_MANUAL_REVIEW)->count(),
                    'revenue_idr' => $omzet,
                    'cost_idr' => $modal,
                    'gateway_fee_idr' => $gatewayFee,
                    'subscription_cost_idr' => $subscriptionCost,
                    'net_profit_idr' => $omzet - $modal - $gatewayFee - $subscriptionCost,
                ],
            ],
        ]);
    }
}
