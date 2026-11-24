<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCredential extends Model
{
    protected $fillable = [
        'supplier_id',
        'payload',
    ];

    /**
     * Never expose encrypted or decrypted credentials in JSON/array serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
