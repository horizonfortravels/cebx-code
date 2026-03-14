<?php

namespace App\Models;

use App\Models\Traits\BelongsToAccount;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice — FR-PAY-005
 *
 * Invoices and receipts with PDF generation and tax compliance.
 */
class Invoice extends Model
{
    use HasFactory, HasUuids, BelongsToAccount;

    protected $fillable = [
        'account_id', 'transaction_id', 'invoice_number', 'type',
        'subtotal', 'tax_amount', 'discount_amount', 'total', 'currency', 'tax_rate',
        'billing_name', 'billing_address', 'tax_number',
        'status', 'issued_at', 'due_at', 'paid_at', 'pdf_path',
    ];

    protected $casts = [
        'subtotal'        => 'decimal:2',
        'tax_amount'      => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total'           => 'decimal:2',
        'tax_rate'        => 'decimal:2',
        'issued_at'       => 'datetime',
        'due_at'          => 'datetime',
        'paid_at'         => 'datetime',
    ];

    const TYPE_INVOICE    = 'invoice';
    const TYPE_RECEIPT    = 'receipt';
    const TYPE_CREDIT     = 'credit_note';

    const STATUS_DRAFT  = 'draft';
    const STATUS_ISSUED = 'issued';
    const STATUS_PAID   = 'paid';
    const STATUS_VOID   = 'void';

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public static function generateNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym');
        $count = self::where('invoice_number', 'like', $prefix . '%')->count() + 1;
        return $prefix . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }

    public function markIssued(): void
    {
        $this->update(['status' => self::STATUS_ISSUED, 'issued_at' => now()]);
    }

    public function markPaid(): void
    {
        $this->update(['status' => self::STATUS_PAID, 'paid_at' => now()]);
    }
}
