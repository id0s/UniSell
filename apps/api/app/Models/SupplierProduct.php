<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id',
        'external_id',
        'name',
        'cost_price',
        'stock_status',
        'raw',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'integer',
            'raw' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ProductMapping::class);
    }
}
