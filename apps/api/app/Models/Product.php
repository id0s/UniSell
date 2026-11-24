<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ProductMapping::class)->orderBy('priority');
    }

    public function activeMappings(): HasMany
    {
        return $this->hasMany(ProductMapping::class)
            ->where('is_active', true)
            ->orderBy('priority');
    }

    public function price(): HasOne
    {
        return $this->hasOne(ProductPrice::class);
    }
}
