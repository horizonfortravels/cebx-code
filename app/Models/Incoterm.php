<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Incoterm extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'name_ar', 'description', 'description_ar',
        'transport_mode', 'seller_pays_freight', 'seller_pays_insurance',
        'seller_pays_import_duty', 'seller_handles_export_clearance',
        'buyer_handles_import_clearance', 'risk_transfer_point',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'seller_pays_freight' => 'boolean',
        'seller_pays_insurance' => 'boolean',
        'seller_pays_import_duty' => 'boolean',
        'seller_handles_export_clearance' => 'boolean',
        'buyer_handles_import_clearance' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q) { return $q->where('is_active', true)->orderBy('sort_order'); }
}
