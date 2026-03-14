<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OrderItem — Line item in a Canonical Order.
 */
class OrderItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_id', 'external_item_id', 'sku', 'name', 'quantity',
        'unit_price', 'total_price', 'weight', 'hs_code',
        'country_of_origin', 'properties',
    ];

    protected $casts = [
        'quantity'    => 'integer',
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
        'weight'      => 'decimal:3',
        'properties'  => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function totalWeight(): float
    {
        return (float) $this->weight * $this->quantity;
    }
}
