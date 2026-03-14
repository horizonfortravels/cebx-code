<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * InvoiceService — Phase 3: Financial Completion
 *
 * Features:
 *   - createFromShipment(): Auto-generate invoice when shipment created
 *   - requestRefund(): Manual refund request with admin approval
 *   - approveRefund(): Admin approves refund → credit wallet
 *
 * VAT: 15% (Saudi Arabia standard)
 *
 * Usage:
 *   // In ShipmentWebController::store(), after Shipment::create():
 *   (new InvoiceService())->createFromShipment($shipment);
 *
 *   // Refund workflow:
 *   (new InvoiceService())->requestRefund($invoice, 'reason', auth()->user());
 *   (new InvoiceService())->approveRefund($invoice, auth()->user());
 */
class InvoiceService
{
    private const VAT_RATE = 0.15; // 15% Saudi VAT

    /**
     * Auto-create invoice when shipment is created
     *
     * @param Shipment $shipment The newly created shipment
     * @return Invoice|null  Returns null if shipment has no charge
     */
    public function createFromShipment(Shipment $shipment): ?Invoice
    {
        $charge = $shipment->total_charge ?? $shipment->shipping_cost ?? $shipment->price ?? 0;

        if ($charge <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($shipment, $charge) {
                $subtotal  = round($charge, 2);
                $vatAmount = round($subtotal * self::VAT_RATE, 2);
                $total     = round($subtotal + $vatAmount, 2);

                // Generate sequential invoice number per account
                $lastNumber = Invoice::where('account_id', $shipment->account_id)
                    ->whereDate('created_at', today())
                    ->count();

                $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

                $invoice = Invoice::create([
                    'account_id'     => $shipment->account_id,
                    'shipment_id'    => $shipment->id,
                    'invoice_number' => $invoiceNumber,
                    'subtotal'       => $subtotal,
                    'vat_rate'       => self::VAT_RATE * 100, // Store as percentage
                    'vat_amount'     => $vatAmount,
                    'total_amount'   => $total,
                    'currency'       => $shipment->currency ?? 'SAR',
                    'status'         => 'paid', // Auto-paid (wallet deducted at shipment creation)
                    'payment_method' => 'wallet',
                    'paid_at'        => now(),
                    'issued_at'      => now(),
                    'due_date'       => now()->addDays(30),
                    'notes'          => 'فاتورة تلقائية — شحنة ' . ($shipment->tracking_number ?? $shipment->id),
                ]);

                // Create ledger entry for audit trail
                if (class_exists(LedgerEntry::class)) {
                    try {
                        LedgerEntry::create([
                            'account_id'     => $shipment->account_id,
                            'wallet_id'      => Wallet::where('account_id', $shipment->account_id)->value('id'),
                            'type'           => 'debit',
                            'amount'         => $total,
                            'balance_after'  => Wallet::where('account_id', $shipment->account_id)->value('balance') ?? 0,
                            'description'    => 'فاتورة شحنة: ' . ($shipment->tracking_number ?? $shipment->id),
                            'reference_type' => 'Invoice',
                            'reference_id'   => $invoice->id,
                        ]);
                    } catch (\Exception $e) {
                        // LedgerEntry is optional — don't fail invoice creation
                        Log::warning('LedgerEntry creation failed', ['error' => $e->getMessage()]);
                    }
                }

                return $invoice;
            });
        } catch (\Exception $e) {
            Log::error('InvoiceService::createFromShipment failed', [
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Request a manual refund — requires admin approval
     *
     * @param Invoice $invoice  The invoice to refund
     * @param string  $reason   Reason for refund
     * @param mixed   $user     The user requesting
     * @return bool
     */
    public function requestRefund(Invoice $invoice, string $reason, $user): bool
    {
        if ($invoice->status !== 'paid') {
            return false;
        }

        if ($invoice->account_id !== $user->account_id) {
            return false;
        }

        $invoice->update([
            'status'         => 'refund_requested',
            'refund_reason'  => $reason,
            'refund_requested_by' => $user->id,
            'refund_requested_at' => now(),
        ]);

        return true;
    }

    /**
     * Admin approves refund → credits wallet
     *
     * @param Invoice $invoice  The invoice with refund_requested status
     * @param mixed   $admin    The admin approving
     * @return bool
     */
    public function approveRefund(Invoice $invoice, $admin): bool
    {
        if ($invoice->status !== 'refund_requested') {
            return false;
        }

        try {
            return DB::transaction(function () use ($invoice, $admin) {
                $refundAmount = $invoice->total_amount;

                // Credit wallet
                $wallet = Wallet::where('account_id', $invoice->account_id)->first();
                if ($wallet) {
                    $wallet->increment('balance', $refundAmount);

                    // Ledger entry for refund
                    if (class_exists(LedgerEntry::class)) {
                        try {
                            LedgerEntry::create([
                                'account_id'     => $invoice->account_id,
                                'wallet_id'      => $wallet->id,
                                'type'           => 'credit',
                                'amount'         => $refundAmount,
                                'balance_after'  => $wallet->fresh()->balance,
                                'description'    => 'استرداد فاتورة: ' . $invoice->invoice_number,
                                'reference_type' => 'Invoice',
                                'reference_id'   => $invoice->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Refund LedgerEntry failed', ['error' => $e->getMessage()]);
                        }
                    }
                }

                $invoice->update([
                    'status'             => 'refunded',
                    'refunded_at'        => now(),
                    'refunded_amount'    => $refundAmount,
                    'refund_approved_by' => $admin->id,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('InvoiceService::approveRefund failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
            return false;
        }
    }
}
