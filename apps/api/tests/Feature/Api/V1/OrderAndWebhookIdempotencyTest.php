<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\ProcessSupplierOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierPackage;
use App\Models\User;
use App\Services\Orders\OrderStateMachine;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Products\ProductAggregatorService;
use App\Services\Suppliers\SupplierDriverManager;
use App\Services\Suppliers\SupplierSubscriptionService;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderAndWebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveProduct(): Product
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $supplier = Supplier::create([
            'name' => 'FastGame API',
            'auth_method' => 'api_key',
            'status' => Supplier::STATUS_ACTIVE,
            'health_status' => Supplier::HEALTH_HEALTHY,
        ]);

        $pkg = SupplierPackage::create([
            'supplier_id' => $supplier->id,
            'name' => 'Basic',
            'price' => 100000,
            'period_days' => 30,
        ]);

        app(SupplierSubscriptionService::class)->subscribe($supplier, $pkg, $admin->id);

        $aggregator = app(ProductAggregatorService::class);
        $aggregator->syncFromSupplier($supplier, $admin->id);
        $sp = $supplier->supplierProducts()->firstOrFail();

        return $aggregator->createStorefrontProduct([
            'supplier_product_id' => $sp->id,
            'name' => 'ML 86 Diamonds',
            'status' => Product::STATUS_ACTIVE,
            'pricing_mode' => 'fixed',
            'markup_value' => 4500,
        ], $admin->id);
    }

    public function test_duplicate_payment_webhook_creates_strictly_one_supplier_order(): void
    {
        Queue::fake();
        $product = $this->createActiveProduct();

        $orderResp = $this->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'qty' => 1,
            'guest_name' => 'Budi',
            'guest_email' => 'budi@example.com',
            'payment_method' => 'qris',
            'customer_input' => ['game_id' => '12345678'],
        ])->assertCreated();

        $paymentRef = $orderResp->json('data.payment.ref');
        $amount = $orderResp->json('data.payment.amount');

        $payload = [
            'event_id' => 'evt-unique-001',
            'payment_ref' => $paymentRef,
            'status' => 'paid',
            'amount' => $amount,
            'fee' => 500,
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $sig = MockPaymentGateway::signPayload($rawBody);

        // Invalid signature must be rejected
        $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => 'invalid-sig',
        ], $rawBody)->assertUnauthorized();

        // First valid webhook
        $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
        ], $rawBody)
            ->assertOk()
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.supplier_orders_created', 1);

        // Second identical webhook (duplicate event_id)
        $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig,
        ], $rawBody)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);

        // Third webhook with NEW event_id for the SAME already-paid payment_ref
        $payload2 = array_merge($payload, ['event_id' => 'evt-unique-002']);
        $rawBody2 = json_encode($payload2, JSON_THROW_ON_ERROR);
        $sig2 = MockPaymentGateway::signPayload($rawBody2);

        $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_WEBHOOK_SIGNATURE' => $sig2,
        ], $rawBody2)
            ->assertOk()
            ->assertJsonPath('data.supplier_orders_created', 0);

        // Strictly 1 supplier order in DB and 1 job dispatched!
        $this->assertSame(1, SupplierOrder::count());
        Queue::assertPushed(ProcessSupplierOrderJob::class, 1);
    }

    public function test_supplier_failure_retries_three_times_then_enters_manual_review_and_can_refund(): void
    {
        $product = $this->createActiveProduct();
        $admin = User::where('email', 'admin@unisell.local')->firstOrFail();

        $orderResp = $this->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'qty' => 1,
            'guest_email' => 'failtest@example.com',
            'customer_input' => ['simulate_supplier_fail' => true],
        ])->assertCreated();

        $orderId = $orderResp->json('data.order.id');
        $paymentRef = $orderResp->json('data.payment.ref');

        Queue::fake();
        $this->postJson('/api/v1/payments/simulate-paid', [
            'payment_ref' => $paymentRef,
        ])->assertOk();

        $supplierOrder = SupplierOrder::firstOrFail();
        $driverManager = app(SupplierDriverManager::class);
        $stateMachine = app(OrderStateMachine::class);

        // Attempt 1 -> failed / failed_retry
        (new ProcessSupplierOrderJob($supplierOrder->id))->handle($driverManager, $stateMachine);
        $this->assertSame(SupplierOrder::STATUS_FAILED, $supplierOrder->fresh()->status);
        $this->assertSame(Order::STATUS_FAILED_RETRY, Order::find($orderId)->status);

        // Attempt 2 -> still failed
        (new ProcessSupplierOrderJob($supplierOrder->id))->handle($driverManager, $stateMachine);
        $this->assertSame(2, $supplierOrder->fresh()->attempts);

        // Attempt 3 -> max attempts reached -> manual_review!
        (new ProcessSupplierOrderJob($supplierOrder->id))->handle($driverManager, $stateMachine);
        $this->assertSame(SupplierOrder::STATUS_MANUAL_REVIEW, $supplierOrder->fresh()->status);
        $this->assertSame(Order::STATUS_MANUAL_REVIEW, Order::find($orderId)->status);

        // Admin refunds order from manual_review
        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/orders/{$orderId}/refund", [
                'reason' => 'Supplier gangguan permanen',
            ])->assertOk();

        $this->assertSame(Order::STATUS_REFUNDED, Order::find($orderId)->status);
    }

    public function test_delivery_payload_is_encrypted_and_order_state_machine_rejects_invalid_transition(): void
    {
        $product = $this->createActiveProduct();

        $orderResp = $this->postJson('/api/v1/orders', [
            'product_id' => $product->id,
            'qty' => 1,
            'guest_email' => 'ok@example.com',
        ])->assertCreated();

        $orderNo = $orderResp->json('data.order.order_no');
        $paymentRef = $orderResp->json('data.payment.ref');

        // Simulate payment with sync queue so job runs immediately
        $this->postJson('/api/v1/payments/simulate-paid', [
            'payment_ref' => $paymentRef,
        ])->assertOk();

        $order = Order::where('order_no', $orderNo)->firstOrFail();
        $this->assertSame(Order::STATUS_DELIVERED, $order->status);

        $rawDelivery = DB::table('deliveries')->where('order_id', $order->id)->value('payload');
        $this->assertStringNotContainsString('UNISELL-', $rawDelivery);

        // State machine rejects delivered -> pending_payment
        $this->expectException(DomainException::class);
        app(OrderStateMachine::class)->transition($order, Order::STATUS_PENDING_PAYMENT);
    }
}
