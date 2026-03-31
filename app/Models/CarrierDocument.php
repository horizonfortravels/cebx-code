<?php

namespace App\Models;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * CarrierDocument — FR-CR-002/005/007/008
 *
 * Stores labels and shipping documents received from carriers.
 * Supports multiple formats (PDF/ZPL) and secure access control.
 */
class CarrierDocument extends Model
{
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            $document->normalizeStorageContract();
            $document->assertValidStorageContract();
        });
    }

    protected $fillable = [
        'carrier_shipment_id', 'shipment_id', 'type', 'format',
        'mime_type', 'storage_path', 'storage_disk', 'original_filename',
        'file_size', 'checksum', 'content_base64', 'download_url',
        'download_url_expires_at', 'print_count', 'last_printed_at',
        'download_count', 'last_downloaded_at', 'fetch_attempts',
        'last_fetch_at', 'is_available', 'carrier_code', 'source',
        'retrieval_mode', 'carrier_metadata',
    ];

    protected $casts = [
        'is_available'              => 'boolean',
        'download_url_expires_at'   => 'datetime',
        'last_printed_at'           => 'datetime',
        'last_downloaded_at'        => 'datetime',
        'last_fetch_at'             => 'datetime',
        'carrier_metadata'          => 'array',
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

    const SOURCE_CARRIER           = 'carrier';

    const RETRIEVAL_INLINE         = 'inline';
    const RETRIEVAL_URL            = 'url';
    const RETRIEVAL_STORED_OBJECT  = 'stored_object';

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

    public function resolvedRetrievalMode(): string
    {
        return $this->inferRetrievalMode() ?? self::RETRIEVAL_INLINE;
    }

    public function hasValidUrl(): bool
    {
        return $this->isDownloadUrlValid();
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
        if ($this->content_base64) {
            return base64_decode($this->content_base64);
        }

        if ($this->storage_path) {
            $disk = $this->storage_disk ?: config('filesystems.default', 'local');
            if (Storage::disk($disk)->exists($this->storage_path)) {
                return Storage::disk($disk)->get($this->storage_path);
            }
        }

        return null;
    }

    /**
     * Set content from binary data.
     */
    public function setContentFromBinary(string $binary): void
    {
        $this->update([
            'retrieval_mode' => self::RETRIEVAL_INLINE,
            'storage_disk' => null,
            'storage_path' => null,
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

    private function normalizeStorageContract(): void
    {
        $this->content_base64 = $this->normalizeNullableString($this->content_base64);
        $this->storage_path = $this->normalizeNullableString($this->storage_path);
        $this->storage_disk = $this->normalizeNullableString($this->storage_disk);
        $this->download_url = $this->normalizeNullableString($this->download_url);
        $this->retrieval_mode = $this->inferRetrievalMode();

        if ($this->storage_path === null) {
            $this->storage_disk = null;
        }
    }

    private function assertValidStorageContract(): void
    {
        $hasInlineContent = $this->content_base64 !== null;
        $hasStoredObject = $this->storage_path !== null;
        $hasUrl = $this->download_url !== null;
        $mode = $this->resolvedRetrievalMode();

        if (! $hasInlineContent && ! $hasStoredObject && ! $hasUrl) {
            throw new InvalidArgumentException('CarrierDocument requires inline content, stored-object coordinates, or a download URL.');
        }

        if ($mode === self::RETRIEVAL_INLINE) {
            if (! $hasInlineContent || $hasStoredObject) {
                throw new InvalidArgumentException('Inline carrier documents cannot also use stored-object fields.');
            }

            return;
        }

        if ($mode === self::RETRIEVAL_STORED_OBJECT) {
            if (! $hasStoredObject || $this->storage_disk === null || $hasInlineContent) {
                throw new InvalidArgumentException('Stored-object carrier documents require storage_path + storage_disk and cannot also store inline content.');
            }

            return;
        }

        if (! $hasUrl || $hasStoredObject || $hasInlineContent) {
            throw new InvalidArgumentException('URL carrier documents must stay URL-only.');
        }
    }

    private function inferRetrievalMode(): ?string
    {
        if ($this->storage_path !== null) {
            return self::RETRIEVAL_STORED_OBJECT;
        }

        if ($this->content_base64 !== null) {
            return self::RETRIEVAL_INLINE;
        }

        if ($this->download_url !== null) {
            return self::RETRIEVAL_URL;
        }

        return null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized === '' ? null : $normalized;
    }
}
