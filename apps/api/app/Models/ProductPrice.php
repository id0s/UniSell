<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    public const MODE_MANUAL = 'manual';
    public const MODE_FIXED = 'fixed';
    public const MODE_PERCENT = 'percent';

    protected $fillable = [
        'product_id',
        'mode',
        'markup_value',
        'sell_price',
    ];

    protected function casts(): array
    {
        return [
            'markup_value' => 'integer',
            'sell_price' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
