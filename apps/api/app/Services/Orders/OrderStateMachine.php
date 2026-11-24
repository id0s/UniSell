<?php

namespace App\Services\Orders;

use App\Models\Order;
use DomainException;

class OrderStateMachine
{
    /**
     * Allowed transitions per agents.md Section 6:
     * pending_payment -> paid -> processing -> delivered
     * Failure branch: processing -> failed_retry -> manual_review -> refunded
     * Also: failed_retry -> processing (on retry), manual_review -> delivered (admin manual fulfill)
     * Other: pending_payment -> expired | cancelled
     *
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        Order::STATUS_PENDING_PAYMENT => [
            Order::STATUS_PAID,
            Order::STATUS_EXPIRED,
            Order::STATUS_CANCELLED,
        ],
        Order::STATUS_PAID => [
            Order::STATUS_PROCESSING,
            Order::STATUS_REFUNDED,
        ],
        Order::STATUS_PROCESSING => [
            Order::STATUS_DELIVERED,
            Order::STATUS_FAILED_RETRY,
            Order::STATUS_MANUAL_REVIEW,
        ],
        Order::STATUS_FAILED_RETRY => [
            Order::STATUS_PROCESSING,
            Order::STATUS_DELIVERED,
            Order::STATUS_MANUAL_REVIEW,
        ],
        Order::STATUS_MANUAL_REVIEW => [
            Order::STATUS_PROCESSING,
            Order::STATUS_DELIVERED,
            Order::STATUS_REFUNDED,
        ],
        Order::STATUS_DELIVERED => [],
        Order::STATUS_REFUNDED => [],
        Order::STATUS_EXPIRED => [],
        Order::STATUS_CANCELLED => [],
    ];

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function transition(Order $order, string $to): Order
    {
        if ($order->status === $to) {
            return $order;
        }

        if (! $this->canTransition($order->status, $to)) {
            throw new DomainException("Invalid order status transition from [{$order->status}] to [{$to}].");
        }

        $order->status = $to;
        $order->save();

        return $order;
    }
}
