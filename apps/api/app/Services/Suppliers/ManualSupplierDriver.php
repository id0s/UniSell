<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;

/**
 * Fallback driver for suppliers without an official API (manual product input & manual fulfillment).
 */
class ManualSupplierDriver implements SupplierDriverInterface
{
    public function syncProducts(Supplier $supplier): array
    {
        // Manual suppliers manage supplier_products via admin input, not external sync.
        return [];
    }

    public function placeOrder(Supplier $supplier, array $orderPayload): array
    {
        // Manual fulfillment never auto-succeeds; it transitions to manual_review for admin processing.
        return [
            'success' => false,
            'external_ref' => null,
            'error' => 'Manual supplier requires admin fulfillment.',
        ];
    }

    public function checkOrder(Supplier $supplier, string $externalRef): array
    {
        return [
            'status' => 'manual_review',
        ];
    }

    public function checkHealth(Supplier $supplier): array
    {
        return [
            'health_status' => Supplier::HEALTH_HEALTHY,
            'latency_ms' => 0,
            'message' => 'Manual supplier active (no external API required).',
        ];
    }

    public function getBalance(Supplier $supplier): int
    {
        return (int) $supplier->balance;
    }
}
