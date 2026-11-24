<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    public const TYPE_CODE = 'code';
    public const TYPE_FILE = 'file';
    public const TYPE_VOUCHER = 'voucher';

    protected $fillable = [
        'order_id',
        'type',
        'payload',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
