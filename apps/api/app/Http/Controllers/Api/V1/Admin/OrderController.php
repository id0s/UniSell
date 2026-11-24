<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSupplierOrderJob;
use App\Models\AuditLog;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Services\Orders\OrderStateMachine;
use App\Services\Orders\RefundService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
        private readonly RefundService $refundService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Order::with([
            'items.product:id,name,slug',
            'items.supplierOrders.supplier:id,name',
            'latestPayment',
            'refunds',
        ])->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json(['data' => $query->limit(100)->get()]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'items.product',
            'items.supplierOrders.supplier:id,name',
            'payments',
            'deliveries',
            'refunds',
        ]);

        return response()->json(['data' => $order]);
    }

    public function retry(Request $request, Order $order): JsonResponse
    {
        $supplierOrders = SupplierOrder::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
            ->whereIn('status', [SupplierOrder::STATUS_FAILED, SupplierOrder::STATUS_MANUAL_REVIEW])
            ->get();

        if ($supplierOrders->isEmpty()) {
            return response()->json([
                'error' => [
                    'code' => 'NOTHING_TO_RETRY',
                    'message' => 'Tidak ada supplier order yang perlu di-retry.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->stateMachine->canTransition($order->status, Order::STATUS_PROCESSING)) {
            $this->stateMachine->transition($order, Order::STATUS_PROCESSING);
        }

        foreach ($supplierOrders as $so) {
            $so->update([
                'status' => SupplierOrder::STATUS_QUEUED,
                'attempts' => 0,
            ]);
            ProcessSupplierOrderJob::dispatch($so->id);
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'order.retried',
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'diff' => ['order_no' => $order->order_no],
        ]);

        return response()->json(['data' => $order->fresh()]);
    }

    public function manualDeliver(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:code,file,voucher'],
            'payload' => ['required', 'string'],
        ]);

        if (! $this->stateMachine->canTransition($order->status, Order::STATUS_DELIVERED)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_STATE',
                    'message' => "Order status [{$order->status}] cannot be manually delivered.",
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::transaction(function () use ($order, $validated, $request) {
            Delivery::create([
                'order_id' => $order->id,
                'type' => $validated['type'],
                'payload' => $validated['payload'],
                'delivered_at' => now(),
            ]);

            SupplierOrder::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
                ->where('status', '!=', SupplierOrder::STATUS_SUCCESS)
                ->update(['status' => SupplierOrder::STATUS_SUCCESS]);

            $this->stateMachine->transition($order, Order::STATUS_DELIVERED);

            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => 'order.manual_delivered',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'diff' => ['order_no' => $order->order_no, 'type' => $validated['type']],
            ]);
        });

        return response()->json(['data' => $order->fresh(['deliveries'])]);
    }

    public function refund(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $refund = $this->refundService->refundOrder($order, $validated['reason'], $request->user()?->id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'REFUND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => $refund]);
    }
}
