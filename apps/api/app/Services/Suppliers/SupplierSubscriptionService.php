<?php

namespace App\Services\Suppliers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPackage;
use App\Models\SupplierSubscription;
use Illuminate\Support\Facades\DB;

class SupplierSubscriptionService
{
    public function subscribe(Supplier $supplier, SupplierPackage $package, ?int $actorUserId = null): SupplierSubscription
    {
        return DB::transaction(function () use ($supplier, $package, $actorUserId) {
            // Cancel any previous active subscription before creating a new one
            $supplier->subscriptions()
                ->where('status', SupplierSubscription::STATUS_ACTIVE)
                ->update(['status' => SupplierSubscription::STATUS_CANCELLED]);

            $now = now();
            $subscription = SupplierSubscription::create([
                'supplier_id' => $supplier->id,
                'package_id' => $package->id,
                'status' => SupplierSubscription::STATUS_ACTIVE,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($package->period_days),
            ]);

            $supplier->update(['status' => Supplier::STATUS_ACTIVE]);

            $this->syncProductLocksForSupplier($supplier);

            AuditLog::create([
                'user_id' => $actorUserId,
                'action' => 'supplier.subscription.created',
                'subject_type' => SupplierSubscription::class,
                'subject_id' => $subscription->id,
                'diff' => [
                    'supplier_id' => $supplier->id,
                    'package_id' => $package->id,
                    'price_idr' => $package->price,
                    'ends_at' => $subscription->ends_at->toIso8601String(),
                ],
            ]);

            return $subscription->load(['supplier', 'package']);
        });
    }

    public function renew(SupplierSubscription $subscription, ?int $actorUserId = null): SupplierSubscription
    {
        return DB::transaction(function () use ($subscription, $actorUserId) {
            $package = $subscription->package;
            $baseDate = $subscription->ends_at->isFuture() ? $subscription->ends_at : now();

            $subscription->update([
                'status' => SupplierSubscription::STATUS_ACTIVE,
                'ends_at' => $baseDate->copy()->addDays($package->period_days),
            ]);

            $supplier = $subscription->supplier;
            $supplier->update(['status' => Supplier::STATUS_ACTIVE]);

            $this->syncProductLocksForSupplier($supplier);

            AuditLog::create([
                'user_id' => $actorUserId,
                'action' => 'supplier.subscription.renewed',
                'subject_type' => SupplierSubscription::class,
                'subject_id' => $subscription->id,
                'diff' => [
                    'ends_at' => $subscription->ends_at->toIso8601String(),
                ],
            ]);

            return $subscription->fresh(['supplier', 'package']);
        });
    }

    public function cancel(SupplierSubscription $subscription, ?int $actorUserId = null): SupplierSubscription
    {
        return DB::transaction(function () use ($subscription, $actorUserId) {
            $subscription->update([
                'status' => SupplierSubscription::STATUS_CANCELLED,
            ]);

            $supplier = $subscription->supplier;
            $this->syncProductLocksForSupplier($supplier);

            AuditLog::create([
                'user_id' => $actorUserId,
                'action' => 'supplier.subscription.cancelled',
                'subject_type' => SupplierSubscription::class,
                'subject_id' => $subscription->id,
                'diff' => ['supplier_id' => $supplier->id],
            ]);

            return $subscription->fresh(['supplier', 'package']);
        });
    }

    public function expireOverdueSubscriptions(): int
    {
        $overdue = SupplierSubscription::with('supplier')
            ->where('status', SupplierSubscription::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($overdue as $subscription) {
            $subscription->update(['status' => SupplierSubscription::STATUS_EXPIRED]);
            $this->syncProductLocksForSupplier($subscription->supplier);
            $count++;
        }

        return $count;
    }

    /**
     * Invariant #7:
     * When a supplier subscription ends, products that have no other active-subscription supplier
     * are locked for new sales (status = locked), while ongoing orders continue and data is never deleted.
     * When a subscription is activated/renewed, previously locked products are unlocked back to active.
     */
    public function syncProductLocksForSupplier(Supplier $supplier): void
    {
        $productIds = Product::whereHas('mappings.supplierProduct', function ($q) use ($supplier) {
            $q->where('supplier_id', $supplier->id);
        })->pluck('id');

        if ($productIds->isEmpty()) {
            return;
        }

        $products = Product::with(['activeMappings.supplierProduct.supplier'])->whereIn('id', $productIds)->get();

        foreach ($products as $product) {
            $hasAnyActiveSupplier = $product->activeMappings->contains(function ($mapping) {
                $mappedSupplier = $mapping->supplierProduct?->supplier;

                return $mappedSupplier !== null && $mappedSupplier->hasActiveSubscription();
            });

            if (! $hasAnyActiveSupplier && $product->status === Product::STATUS_ACTIVE) {
                $product->update(['status' => Product::STATUS_LOCKED]);
            } elseif ($hasAnyActiveSupplier && $product->status === Product::STATUS_LOCKED) {
                $product->update(['status' => Product::STATUS_ACTIVE]);
            }
        }
    }
}
