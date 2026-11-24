<?php

namespace App\Contracts;

use App\Models\Supplier;

interface SupplierDriverInterface
{
    /**
     * Fetch normalized product catalog from the supplier.
     *
     * @return list<array{external_id: string, name: string, cost_price: int, stock_status: string, raw: array<string, mixed>}>
     */
    public function syncProducts(Supplier $supplier): array;

    /**
     * Place an order to the supplier.
     *
     * @param  array<string, mixed>  $orderPayload
     * @return array{success: bool, external_ref: ?string, delivery_type?: string, delivery_payload?: string, error?: string}
     */
    public function placeOrder(Supplier $supplier, array $orderPayload): array;

    /**
     * Check an existing supplier order status.
     *
     * @return array{status: string, delivery_payload?: string, error?: string}
     */
    public function checkOrder(Supplier $supplier, string $externalRef): array;

    /**
     * Check supplier API connectivity and health.
     *
     * @return array{health_status: string, latency_ms: int, message: string}
     */
    public function checkHealth(Supplier $supplier): array;

    /**
     * Retrieve current supplier deposit balance in integer Rupiah.
     */
    public function getBalance(Supplier $supplier): int;
}
