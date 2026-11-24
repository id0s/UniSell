<?php

namespace App\Services\Orders;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * Create a unified order (website / telegram / whatsapp) and initial payment transaction.
     *
     * @param  array<string, mixed>  $data
     * @return array{order: Order, payment: PaymentTransaction, payment_instructions: array<string, mixed>}
     */
    public function createOrder(array $data, ?int $userId = null): array
    {
        return DB::transaction(function () use ($data, $userId) {
            $product = Product::with(['price', 'activeMappings.supplierProduct.supplier'])
                ->findOrFail($data['product_id']);

            if ($product->status !== Product::STATUS_ACTIVE) {
                throw new DomainException('Produk sedang tidak tersedia untuk pembelian.');
            }

            $mapping = $product->activeMappings->first(function ($m) {
                return $m->supplierProduct?->supplier?->hasActiveSubscription() === true;
            });

            if (! $mapping) {
                throw new DomainException('Supplier untuk produk ini tidak memiliki langganan aktif.');
            }

            $qty = max(1, (int) ($data['qty'] ?? 1));
            $unitPrice = (int) ($product->price?->sell_price ?? 0);
            $unitCost = (int) ($mapping->supplierProduct->cost_price ?? 0);
            $total = $unitPrice * $qty;

            if ($total <= 0) {
                throw new DomainException('Harga produk belum valid.');
            }

            $orderNo = 'US-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));

            $order = Order::create([
                'order_no' => $orderNo,
                'user_id' => $userId,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_email' => $data['guest_email'] ?? null,
                'channel' => $data['channel'] ?? Order::CHANNEL_WEBSITE,
                'status' => Order::STATUS_PENDING_PAYMENT,
                'total' => $total,
                'customer_input' => $data['customer_input'] ?? [],
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'unit_cost' => $unitCost,
            ]);

            $paymentMethod = $data['payment_method'] ?? 'qris';
            $gatewayResp = $this->paymentGateway->createPayment($order, $paymentMethod);

            $payment = PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway' => $this->paymentGateway->gatewayName(),
                'ref' => $gatewayResp['ref'],
                'status' => PaymentTransaction::STATUS_PENDING,
                'amount' => $gatewayResp['amount'],
                'fee' => $gatewayResp['fee'],
                'expires_at' => $gatewayResp['expires_at'],
            ]);

            return [
                'order' => $order->load(['items.product', 'latestPayment']),
                'payment' => $payment,
                'payment_instructions' => $gatewayResp['instructions'],
            ];
        });
    }
}
