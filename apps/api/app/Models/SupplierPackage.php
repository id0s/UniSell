<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPackage extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'price',
        'period_days',
        'product_limit',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'period_days' => 'integer',
            'product_limit' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SupplierSubscription::class, 'package_id');
    }
}
