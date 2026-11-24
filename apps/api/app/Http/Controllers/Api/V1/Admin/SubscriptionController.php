<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierPackage;
use App\Models\SupplierSubscription;
use App\Services\Suppliers\SupplierSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SupplierSubscriptionService $subscriptionService,
    ) {}

    public function index(): JsonResponse
    {
        $subscriptions = SupplierSubscription::with(['supplier:id,name,status', 'package'])
            ->latest()
            ->get();

        return response()->json(['data' => $subscriptions]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'package_id' => ['required', 'integer', 'exists:supplier_packages,id'],
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);
        $package = SupplierPackage::where('id', $validated['package_id'])
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();

        $subscription = $this->subscriptionService->subscribe($supplier, $package, $request->user()?->id);

        return response()->json(['data' => $subscription], Response::HTTP_CREATED);
    }

    public function renew(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:supplier_subscriptions,id'],
        ]);

        $subscription = SupplierSubscription::with(['supplier', 'package'])->findOrFail($validated['subscription_id']);
        $updated = $this->subscriptionService->renew($subscription, $request->user()?->id);

        return response()->json(['data' => $updated]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'integer', 'exists:supplier_subscriptions,id'],
        ]);

        $subscription = SupplierSubscription::with(['supplier', 'package'])->findOrFail($validated['subscription_id']);
        $updated = $this->subscriptionService->cancel($subscription, $request->user()?->id);

        return response()->json(['data' => $updated]);
    }
}
