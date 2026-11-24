<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * SekaliPay Reseller & H2H Supplier Driver (https://sekalipay.com/api).
 *
 * Uses encrypted supplier_credentials payload:
 * - api_key: string (required, sent via X-API-Key header)
 * - api_secret: ?string (optional, used for X-Signature HMAC-SHA256 when provided)
 * - base_url: ?string (defaults to https://sekalipay.com/api)
 * - sandbox: ?bool (uses /v1/order/sandbox when true)
 */
class SekaliPaySupplierDriver implements SupplierDriverInterface
{
    private const DEFAULT_BASE_URL = 'https://sekalipay.com/api';

    public function syncProducts(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_key'])) {
            return [];
        }

        $response = $this->client($creds)->get('/v1/item');
        if (! $response->successful()) {
            return [];
        }

        $json = $response->json();
        $items = $json['data'] ?? $json['items'] ?? (is_array($json) && array_is_list($json) ? $json : []);

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = (string) ($item['id'] ?? $item['code'] ?? $item['sku'] ?? '');
            $name = (string) ($item['name'] ?? $item['title'] ?? $item['product_name'] ?? '');

            if ($externalId === '' || $name === '') {
                continue;
            }

            $rawPrice = $item['price'] ?? $item['reseller_price'] ?? $item['cost_price'] ?? 0;
            $costPrice = (int) round((float) $rawPrice);

            $inStock = $item['is_available'] ?? $item['available'] ?? (($item['stock'] ?? 1) > 0);
            $stockStatus = $inStock ? 'available' : 'out_of_stock';

            $normalized[] = [
                'external_id' => $externalId,
                'name' => $name,
                'cost_price' => max(0, $costPrice),
                'stock_status' => $stockStatus,
                'raw' => $item,
            ];
        }

        return $normalized;
    }

    public function placeOrder(Supplier $supplier, array $orderPayload): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_key'])) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'SekaliPay API Key belum dikonfigurasi.',
            ];
        }

        $isSandbox = (bool) ($creds['sandbox'] ?? false);
        $endpoint = $isSandbox ? '/v1/order/sandbox' : '/v1/trx';

        // TODO(docs): Sesuaikan nama field spesifik bila produk bertipe H2H/SMM membutuhkan parameter tambahan.
        $body = [
            'ref_id' => $orderPayload['order_no'] ?? null,
            'item_id' => $orderPayload['external_product_id'] ?? null,
            'qty' => (int) ($orderPayload['qty'] ?? 1),
            'target' => $orderPayload['customer_input']['target_account'] ?? null,
        ];

        try {
            $request = $this->client($creds);

            if (! empty($creds['api_secret'])) {
                $rawJson = json_encode($body, JSON_THROW_ON_ERROR);
                $request = $request->withHeaders([
                    'X-Signature' => hash_hmac('sha256', $rawJson, (string) $creds['api_secret']),
                ]);
            }

            $response = $request->post($endpoint, $body);
            $json = $response->json() ?? [];
            $data = $json['data'] ?? $json;

            $status = strtolower((string) ($data['status'] ?? ''));
            $isSuccess = $response->successful() && in_array($status, ['success', 'completed', 'delivered', 'paid'], true);

            if (! $isSuccess) {
                return [
                    'success' => false,
                    'external_ref' => isset($data['trx_id']) ? (string) $data['trx_id'] : null,
                    'error' => (string) ($json['message'] ?? $data['error'] ?? "SekaliPay HTTP {$response->status()}"),
                ];
            }

            $sn = $data['sn'] ?? $data['voucher_code'] ?? $data['delivery_payload'] ?? null;
            $ref = (string) ($data['trx_id'] ?? $data['order_id'] ?? $data['id'] ?? '');

            return [
                'success' => true,
                'external_ref' => $ref !== '' ? $ref : null,
                'delivery_type' => 'code',
                'delivery_payload' => $sn
                    ? "SN / Kode: {$sn}\nRef SekaliPay: {$ref}"
                    : "Pesanan SekaliPay berhasil diproses.\nRef: {$ref}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'Gagal menghubungi API SekaliPay: '.$e->getMessage(),
            ];
        }
    }

    public function checkOrder(Supplier $supplier, string $externalRef): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_key'])) {
            return ['status' => 'failed', 'error' => 'SekaliPay API Key belum dikonfigurasi.'];
        }

        try {
            $response = $this->client($creds)->get("/v1/trx/{$externalRef}");
            $json = $response->json() ?? [];
            $data = $json['data'] ?? $json;

            return [
                'status' => (string) ($data['status'] ?? 'unknown'),
                'delivery_payload' => isset($data['sn']) ? (string) $data['sn'] : null,
            ];
        } catch (Throwable $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function checkHealth(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_key'])) {
            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => 0,
                'message' => 'SekaliPay API Key belum diisi. Silakan masukkan API Key di menu Suppliers.',
            ];
        }

        $start = hrtime(true);
        try {
            $response = $this->client($creds)->get('/v1/balance');
            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            if ($response->successful()) {
                return [
                    'health_status' => Supplier::HEALTH_HEALTHY,
                    'latency_ms' => $latencyMs,
                    'message' => 'Terhubung ke API SekaliPay.',
                ];
            }

            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => $latencyMs,
                'message' => "SekaliPay merespons HTTP {$response->status()} — periksa API Key / whitelist IP.",
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            return [
                'health_status' => Supplier::HEALTH_DOWN,
                'latency_ms' => $latencyMs,
                'message' => 'Koneksi ke SekaliPay gagal: '.$e->getMessage(),
            ];
        }
    }

    public function getBalance(Supplier $supplier): int
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_key'])) {
            return (int) $supplier->balance;
        }

        try {
            $response = $this->client($creds)->get('/v1/balance');
            if (! $response->successful()) {
                return (int) $supplier->balance;
            }

            $json = $response->json() ?? [];
            $raw = $json['data']['balance'] ?? $json['balance'] ?? $supplier->balance;

            return (int) round((float) $raw);
        } catch (Throwable) {
            return (int) $supplier->balance;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(Supplier $supplier): array
    {
        $supplier->loadMissing('credential');
        $payload = $supplier->credential?->payload;

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $creds
     */
    private function client(array $creds): PendingRequest
    {
        $baseUrl = rtrim((string) ($creds['base_url'] ?? config('services.sekalipay.base_url', self::DEFAULT_BASE_URL)), '/');
        $apiKey = (string) ($creds['api_key'] ?? '');

        return Http::baseUrl($baseUrl)
            ->timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-API-Key' => $apiKey,
                'Authorization' => "Bearer {$apiKey}",
            ]);
    }
}
