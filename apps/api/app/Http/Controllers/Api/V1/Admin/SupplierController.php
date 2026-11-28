<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreSupplierPackageRequest;
use App\Http\Requests\Api\V1\Admin\StoreSupplierRequest;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierCredential;
use App\Models\SupplierPackage;
use App\Services\Suppliers\SupplierDriverManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierDriverManager $driverManager,
    ) {}

    public function index(): JsonResponse
    {
        $suppliers = Supplier::with(['activeSubscription.package', 'packages'])
            ->withCount(['supplierProducts', 'packages'])
            ->withExists('credential')
            ->latest()
            ->get()
            ->map(fn (Supplier $s) => $this->serializeSupplier($s));

        return response()->json(['data' => $suppliers]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $credentials = $validated['credentials'] ?? null;
        unset($validated['credentials']);
        $validated['status'] = $validated['status'] ?? Supplier::STATUS_ACTIVE;

        $supplier = Supplier::create($validated);

        if (! empty($credentials)) {
            SupplierCredential::create([
                'supplier_id' => $supplier->id,
                'payload' => $credentials,
            ]);
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'supplier.created',
            'subject_type' => Supplier::class,
            'subject_id' => $supplier->id,
            'diff' => [
                'name' => $supplier->name,
                'auth_method' => $supplier->auth_method,
                'credentials_configured' => ! empty($credentials),
            ],
        ]);

        $supplier->load(['activeSubscription.package', 'packages'])->loadExists('credential');

        return response()->json([
            'data' => $this->serializeSupplier($supplier),
        ], Response::HTTP_CREATED);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['activeSubscription.package', 'packages'])
            ->loadCount(['supplierProducts', 'packages'])
            ->loadExists('credential');

        return response()->json([
            'data' => $this->serializeSupplier($supplier),
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'website' => ['nullable', 'url', 'max:255'],
            'docs_url' => ['nullable', 'url', 'max:255'],
            'auth_method' => ['sometimes', 'in:api_key,bearer,basic,manual,sekalipay,digiflazz,vip_reseller'],
            'status' => ['sometimes', 'in:active,inactive,suspended'],
            'balance' => ['sometimes', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
        ]);

        $credentials = $validated['credentials'] ?? null;
        unset($validated['credentials']);

        $supplier->update($validated);

        if (is_array($credentials) && ! empty($credentials)) {
            SupplierCredential::updateOrCreate(
                ['supplier_id' => $supplier->id],
                ['payload' => $credentials]
            );
        }

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'supplier.updated',
            'subject_type' => Supplier::class,
            'subject_id' => $supplier->id,
            'diff' => [
                'updated_fields' => array_keys($validated),
                'credentials_updated' => is_array($credentials) && ! empty($credentials),
            ],
        ]);

        $supplier->load(['activeSubscription.package', 'packages'])->loadExists('credential');

        return response()->json([
            'data' => $this->serializeSupplier($supplier),
        ]);
    }

    public function packages(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'data' => $supplier->packages()->orderBy('price')->get(),
        ]);
    }

    public function storePackage(StoreSupplierPackageRequest $request, Supplier $supplier): JsonResponse
    {
        $package = $supplier->packages()->create($request->validated());

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'supplier.package.created',
            'subject_type' => SupplierPackage::class,
            'subject_id' => $package->id,
            'diff' => $package->only(['supplier_id', 'name', 'price', 'period_days', 'product_limit']),
        ]);

        return response()->json(['data' => $package], Response::HTTP_CREATED);
    }

    public function health(Supplier $supplier): JsonResponse
    {
        $supplier->load('credential');
        $driver = $this->driverManager->resolve($supplier);
        $health = $driver->checkHealth($supplier);
        $balance = $driver->getBalance($supplier);

        $supplier->update([
            'health_status' => $health['health_status'],
            'balance' => $balance,
            'last_checked_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'supplier_id' => $supplier->id,
                'name' => $supplier->name,
                'health_status' => $health['health_status'],
                'latency_ms' => $health['latency_ms'],
                'message' => $health['message'],
                'balance' => $balance,
                'checked_at' => $supplier->last_checked_at?->toIso8601String(),
            ],
        ]);
    }

    private function serializeSupplier(Supplier $supplier): array
    {
        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'website' => $supplier->website,
            'docs_url' => $supplier->docs_url,
            'auth_method' => $supplier->auth_method,
            'status' => $supplier->status,
            'health_status' => $supplier->health_status,
            'balance' => (int) $supplier->balance,
            'last_checked_at' => $supplier->last_checked_at?->toIso8601String(),
            'has_credentials' => (bool) ($supplier->credential_exists ?? $supplier->credential()->exists()),
            'has_active_subscription' => $supplier->activeSubscription !== null,
            'active_subscription' => $supplier->activeSubscription,
            'packages' => $supplier->relationLoaded('packages') ? $supplier->packages : [],
            'supplier_products_count' => $supplier->supplier_products_count ?? 0,
            'created_at' => $supplier->created_at?->toIso8601String(),
        ];
    }
}
