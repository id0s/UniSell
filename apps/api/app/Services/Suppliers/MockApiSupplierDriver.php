<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;
use Illuminate\Support\Str;

/**
 * Example API driver using deterministic mock responses.
 * TODO(docs): Replace mock calls with official supplier REST API endpoints once supplier docs are provided.
 */
class MockApiSupplierDriver implements SupplierDriverInterface
{
    public function syncProducts(Supplier $supplier): array
    {
        // TODO(docs): Call supplier catalog endpoint using decrypted $supplier->credential->payload.
        return [
            [
                'external_id' => 'VCH-ML-86',
                'name' => 'Mobile Legends 86 Diamonds',
                'cost_price' => 19500,
                'stock_status' => 'available',
                'raw' => ['sku' => 'VCH-ML-86', 'category' => 'Game Topup', 'instant' => true],
            ],
            [
                'external_id' => 'VCH-FF-140',
                'name' => 'Free Fire 140 Diamonds',
                'cost_price' => 18200,
                'stock_status' => 'available',
                'raw' => ['sku' => 'VCH-FF-140', 'category' => 'Game Topup', 'instant' => true],
            ],
            [
                'external_id' => 'LIC-CANVA-30D',
                'name' => 'Canva Pro Team 30 Hari',
                'cost_price' => 25000,
                'stock_status' => 'available',
                'raw' => ['sku' => 'LIC-CANVA-30D', 'category' => 'Aplikasi Premium', 'instant' => true],
            ],
            [
                'external_id' => 'VCH-STEAM-60K',
                'name' => 'Steam Wallet IDR 60.000',
                'cost_price' => 61500,
                'stock_status' => 'available',
                'raw' => ['sku' => 'VCH-STEAM-60K', 'category' => 'Voucher Digital', 'instant' => true],
            ],
        ];
    }

    public function placeOrder(Supplier $supplier, array $orderPayload): array
    {
        // TODO(docs): Submit order to official supplier API endpoint.
        $simulateFailure = (bool) ($orderPayload['customer_input']['simulate_supplier_fail'] ?? false);

        if ($simulateFailure || $supplier->health_status === Supplier::HEALTH_DOWN) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'Supplier API timeout or rejected order.',
            ];
        }

        $ref = 'SUP-'.strtoupper(Str::random(10));
        $voucherCode = 'UNISELL-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));

        return [
            'success' => true,
            'external_ref' => $ref,
            'delivery_type' => 'voucher',
            'delivery_payload' => "Kode Voucher: {$voucherCode}\nRef Supplier: {$ref}",
        ];
    }

    public function checkOrder(Supplier $supplier, string $externalRef): array
    {
        // TODO(docs): Query supplier order status endpoint.
        return [
            'status' => 'success',
            'delivery_payload' => "Verified Ref: {$externalRef}",
        ];
    }

    public function checkHealth(Supplier $supplier): array
    {
        $hasCredentials = $supplier->credential !== null && ! empty($supplier->credential->payload);

        if (! $hasCredentials) {
            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => 15,
                'message' => 'API credentials not configured yet.',
            ];
        }

        return [
            'health_status' => Supplier::HEALTH_HEALTHY,
            'latency_ms' => 42,
            'message' => 'Mock API supplier reachable.',
        ];
    }

    public function getBalance(Supplier $supplier): int
    {
        // TODO(docs): Fetch real deposit balance from supplier API.
        return $supplier->balance > 0 ? $supplier->balance : 5000000;
    }
}
