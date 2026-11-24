<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;
use DomainException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Digiflazz H2H Supplier Driver (https://api.digiflazz.com/v1).
 * Free registration & free testing mode (`testing: true`).
 *
 * Encrypted credentials payload:
 * - username: string (Digiflazz Buyer Username)
 * - api_key: string (Development or Production Key)
 * - base_url: ?string (defaults to https://api.digiflazz.com/v1)
 * - sandbox: ?bool (sets `testing: true` on /transaction requests)
 */
class DigiflazzSupplierDriver implements SupplierDriverInterface
{
    private const DEFAULT_BASE_URL = 'https://api.digiflazz.com/v1';

    public function syncProducts(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['username']) || empty($creds['api_key'])) {
            return [];
        }

        $username = (string) $creds['username'];
        $apiKey = (string) $creds['api_key'];

        $response = Http::baseUrl($this->baseUrl($creds))
            ->timeout(25)
            ->acceptJson()
            ->post('/price-list', [
                'cmd' => 'prepaid',
                'username' => $username,
                'sign' => md5($username.$apiKey.'pricelist'),
            ]);

        if (! $response->successful()) {
            $errMsg = (string) ($response->json('data.message') ?? "HTTP {$response->status()}");
            $rc = (string) ($response->json('data.rc') ?? '');

            if (str_starts_with($apiKey, 'dev-') && $rc === '41') {
                throw new DomainException(
                    'Digiflazz menolak /v1/price-list (rc: 41) karena masih memakai Development Key (dev-...). Ganti API Key ke Production Key di "Atur API Key" untuk menarik daftar produk Digiflazz.'
                );
            }

            throw new DomainException("Sync Digiflazz gagal ({$rc}): {$errMsg}");
        }

        $items = $response->json('data');
        if (! is_array($items) || ! array_is_list($items)) {
            $errMsg = (string) ($response->json('data.message') ?? 'Format daftar produk tidak valid.');
            throw new DomainException("Sync Digiflazz gagal: {$errMsg}");
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $sku = (string) ($item['buyer_sku_code'] ?? '');
            $name = (string) ($item['product_name'] ?? '');
            if ($sku === '' || $name === '') {
                continue;
            }

            $buyerActive = (bool) ($item['buyer_product_status'] ?? true);
            $sellerActive = (bool) ($item['seller_product_status'] ?? true);

            $normalized[] = [
                'external_id' => $sku,
                'name' => $name,
                'cost_price' => max(0, (int) round((float) ($item['price'] ?? 0))),
                'stock_status' => ($buyerActive && $sellerActive) ? 'available' : 'out_of_stock',
                'raw' => $item,
            ];
        }

        return $normalized;
    }

    public function placeOrder(Supplier $supplier, array $orderPayload): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['username']) || empty($creds['api_key'])) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'Username atau API Key Digiflazz belum dikonfigurasi.',
            ];
        }

        $username = (string) $creds['username'];
        $apiKey = (string) $creds['api_key'];
        $refId = (string) ($orderPayload['order_no'] ?? ('DF-'.time()));
        $sku = (string) ($orderPayload['external_product_id'] ?? '');
        $customerNo = (string) ($orderPayload['customer_input']['target_account'] ?? '087800001230');

        $payload = [
            'username' => $username,
            'buyer_sku_code' => $sku,
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => md5($username.$apiKey.$refId),
        ];

        if ((bool) ($creds['sandbox'] ?? true)) {
            $payload['testing'] = true;
        }

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->timeout(20)
                ->acceptJson()
                ->post('/transaction', $payload);

            $data = $response->json('data') ?? [];
            $status = strtolower((string) ($data['status'] ?? ''));

            if (! $response->successful() || $status !== 'sukses') {
                return [
                    'success' => false,
                    'external_ref' => (string) ($data['ref_id'] ?? $refId),
                    'error' => (string) ($data['message'] ?? "Digiflazz status: {$status} (HTTP {$response->status()})"),
                ];
            }

            $sn = (string) ($data['sn'] ?? '');

            return [
                'success' => true,
                'external_ref' => (string) ($data['ref_id'] ?? $refId),
                'delivery_type' => 'code',
                'delivery_payload' => $sn !== ''
                    ? "SN / Voucher: {$sn}\nRef Digiflazz: {$refId}"
                    : "Transaksi Digiflazz Sukses\nRef: {$refId}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'Gagal menghubungi API Digiflazz: '.$e->getMessage(),
            ];
        }
    }

    public function checkOrder(Supplier $supplier, string $externalRef): array
    {
        return [
            'status' => 'pending',
            'error' => 'Gunakan webhook callback Digiflazz atau retry transaksi dengan ref_id yang sama.',
        ];
    }

    public function checkHealth(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['username']) || empty($creds['api_key'])) {
            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => 0,
                'message' => 'Username & API Key Digiflazz belum diisi. Klik "Atur API Key" untuk menghubungkan.',
            ];
        }

        $username = (string) $creds['username'];
        $apiKey = (string) $creds['api_key'];
        $start = hrtime(true);

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->timeout(15)
                ->acceptJson()
                ->post('/cek-saldo', [
                    'cmd' => 'deposit',
                    'username' => $username,
                    'sign' => md5($username.$apiKey.'depo'),
                ]);

            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);
            $data = $response->json('data') ?? [];

            if ($response->successful() && array_key_exists('deposit', $data)) {
                return [
                    'health_status' => Supplier::HEALTH_HEALTHY,
                    'latency_ms' => $latencyMs,
                    'message' => 'Terhubung ke Digiflazz API.',
                ];
            }

            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => $latencyMs,
                'message' => (string) ($data['message'] ?? "Digiflazz HTTP {$response->status()} — cek Username, API Key & whitelist IP."),
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            return [
                'health_status' => Supplier::HEALTH_DOWN,
                'latency_ms' => $latencyMs,
                'message' => 'Koneksi ke Digiflazz gagal: '.$e->getMessage(),
            ];
        }
    }

    public function getBalance(Supplier $supplier): int
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['username']) || empty($creds['api_key'])) {
            return (int) $supplier->balance;
        }

        $username = (string) $creds['username'];
        $apiKey = (string) $creds['api_key'];

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->timeout(15)
                ->acceptJson()
                ->post('/cek-saldo', [
                    'cmd' => 'deposit',
                    'username' => $username,
                    'sign' => md5($username.$apiKey.'depo'),
                ]);

            $deposit = $response->json('data.deposit');

            return $deposit !== null ? (int) round((float) $deposit) : (int) $supplier->balance;
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
    private function baseUrl(array $creds): string
    {
        return rtrim((string) ($creds['base_url'] ?? config('services.digiflazz.base_url', self::DEFAULT_BASE_URL)), '/');
    }
}
