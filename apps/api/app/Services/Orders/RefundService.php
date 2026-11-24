<?php

namespace App\Services\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use DomainException;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(
        private readonly OrderStateMachine $stateMachine,
    ) {}

    public function refundOrder(Order $order, string $reason, ?int $actorUserId = null): Refund
    {
        if (! $this->stateMachine->canTransition($order->status, Order::STATUS_REFUNDED)) {
            throw new DomainException("Order [{$order->order_no}] dengan status [{$order->status}] tidak dapat di-refund.");
        }

        return DB::transaction(function () use ($order, $reason, $actorUserId) {
            $refund = Refund::create([
                'order_id' => $order->id,
                'amount' => (int) $order->total,
                'reason' => $reason,
                'status' => Refund::STATUS_PROCESSED,
            ]);

            $this->stateMachine->transition($order, Order::STATUS_REFUNDED);

            $order->payments()
                ->where('status', PaymentTransaction::STATUS_PAID)
                ->update(['status' => PaymentTransaction::STATUS_REFUNDED]);

            AuditLog::create([
                'user_id' => $actorUserId,
                'action' => 'order.refunded',
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'diff' => [
                    'order_no' => $order->order_no,
                    'amount' => $refund->amount,
                    'reason' => $reason,
                ],
            ]);

            return $refund;
        });
    }
}
