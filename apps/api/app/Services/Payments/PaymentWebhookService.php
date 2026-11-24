<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Jobs\ProcessSupplierOrderJob;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\SupplierOrder;
use App\Models\WebhookLog;
use App\Services\Orders\OrderStateMachine;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PaymentWebhookService
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly OrderStateMachine $stateMachine,
    ) {}

    /**
     * Process an incoming payment webhook with strict signature check and idempotency lock.
     *
     * @param  array<string, mixed>  $payload
     * @return array{duplicate: bool, processed: bool, order_no?: string, supplier_orders_created?: int}
     */
    public function handleWebhook(string $rawBody, array $payload, ?string $signature): array
    {
        $signatureValid = $this->paymentGateway->verifyWebhookSignature($rawBody, $signature);
        $parsed = $this->paymentGateway->parseWebhook($payload);

        if ($parsed['event_id'] === '') {
            throw new DomainException('Missing webhook event_id.');
        }

        if (! $signatureValid) {
            WebhookLog::firstOrCreate(
                ['event_id' => $parsed['event_id'].'-invalid-'.now()->timestamp],
                [
                    'source' => $this->paymentGateway->gatewayName(),
                    'payload' => $payload,
                    'signature_valid' => false,
                ]
            );

            throw new DomainException('Invalid webhook signature.');
        }

        // Idempotency gate #1: unique event_id insert in webhook_logs
        try {
            $webhookLog = WebhookLog::create([
                'source' => $this->paymentGateway->gatewayName(),
                'event_id' => $parsed['event_id'],
                'payload' => $payload,
                'signature_valid' => true,
            ]);
        } catch (UniqueConstraintViolationException) {
            return [
                'duplicate' => true,
                'processed' => false,
            ];
        }

        $supplierOrderIdsToDispatch = [];
        $orderNo = null;

        DB::transaction(function () use ($parsed, $webhookLog, &$supplierOrderIdsToDispatch, &$orderNo) {
            $payment = PaymentTransaction::where('ref', $parsed['payment_ref'])
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                $webhookLog->update(['processed_at' => now()]);

                return;
            }

            $order = Order::with(['items.product.activeMappings.supplierProduct'])
                ->where('id', $payment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $orderNo = $order->order_no;

            // Idempotency gate #2: if payment is already paid, never create duplicate supplier orders
            if ($payment->status === PaymentTransaction::STATUS_PAID) {
                $webhookLog->update(['processed_at' => now()]);

                return;
            }

            if ($parsed['status'] === 'paid') {
                $payment->update([
                    'status' => PaymentTransaction::STATUS_PAID,
                    'fee' => $parsed['fee'] > 0 ? $parsed['fee'] : $payment->fee,
                    'paid_at' => now(),
                ]);

                $this->stateMachine->transition($order, Order::STATUS_PAID);
                $this->stateMachine->transition($order, Order::STATUS_PROCESSING);

                foreach ($order->items as $item) {
                    // Guard against duplicate supplier order for the same order_item
                    if ($item->supplierOrders()->exists()) {
                        continue;
                    }

                    $mapping = $item->product?->activeMappings->first();
                    $supplierId = $mapping?->supplierProduct?->supplier_id;

                    if (! $supplierId) {
                        continue;
                    }

                    $supplierOrder = SupplierOrder::create([
                        'order_item_id' => $item->id,
                        'supplier_id' => $supplierId,
                        'status' => SupplierOrder::STATUS_QUEUED,
                        'attempts' => 0,
                    ]);

                    $supplierOrderIdsToDispatch[] = $supplierOrder->id;
                }
            } elseif ($parsed['status'] === 'expired') {
                $payment->update(['status' => PaymentTransaction::STATUS_EXPIRED]);
                if ($this->stateMachine->canTransition($order->status, Order::STATUS_EXPIRED)) {
                    $this->stateMachine->transition($order, Order::STATUS_EXPIRED);
                }
            } elseif ($parsed['status'] === 'failed') {
                $payment->update(['status' => PaymentTransaction::STATUS_FAILED]);
                if ($this->stateMachine->canTransition($order->status, Order::STATUS_CANCELLED)) {
                    $this->stateMachine->transition($order, Order::STATUS_CANCELLED);
                }
            }

            $webhookLog->update(['processed_at' => now()]);
        });

        foreach ($supplierOrderIdsToDispatch as $supplierOrderId) {
            ProcessSupplierOrderJob::dispatch($supplierOrderId);
        }

        return [
            'duplicate' => false,
            'processed' => true,
            'order_no' => $orderNo,
            'supplier_orders_created' => count($supplierOrderIdsToDispatch),
        ];
    }
}
