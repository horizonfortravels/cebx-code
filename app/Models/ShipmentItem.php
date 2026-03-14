<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'shipment_id', 'declaration_id', 'description', 'description_ar',
        'hs_code', 'quantity', 'unit', 'weight', 'unit_value', 'total_value',
        'currency', 'origin_country', 'dangerous_flag', 'dg_class', 'un_number',
        'brand', 'model', 'serial_number', 'metadata',
    ];

    protected $casts = [
        'weight' => 'decimal:3', 'unit_value' => 'decimal:2',
        'total_value' => 'decimal:2', 'dangerous_flag' => 'boolean',
        'metadata' => 'json',
    ];

    public function shipment() { return $this->belongsTo(Shipment::class); }
    public function declaration() { return $this->belongsTo(CustomsDeclaration::class, 'declaration_id'); }

    public function hsCodeRecord() { return HsCode::where('code', $this->hs_code)->first(); }
}
