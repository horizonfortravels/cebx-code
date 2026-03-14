<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DgMetadata — FR-DG-009
 *
 * Optional dangerous goods details: UN number, hazard class, packing group, quantity.
 * Collected when user declares DG=Yes for future DG-capable carriers.
 */
class DgMetadata extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'dg_metadata';

    protected $fillable = [
        'declaration_id',
        'un_number', 'dg_class', 'packing_group', 'proper_shipping_name',
        'quantity', 'quantity_unit', 'description', 'additional_info',
    ];

    protected $casts = [
        'quantity'        => 'decimal:3',
        'additional_info' => 'array',
    ];

    // Valid hazard classes per UN classification
    const VALID_CLASSES = ['1', '1.1', '1.2', '1.3', '1.4', '1.5', '1.6', '2', '2.1', '2.2', '2.3', '3', '4', '4.1', '4.2', '4.3', '5', '5.1', '5.2', '6', '6.1', '6.2', '7', '8', '9'];
    const VALID_PACKING_GROUPS = ['I', 'II', 'III'];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(ContentDeclaration::class, 'declaration_id');
    }

    public function isValidUnNumber(): bool
    {
        return $this->un_number && preg_match('/^UN\d{4}$/', $this->un_number);
    }

    public function isValidClass(): bool
    {
        return $this->dg_class && in_array($this->dg_class, self::VALID_CLASSES);
    }
}
