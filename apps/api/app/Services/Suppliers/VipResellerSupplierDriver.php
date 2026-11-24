<?php

namespace App\Services\Suppliers;

use App\Contracts\SupplierDriverInterface;
use App\Models\Supplier;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * VIP-Reseller (VIPayment) Supplier Driver (https://vip-reseller.co.id/api).
 * Instant registration without long KYC wait.
 *
 * Encrypted credentials payload:
 * - api_id: string (API ID from member area)
 * - api_key: string (API Key from member area)
 * - base_url: ?string (defaults to https://vip-reseller.co.id/api)
 */
class VipResellerSupplierDriver implements SupplierDriverInterface
{
    private const DEFAULT_BASE_URL = 'https://vip-reseller.co.id/api';

    public function syncProducts(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_id']) || empty($creds['api_key'])) {
            return [];
        }

        $sign = md5((string) $creds['api_id'].(string) $creds['api_key']);
        $normalized = [];

        foreach (['/game-feature' => 'game', '/prepaid' => 'prepaid'] as $endpoint => $kind) {
            try {
                $response = Http::baseUrl($this->baseUrl($creds))
                    ->asForm()
                    ->timeout(25)
                    ->acceptJson()
                    ->post($endpoint, [
                        'key' => (string) $creds['api_key'],
                        'sign' => $sign,
                        'type' => 'services',
                    ]);

                if (! $response->successful() || ! $response->json('result')) {
                    continue;
                }

                $items = $response->json('data');
                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $code = (string) ($item['code'] ?? '');
                    $name = (string) ($item['name'] ?? '');
                    if ($code === '' || $name === '') {
                        continue;
                    }

                    $priceData = $item['price'] ?? 0;
                    $rawPrice = is_array($priceData)
                        ? ($priceData['basic'] ?? $priceData['premium'] ?? $priceData['special'] ?? 0)
                        : $priceData;

                    $status = strtolower((string) ($item['status'] ?? 'available'));

                    $normalized[] = [
                        'external_id' => "{$kind}:{$code}",
                        'name' => $name,
                        'cost_price' => max(0, (int) round((float) $rawPrice)),
                        'stock_status' => in_array($status, ['available', 'active', 'empty' === $status ? '' : 'normal'], true)
                            ? 'available'
                            : 'out_of_stock',
                        'raw' => array_merge($item, ['_vip_kind' => $kind]),
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return $normalized;
    }

    public function placeOrder(Supplier $supplier, array $orderPayload): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_id']) || empty($creds['api_key'])) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'API ID atau API Key VIP-Reseller belum dikonfigurasi.',
            ];
        }

        $sign = md5((string) $creds['api_id'].(string) $creds['api_key']);
        $externalId = (string) ($orderPayload['external_product_id'] ?? '');
        [$kind, $serviceCode] = str_contains($externalId, ':')
            ? explode(':', $externalId, 2)
            : ['game', $externalId];

        $endpoint = $kind === 'prepaid' ? '/prepaid' : '/game-feature';
        $target = (string) ($orderPayload['customer_input']['target_account'] ?? '');
        $zone = (string) ($orderPayload['customer_input']['zone_id'] ?? '');

        $form = [
            'key' => (string) $creds['api_key'],
            'sign' => $sign,
            'type' => 'order',
            'service' => $serviceCode,
            'data_no' => $target,
        ];

        if ($kind === 'game' && $zone !== '') {
            $form['data_zone'] = $zone;
        }

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->asForm()
                ->timeout(20)
                ->acceptJson()
                ->post($endpoint, $form);

            $resultOk = (bool) $response->json('result');
            $data = $response->json('data') ?? [];
            $status = strtolower((string) ($data['status'] ?? ''));

            if (! $response->successful() || ! $resultOk || ! in_array($status, ['success', 'sukses'], true)) {
                return [
                    'success' => false,
                    'external_ref' => isset($data['trxid']) ? (string) $data['trxid'] : null,
                    'error' => (string) ($response->json('message') ?? $data['note'] ?? "VIP-Reseller status: {$status}"),
                ];
            }

            $trxId = (string) ($data['trxid'] ?? '');
            $note = (string) ($data['note'] ?? '');

            return [
                'success' => true,
                'external_ref' => $trxId !== '' ? $trxId : null,
                'delivery_type' => 'code',
                'delivery_payload' => $note !== ''
                    ? "Catatan / SN: {$note}\nRef VIP-Reseller: {$trxId}"
                    : "Pesanan VIP-Reseller sukses.\nRef: {$trxId}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'external_ref' => null,
                'error' => 'Gagal menghubungi VIP-Reseller: '.$e->getMessage(),
            ];
        }
    }

    public function checkOrder(Supplier $supplier, string $externalRef): array
    {
        return ['status' => 'pending'];
    }

    public function checkHealth(Supplier $supplier): array
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_id']) || empty($creds['api_key'])) {
            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => 0,
                'message' => 'API ID & API Key VIP-Reseller belum diisi. Klik "Atur API Key".',
            ];
        }

        $sign = md5((string) $creds['api_id'].(string) $creds['api_key']);
        $start = hrtime(true);

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->asForm()
                ->timeout(15)
                ->acceptJson()
                ->post('/profile', [
                    'key' => (string) $creds['api_key'],
                    'sign' => $sign,
                ]);

            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            if ($response->successful() && (bool) $response->json('result')) {
                return [
                    'health_status' => Supplier::HEALTH_HEALTHY,
                    'latency_ms' => $latencyMs,
                    'message' => 'Terhubung ke VIP-Reseller API (level: '.($response->json('data.level') ?? 'Basic').').',
                ];
            }

            return [
                'health_status' => Supplier::HEALTH_DEGRADED,
                'latency_ms' => $latencyMs,
                'message' => (string) ($response->json('message') ?? "VIP-Reseller HTTP {$response->status()}"),
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((hrtime(true) - $start) / 1_000_000);

            return [
                'health_status' => Supplier::HEALTH_DOWN,
                'latency_ms' => $latencyMs,
                'message' => 'Koneksi ke VIP-Reseller gagal: '.$e->getMessage(),
            ];
        }
    }

    public function getBalance(Supplier $supplier): int
    {
        $creds = $this->credentials($supplier);
        if (empty($creds['api_id']) || empty($creds['api_key'])) {
            return (int) $supplier->balance;
        }

        $sign = md5((string) $creds['api_id'].(string) $creds['api_key']);

        try {
            $response = Http::baseUrl($this->baseUrl($creds))
                ->asForm()
                ->timeout(15)
                ->acceptJson()
                ->post('/profile', [
                    'key' => (string) $creds['api_key'],
                    'sign' => $sign,
                ]);

            $balance = $response->json('data.balance');

            return $balance !== null ? (int) round((float) $balance) : (int) $supplier->balance;
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
        return rtrim((string) ($creds['base_url'] ?? config('services.vip_reseller.base_url', self::DEFAULT_BASE_URL)), '/');
    }
}
