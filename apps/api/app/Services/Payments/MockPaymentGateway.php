<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * Single payment gateway wrapper (QRIS + VA + e-wallet) behind PaymentGatewayInterface.
 * TODO(docs): Swap implementation with Midtrans/Xendit/Tripay/Duitku once official credentials & docs are provided.
 */
class MockPaymentGateway implements PaymentGatewayInterface
{
    public function gatewayName(): string
    {
        return 'unisell_pay_mock';
    }

    public function createPayment(Order $order, string $method = 'qris'): array
    {
        $ref = 'PAY-'.strtoupper(Str::random(12));
        $fee = match ($method) {
            'qris' => max(500, (int) round($order->total * 0.007)), // 0.7% QRIS MDR
            'va' => 4000,
            'ewallet' => max(1000, (int) round($order->total * 0.015)),
            default => 1000,
        };

        $expiresAt = now()->addMinutes(30);

        return [
            'ref' => $ref,
            'method' => $method,
            'amount' => (int) $order->total,
            'fee' => $fee,
            'payment_url' => url("/api/v1/payments/mock-checkout/{$ref}"),
            'instructions' => [
                'method' => strtoupper($method),
                'ref' => $ref,
                'note' => 'Sandbox payment instruction — gunakan tombol Simulasikan Bayar pada mode lokal.',
            ],
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $secret = (string) config('services.payment_gateway.webhook_secret', 'unisell-local-webhook-secret');
        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'event_id' => (string) ($payload['event_id'] ?? ''),
            'payment_ref' => (string) ($payload['payment_ref'] ?? ''),
            'status' => (string) ($payload['status'] ?? 'pending'),
            'amount' => (int) ($payload['amount'] ?? 0),
            'fee' => (int) ($payload['fee'] ?? 0),
        ];
    }

    public static function signPayload(string $rawBody): string
    {
        $secret = (string) config('services.payment_gateway.webhook_secret', 'unisell-local-webhook-secret');

        return hash_hmac('sha256', $rawBody, $secret);
    }
}
