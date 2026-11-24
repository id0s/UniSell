<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'qty' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'channel' => ['sometimes', 'in:website,telegram,whatsapp'],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:150'],
            'payment_method' => ['sometimes', 'in:qris,va,ewallet'],
            'customer_input' => ['sometimes', 'array'],
        ]);

        try {
            $result = $this->orderService->createOrder($validated, $request->user('sanctum')?->id);
        } catch (DomainException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ORDER_CREATION_FAILED',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => [
                'order' => $result['order'],
                'payment' => $result['payment'],
                'payment_instructions' => $result['payment_instructions'],
            ],
        ], Response::HTTP_CREATED);
    }

    public function show(string $orderNo): JsonResponse
    {
        $order = Order::with([
            'items.product:id,name,slug',
            'latestPayment',
            'deliveries',
            'refunds',
        ])->where('order_no', $orderNo)->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'channel' => $order->channel,
                'status' => $order->status,
                'total' => (int) $order->total,
                'guest_name' => $order->guest_name,
                'guest_email' => $order->guest_email,
                'customer_input' => $order->customer_input,
                'items' => $order->items,
                'payment' => $order->latestPayment,
                'deliveries' => $order->deliveries->map(fn ($d) => [
                    'id' => $d->id,
                    'type' => $d->type,
                    'payload' => $d->payload,
                    'delivered_at' => $d->delivered_at?->toIso8601String(),
                ]),
                'refunds' => $order->refunds,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $query = Order::with(['items.product:id,name,slug', 'latestPayment'])->latest();

        if ($user = $request->user('sanctum')) {
            $query->where('user_id', $user->id);
        } elseif ($email = $request->query('email')) {
            $query->where('guest_email', $email);
        } else {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $query->limit(50)->get()]);
    }
}
