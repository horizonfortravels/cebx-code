<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CustomsDocument extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'declaration_id', 'shipment_id', 'document_type', 'document_name',
        'document_number', 'file_path', 'file_type', 'file_size',
        'uploaded_by', 'is_required', 'is_verified', 'verified_by',
        'verified_at', 'rejection_reason', 'expiry_date', 'metadata',
    ];

    protected $casts = [
        'is_required' => 'boolean', 'is_verified' => 'boolean',
        'verified_at' => 'datetime', 'expiry_date' => 'datetime',
        'metadata' => 'json',
    ];

    public function declaration() { return $this->belongsTo(CustomsDeclaration::class, 'declaration_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function verifier() { return $this->belongsTo(User::class, 'verified_by'); }
}
