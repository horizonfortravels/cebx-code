<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CarrierDocument — FR-CR-002/005/007/008
 *
 * Stores labels and shipping documents received from carriers.
 * Supports multiple formats (PDF/ZPL) and secure access control.
 */
class CarrierDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'carrier_shipment_id', 'shipment_id', 'type', 'format',
        'mime_type', 'storage_path', 'storage_disk', 'original_filename',
        'file_size', 'checksum', 'content_base64', 'download_url',
        'download_url_expires_at', 'print_count', 'last_printed_at',
        'download_count', 'last_downloaded_at', 'fetch_attempts',
        'last_fetch_at', 'is_available',
    ];

    protected $casts = [
        'is_available'              => 'boolean',
        'download_url_expires_at'   => 'datetime',
        'last_printed_at'           => 'datetime',
        'last_downloaded_at'        => 'datetime',
        'last_fetch_at'             => 'datetime',
    ];

    protected $hidden = ['content_base64']; // Never expose raw content in API

    // ── Document Type Constants ──────────────────────────────
    const TYPE_LABEL               = 'label';
    const TYPE_COMMERCIAL_INVOICE  = 'commercial_invoice';
    const TYPE_CUSTOMS_DECLARATION = 'customs_declaration';
    const TYPE_WAYBILL             = 'waybill';
    const TYPE_RECEIPT             = 'receipt';
    const TYPE_RETURN_LABEL        = 'return_label';
    const TYPE_OTHER               = 'other';

    // ── Format Constants ─────────────────────────────────────
    const FORMAT_PDF  = 'pdf';
    const FORMAT_ZPL  = 'zpl';
    const FORMAT_PNG  = 'png';
    const FORMAT_EPL  = 'epl';
    const FORMAT_HTML = 'html';

    const MIME_TYPES = [
        'pdf'  => 'application/pdf',
        'zpl'  => 'application/x-zpl',
        'png'  => 'image/png',
        'epl'  => 'application/x-epl',
        'html' => 'text/html',
    ];

    // ── Relationships ────────────────────────────────────────

    public function carrierShipment(): BelongsTo
    {
        return $this->belongsTo(CarrierShipment::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Check if the document has stored content.
     */
    public function hasContent(): bool
    {
        return !empty($this->content_base64) || !empty($this->storage_path);
    }

    /**
     * Check if external download URL is still valid.
     */
    public function isDownloadUrlValid(): bool
    {
        if (empty($this->download_url)) {
            return false;
        }
        if ($this->download_url_expires_at && now()->isAfter($this->download_url_expires_at)) {
            return false;
        }
        return true;
    }

    /**
     * FR-CR-008: Increment print counter.
     */
    public function recordPrint(): void
    {
        $this->increment('print_count');
        $this->update(['last_printed_at' => now()]);
    }

    /**
     * FR-CR-008: Increment download counter.
     */
    public function recordDownload(): void
    {
        $this->increment('download_count');
        $this->update(['last_downloaded_at' => now()]);
    }

    /**
     * FR-CR-005: Record fetch attempt.
     */
    public function recordFetchAttempt(): void
    {
        $this->increment('fetch_attempts');
        $this->update(['last_fetch_at' => now()]);
    }

    /**
     * Get decoded content (from base64).
     */
    public function getDecodedContent(): ?string
    {
        return $this->content_base64 ? base64_decode($this->content_base64) : null;
    }

    /**
     * Set content from binary data.
     */
    public function setContentFromBinary(string $binary): void
    {
        $this->update([
            'content_base64' => base64_encode($binary),
            'file_size'      => strlen($binary),
            'checksum'       => hash('sha256', $binary),
            'is_available'   => true,
        ]);
    }

    /**
     * Get the appropriate MIME type for the format.
     */
    public static function getMimeType(string $format): string
    {
        return self::MIME_TYPES[$format] ?? 'application/octet-stream';
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeLabels($query)
    {
        return $query->where('type', self::TYPE_LABEL);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeByFormat($query, string $format)
    {
        return $query->where('format', $format);
    }
}
