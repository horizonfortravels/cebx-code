<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * KycAuditLog — FR-KYC-008
 */
class KycAuditLog extends Model
{
    use HasUuids;

    protected $fillable = [
        'case_id', 'document_id', 'actor_id', 'actor_type', 'action',
        'ip_address', 'metadata', 'result',
    ];

    protected $casts = ['metadata' => 'array'];

    // Actions
    const ACTION_DOCUMENT_UPLOAD   = 'document_upload';
    const ACTION_DOCUMENT_VIEW     = 'document_view';
    const ACTION_DOCUMENT_DOWNLOAD = 'document_download';
    const ACTION_CASE_SUBMIT       = 'case_submit';
    const ACTION_CASE_REVIEW       = 'case_review';
    const ACTION_STATUS_CHANGE     = 'status_change';
    const ACTION_DECISION          = 'decision';
    const ACTION_RESTRICTION_CHECK = 'restriction_check';

    public function verificationCase(): BelongsTo { return $this->belongsTo(VerificationCase::class, 'case_id'); }
    public function document(): BelongsTo { return $this->belongsTo(VerificationDocument::class, 'document_id'); }

    public static function log(
        string  $action,
        string  $actorId,
        string  $actorType = 'user',
        ?string $caseId = null,
        ?string $documentId = null,
        ?string $ipAddress = null,
        array   $metadata = [],
        string  $result = 'success'
    ): self {
        return self::create(compact(
            'action', 'actorId', 'actorType', 'caseId', 'documentId',
            'ipAddress', 'metadata', 'result'
        ) + [
            'case_id'     => $caseId,
            'document_id' => $documentId,
            'actor_id'    => $actorId,
            'actor_type'  => $actorType,
            'action'      => $action,
            'ip_address'  => $ipAddress,
            'metadata'    => $metadata,
            'result'      => $result,
        ]);
    }
}
