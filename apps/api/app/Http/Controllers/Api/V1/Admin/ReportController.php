<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(): JsonResponse
    {
        // 1. Customer Revenue (only delivered orders)
        $deliveredOrderIds = Order::where('status', Order::STATUS_DELIVERED)->pluck('id');

        $omzet = (int) Order::whereIn('id', $deliveredOrderIds)->sum('total');

        $modal = (int) DB::table('order_items')
            ->whereIn('order_id', $deliveredOrderIds)
            ->selectRaw('COALESCE(SUM(unit_cost * qty), 0) as total_cost')
            ->value('total_cost');

        $biayaGateway = (int) PaymentTransaction::whereIn('order_id', $deliveredOrderIds)
            ->where('status', PaymentTransaction::STATUS_PAID)
            ->sum('fee');

        // 2. Supplier subscription costs (separated from customer payments per Invariant #10)
        $biayaLangganan = (int) DB::table('supplier_subscriptions')
            ->join('supplier_packages', 'supplier_subscriptions.package_id', '=', 'supplier_packages.id')
            ->where('supplier_subscriptions.status', SupplierSubscription::STATUS_ACTIVE)
            ->sum('supplier_packages.price');

        // Formula: laba_bersih = harga_jual - harga_modal - biaya_gateway - alokasi_langganan
        $labaBersih = $omzet - $modal - $biayaGateway - $biayaLangganan;

        // 3. Top products
        $topProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.status', Order::STATUS_DELIVERED)
            ->groupBy('products.id', 'products.name', 'products.slug')
            ->selectRaw('products.id, products.name, products.slug, SUM(order_items.qty) as total_qty, SUM(order_items.unit_price * order_items.qty) as revenue_idr, SUM((order_items.unit_price - order_items.unit_cost) * order_items.qty) as gross_profit_idr')
            ->orderByDesc('revenue_idr')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'slug' => $row->slug,
                'total_qty' => (int) $row->total_qty,
                'revenue_idr' => (int) $row->revenue_idr,
                'gross_profit_idr' => (int) $row->gross_profit_idr,
            ]);

        // 4. Supplier evaluation ("layak dipertahankan?")
        $suppliers = Supplier::with(['activeSubscription.package'])->get();
        $supplierEvaluations = $suppliers->map(function (Supplier $supplier) {
            $totalSo = SupplierOrder::where('supplier_id', $supplier->id)->count();
            $successSo = SupplierOrder::where('supplier_id', $supplier->id)
                ->where('status', SupplierOrder::STATUS_SUCCESS)
                ->count();
            $failedSo = SupplierOrder::where('supplier_id', $supplier->id)
                ->whereIn('status', [SupplierOrder::STATUS_FAILED, SupplierOrder::STATUS_MANUAL_REVIEW])
                ->count();

            $failRate = $totalSo > 0 ? round(($failedSo / $totalSo) * 100, 2) : 0.0;

            $grossProfit = (int) DB::table('supplier_orders')
                ->join('order_items', 'supplier_orders.order_item_id', '=', 'order_items.id')
                ->where('supplier_orders.supplier_id', $supplier->id)
                ->where('supplier_orders.status', SupplierOrder::STATUS_SUCCESS)
                ->selectRaw('COALESCE(SUM((order_items.unit_price - order_items.unit_cost) * order_items.qty), 0) as gp')
                ->value('gp');

            $subCost = (int) ($supplier->activeSubscription?->package?->price ?? 0);
            $netContribution = $grossProfit - $subCost;

            $worthKeeping = $failRate <= 15.0 && ($netContribution >= 0 || $totalSo === 0);
            $verdict = match (true) {
                $totalSo === 0 => 'Belum Ada Transaksi',
                $failRate > 15.0 => 'Evaluasi — Tingkat Gagal Tinggi',
                $netContribution < 0 => 'Evaluasi — Belum Balik Modal Langganan',
                default => 'Layak Dipertahankan',
            };

            return [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'health_status' => $supplier->health_status,
                'total_orders' => $totalSo,
                'success_orders' => $successSo,
                'failed_orders' => $failedSo,
                'failure_rate_percent' => $failRate,
                'subscription_cost_idr' => $subCost,
                'gross_profit_idr' => $grossProfit,
                'net_contribution_idr' => $netContribution,
                'worth_keeping' => $worthKeeping,
                'verdict' => $verdict,
            ];
        })->sortByDesc('net_contribution_idr')->values();

        $totalOrders = Order::count();
        $failedOrReviewOrders = Order::whereIn('status', [
            Order::STATUS_FAILED_RETRY,
            Order::STATUS_MANUAL_REVIEW,
            Order::STATUS_REFUNDED,
        ])->count();

        return response()->json([
            'data' => [
                'financial' => [
                    'omzet_idr' => $omzet,
                    'modal_idr' => $modal,
                    'biaya_gateway_idr' => $biayaGateway,
                    'biaya_langganan_idr' => $biayaLangganan,
                    'laba_bersih_idr' => $labaBersih,
                ],
                'operations' => [
                    'total_orders' => $totalOrders,
                    'delivered_orders' => $deliveredOrderIds->count(),
                    'manual_review_orders' => Order::where('status', Order::STATUS_MANUAL_REVIEW)->count(),
                    'failure_rate_percent' => $totalOrders > 0 ? round(($failedOrReviewOrders / $totalOrders) * 100, 2) : 0.0,
                ],
                'top_products' => $topProducts,
                'supplier_evaluations' => $supplierEvaluations,
            ],
        ]);
    }
}
