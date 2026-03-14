<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShipmentCharge extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipment_id', 'tariff_rule_id', 'charge_type', 'description',
        'amount', 'currency', 'exchange_rate', 'amount_base',
        'is_billable', 'is_taxable', 'created_by', 'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'exchange_rate' => 'decimal:6',
        'amount_base' => 'decimal:2',
        'is_billable' => 'boolean', 'is_taxable' => 'boolean',
        'metadata' => 'json',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function tariffRule() { return $this->belongsTo(TariffRule::class); }
}
