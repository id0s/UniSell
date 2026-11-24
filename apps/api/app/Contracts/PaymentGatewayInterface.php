<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGatewayInterface
{
    public function gatewayName(): string;

    /**
     * Create a payment invoice/instruction for an order (QRIS, VA, e-wallet).
     *
     * @return array{ref: string, method: string, amount: int, fee: int, payment_url: string, instructions: array<string, mixed>, expires_at: string}
     */
    public function createPayment(Order $order, string $method = 'qris'): array;

    /**
     * Verify incoming webhook signature using HMAC or gateway-specific scheme.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool;

    /**
     * Parse normalized webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array{event_id: string, payment_ref: string, status: string, amount: int, fee: int}
     */
    public function parseWebhook(array $payload): array;
}
