<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\SupplierOrder;
use App\Services\Orders\OrderStateMachine;
use App\Services\Suppliers\SupplierDriverManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessSupplierOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = SupplierOrder::MAX_ATTEMPTS;

    /**
     * Exponential backoff in seconds (agents.md Section 6).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly int $supplierOrderId,
    ) {}

    public function handle(
        SupplierDriverManager $driverManager,
        OrderStateMachine $stateMachine,
    ): void {
        $supplierOrder = SupplierOrder::with([
            'supplier.credential',
            'orderItem.order',
            'orderItem.product.activeMappings.supplierProduct',
        ])->find($this->supplierOrderId);

        if (! $supplierOrder || $supplierOrder->status === SupplierOrder::STATUS_SUCCESS) {
            return;
        }

        $order = $supplierOrder->orderItem->order;
        $supplier = $supplierOrder->supplier;
        $driver = $driverManager->resolve($supplier);

        $supplierOrder->update([
            'status' => SupplierOrder::STATUS_SENT,
            'attempts' => $supplierOrder->attempts + 1,
        ]);

        $mapping = $supplierOrder->orderItem->product?->activeMappings->first();

        $result = $driver->placeOrder($supplier, [
            'order_no' => $order->order_no,
            'external_product_id' => $mapping?->supplierProduct?->external_id,
            'qty' => $supplierOrder->orderItem->qty,
            'customer_input' => $order->customer_input ?? [],
        ]);

        if (($result['success'] ?? false) === true) {
            DB::transaction(function () use ($supplierOrder, $order, $result, $stateMachine) {
                $supplierOrder->update([
                    'status' => SupplierOrder::STATUS_SUCCESS,
                    'external_ref' => $result['external_ref'] ?? null,
                    'last_error' => null,
                ]);

                Delivery::create([
                    'order_id' => $order->id,
                    'type' => $result['delivery_type'] ?? Delivery::TYPE_VOUCHER,
                    'payload' => $result['delivery_payload'] ?? 'Delivered',
                    'delivered_at' => now(),
                ]);

                // Invariant #6: Order only becomes delivered when all supplier orders confirm success
                $hasPendingOrFailed = SupplierOrder::whereHas('orderItem', fn ($q) => $q->where('order_id', $order->id))
                    ->where('status', '!=', SupplierOrder::STATUS_SUCCESS)
                    ->exists();

                if (! $hasPendingOrFailed) {
                    $stateMachine->transition($order, Order::STATUS_DELIVERED);
                }
            });

            return;
        }

        // Failure path: retry up to MAX_ATTEMPTS (3), then transition to manual_review
        $errorMessage = $result['error'] ?? 'Supplier order failed.';

        if ($supplierOrder->attempts >= SupplierOrder::MAX_ATTEMPTS) {
            DB::transaction(function () use ($supplierOrder, $order, $errorMessage, $stateMachine) {
                $supplierOrder->update([
                    'status' => SupplierOrder::STATUS_MANUAL_REVIEW,
                    'last_error' => $errorMessage,
                ]);

                $stateMachine->transition($order, Order::STATUS_MANUAL_REVIEW);
            });

            return;
        }

        DB::transaction(function () use ($supplierOrder, $order, $errorMessage, $stateMachine) {
            $supplierOrder->update([
                'status' => SupplierOrder::STATUS_FAILED,
                'last_error' => $errorMessage,
            ]);

            if ($stateMachine->canTransition($order->status, Order::STATUS_FAILED_RETRY)) {
                $stateMachine->transition($order, Order::STATUS_FAILED_RETRY);
            }
        });

        // Release with exponential backoff when running on async queue
        if ($this->job !== null) {
            $delay = $this->backoff()[$supplierOrder->attempts - 1] ?? 60;
            $this->release($delay);
        }
    }
}
