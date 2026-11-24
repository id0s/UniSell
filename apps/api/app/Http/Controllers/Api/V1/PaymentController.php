<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\PaymentGatewayInterface;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\Payments\MockPaymentGateway;
use App\Services\Payments\PaymentWebhookService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
        private readonly PaymentWebhookService $webhookService,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_no' => ['required', 'string', 'exists:orders,order_no'],
            'method' => ['sometimes', 'in:qris,va,ewallet'],
        ]);

        $order = Order::where('order_no', $validated['order_no'])->firstOrFail();

        if ($order->status !== Order::STATUS_PENDING_PAYMENT) {
            return response()->json([
                'error' => [
                    'code' => 'ORDER_NOT_PAYABLE',
                    'message' => 'Pesanan tidak dalam status menunggu pembayaran.',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $resp = $this->paymentGateway->createPayment($order, $validated['method'] ?? 'qris');

        $payment = PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => $this->paymentGateway->gatewayName(),
            'ref' => $resp['ref'],
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount' => $resp['amount'],
            'fee' => $resp['fee'],
            'expires_at' => $resp['expires_at'],
        ]);

        return response()->json([
            'data' => [
                'payment' => $payment,
                'instructions' => $resp['instructions'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function webhook(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');
        $payload = $request->all();

        try {
            $result = $this->webhookService->handleWebhook($rawBody, $payload, $signature);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'WEBHOOK_REJECTED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Local/testing sandbox helper so storefront users can simulate a signed payment webhook end-to-end.
     */
    public function simulateLocalPaid(Request $request): JsonResponse
    {
        if (! app()->environment(['local', 'testing'])) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Simulation endpoint is only available in local/testing environments.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'payment_ref' => ['required', 'string', 'exists:payment_transactions,ref'],
        ]);

        $payment = PaymentTransaction::where('ref', $validated['payment_ref'])->firstOrFail();

        $payload = [
            'event_id' => 'evt-sim-'.Str::uuid()->toString(),
            'payment_ref' => $payment->ref,
            'status' => 'paid',
            'amount' => (int) $payment->amount,
            'fee' => (int) $payment->fee,
        ];

        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = MockPaymentGateway::signPayload($rawBody);

        $result = $this->webhookService->handleWebhook($rawBody, $payload, $signature);

        return response()->json(['data' => $result]);
    }
}
