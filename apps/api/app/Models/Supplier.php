<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Supplier extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    public const HEALTH_HEALTHY = 'healthy';
    public const HEALTH_DEGRADED = 'degraded';
    public const HEALTH_DOWN = 'down';
    public const HEALTH_UNKNOWN = 'unknown';

    protected $fillable = [
        'name',
        'website',
        'docs_url',
        'auth_method',
        'status',
        'health_status',
        'balance',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    public function credential(): HasOne
    {
        return $this->hasOne(SupplierCredential::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(SupplierPackage::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SupplierSubscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(SupplierSubscription::class)
            ->where('status', SupplierSubscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->latest('ends_at');
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', SupplierSubscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->exists();
    }
}
