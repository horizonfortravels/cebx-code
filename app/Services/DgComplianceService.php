<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\DgMetadata;
use App\Models\Shipment;
use App\Models\WaiverVersion;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * DgComplianceService — FR-DG-001→009
 *
 * Manages the full lifecycle of content declarations for shipments:
 * mandatory DG flag, liability waivers, hold enforcement, versioned
 * waiver texts, append-only audit, and RBAC-aware retrieval.
 */
class DgComplianceService
{
    // ═══════════════════════════════════════════════════════════
    // FR-DG-001: Create Content Declaration
    // ═══════════════════════════════════════════════════════════

    /**
     * Create a mandatory content declaration step for a shipment.
     */
    public function createDeclaration(
        string $accountId,
        string $shipmentId,
        string $declaredBy,
        string $locale = 'ar',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ContentDeclaration {
        // Check if declaration already exists for this shipment
        $existing = ContentDeclaration::forShipment($shipmentId)
            ->where('account_id', $accountId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($accountId, $shipmentId, $declaredBy, $locale, $ipAddress, $userAgent) {
            $declaration = ContentDeclaration::create([
                'account_id'                => $accountId,
                'shipment_id'               => $shipmentId,
                'contains_dangerous_goods'  => false, // Will be set explicitly
                'dg_flag_declared'          => false,
                'status'                    => ContentDeclaration::STATUS_PENDING,
                'declared_by'               => $declaredBy,
                'ip_address'                => $ipAddress,
                'user_agent'                => $userAgent,
                'locale'                    => $locale,
                'declared_at'               => now(),
            ]);

            DgAuditLog::log(
                DgAuditLog::ACTION_CREATED,
                $accountId,
                $declaredBy,
                $declaration->id,
                $shipmentId,
                null,
                $ipAddress,
                null,
                ['status' => 'pending'],
            );

            return $declaration;
        });
    }

    public function beginShipmentDeclarationGate(
        string $accountId,
        string $shipmentId,
        string $declaredBy,
        string $locale = 'en',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): ContentDeclaration {
        $declaration = $this->createDeclaration(
            accountId: $accountId,
            shipmentId: $shipmentId,
            declaredBy: $declaredBy,
            locale: $locale,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );

        $this->syncShipmentWorkflowFromDeclaration($declaration);

        return $declaration;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-002: Set DG Flag (mandatory Yes/No)
    // ═══════════════════════════════════════════════════════════

    /**
     * Set the dangerous goods flag on a declaration.
     */
    public function setDgFlag(
        string $declarationId,
        bool $containsDg,
        string $actorId,
        ?string $ipAddress = null,
    ): ContentDeclaration {
        $declaration = ContentDeclaration::findOrFail($declarationId);
        $oldFlag = $declaration->contains_dangerous_goods;

        return DB::transaction(function () use ($declaration, $containsDg, $actorId, $ipAddress, $oldFlag) {
            $declaration->setDgFlag($containsDg);

            DgAuditLog::log(
                DgAuditLog::ACTION_DG_FLAG_SET,
                $declaration->account_id,
                $actorId,
                $declaration->id,
                $declaration->shipment_id,
                null,
                $ipAddress,
                ['contains_dangerous_goods' => $oldFlag],
                ['contains_dangerous_goods' => $containsDg, 'status' => $declaration->status],
            );

            // FR-DG-003: If DG=Yes, log hold
            if ($containsDg) {
                DgAuditLog::log(
                    DgAuditLog::ACTION_HOLD_APPLIED,
                    $declaration->account_id,
                    $actorId,
                    $declaration->id,
                    $declaration->shipment_id,
                    null,
                    $ipAddress,
                    null,
                    ['status' => ContentDeclaration::STATUS_HOLD_DG, 'hold_reason' => $declaration->hold_reason],
                );
            }

            $this->syncShipmentWorkflowFromDeclaration($declaration);

            return $declaration;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-004: Accept Liability Waiver
    // ═══════════════════════════════════════════════════════════

    /**
     * Accept the liability waiver (required when DG=No).
     */
    public function acceptWaiver(
        string $declarationId,
        string $actorId,
        ?string $locale = null,
        ?string $ipAddress = null,
    ): ContentDeclaration {
        $declaration = ContentDeclaration::findOrFail($declarationId);

        if ($declaration->contains_dangerous_goods) {
            throw new BusinessException(
                'The declaration is on hold because the shipment contains dangerous goods.',
                'ERR_DG_HOLD_REQUIRED',
                422,
                [
                    'shipment_id' => (string) $declaration->shipment_id,
                    'declaration_id' => (string) $declaration->id,
                    'next_action' => 'Contact support for manual dangerous goods handling.',
                ]
            );
        }

        $locale = $locale ?? $declaration->locale;
        $waiverVersion = WaiverVersion::getActive($locale);

        if (! $waiverVersion) {
            throw new BusinessException(
                sprintf('No active waiver version is available for locale %s.', $locale),
                'ERR_DG_WAIVER_UNAVAILABLE',
                503,
                [
                    'shipment_id' => (string) $declaration->shipment_id,
                    'declaration_id' => (string) $declaration->id,
                    'locale' => $locale,
                    'next_action' => 'Ask an administrator to publish an active legal disclaimer before continuing.',
                ]
            );
        }

        return DB::transaction(function () use ($declaration, $waiverVersion, $actorId, $ipAddress) {
            $declaration->acceptWaiver($waiverVersion);

            DgAuditLog::log(
                DgAuditLog::ACTION_WAIVER_ACCEPTED,
                $declaration->account_id,
                $actorId,
                $declaration->id,
                $declaration->shipment_id,
                null,
                $ipAddress,
                null,
                [
                    'waiver_version'  => $waiverVersion->version,
                    'waiver_hash'     => $waiverVersion->waiver_hash,
                    'status'          => $declaration->status,
                ],
            );

            // FR-DG-001: Mark completed if DG=No + waiver accepted
            if ($declaration->status === ContentDeclaration::STATUS_COMPLETED) {
                DgAuditLog::log(
                    DgAuditLog::ACTION_COMPLETED,
                    $declaration->account_id,
                    $actorId,
                    $declaration->id,
                    $declaration->shipment_id,
                );
            }

            $this->syncShipmentWorkflowFromDeclaration($declaration);

            return $declaration;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-007: Pre-flight Check for Carrier API Call
    // ═══════════════════════════════════════════════════════════

    /**
     * Check if a shipment has a valid, completed declaration.
     * Must be called before any carrier API call or payment/debit.
     *
     * @throws BusinessException with unified error codes
     */
    public function validateForIssuance(string $shipmentId, string $accountId): ContentDeclaration
    {
        $shipment = Shipment::query()
            ->where('account_id', $accountId)
            ->where('id', $shipmentId)
            ->first();

        $declaration = ContentDeclaration::forShipment($shipmentId)
            ->where('account_id', $accountId)
            ->latest()
            ->first();

        if (! $declaration) {
            throw new BusinessException(
                'A dangerous goods declaration is required before the workflow can continue.',
                'ERR_DG_DECLARATION_REQUIRED',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'shipment_status' => (string) ($shipment?->status ?? ''),
                    'next_action' => 'Complete the dangerous goods declaration step for this shipment.',
                ]
            );
        }

        if ($declaration->status === ContentDeclaration::STATUS_HOLD_DG) {
            throw new BusinessException(
                'This shipment is on hold because dangerous goods were declared.',
                'ERR_DG_HOLD_REQUIRED',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'declaration_id' => (string) $declaration->id,
                    'hold_reason' => (string) ($declaration->hold_reason ?? ''),
                    'next_action' => 'Contact support for manual dangerous goods handling.',
                ]
            );
        }

        if ($declaration->status === ContentDeclaration::STATUS_REQUIRES_ACTION) {
            throw new BusinessException(
                'The dangerous goods declaration still requires additional action.',
                'ERR_DG_REQUIRES_ACTION',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'declaration_id' => (string) $declaration->id,
                    'next_action' => 'Review the declaration and supply the required information before continuing.',
                ]
            );
        }

        if (! $declaration->dg_flag_declared) {
            throw new BusinessException(
                'You must declare whether the shipment contains dangerous goods before the workflow can continue.',
                'ERR_DG_DECLARATION_INCOMPLETE',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'declaration_id' => (string) $declaration->id,
                    'next_action' => 'Answer the dangerous goods declaration question for this shipment.',
                ]
            );
        }

        if (! $declaration->contains_dangerous_goods && ! $declaration->waiver_accepted) {
            throw new BusinessException(
                'You must accept the legal disclaimer before the workflow can continue.',
                'ERR_DG_DISCLAIMER_REQUIRED',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'declaration_id' => (string) $declaration->id,
                    'next_action' => 'Accept the legal disclaimer for a non-dangerous-goods shipment.',
                ]
            );
        }

        if (! $declaration->isReadyForIssuance()) {
            throw new BusinessException(
                'The dangerous goods declaration is not complete yet.',
                'ERR_DG_DECLARATION_INCOMPLETE',
                422,
                [
                    'shipment_id' => $shipmentId,
                    'declaration_id' => (string) $declaration->id,
                    'next_action' => 'Complete the dangerous goods declaration before continuing.',
                ]
            );
        }

        return $declaration;
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-003: Get blocked declaration info
    // ═══════════════════════════════════════════════════════════

    public function getHoldInfo(string $declarationId): array
    {
        $declaration = ContentDeclaration::findOrFail($declarationId);

        return [
            'is_blocked'   => $declaration->isBlocked(),
            'status'       => $declaration->status,
            'hold_reason'  => $declaration->hold_reason,
            'alternatives' => $declaration->isBlocked() ? [
                'contact_support' => 'تواصل مع فريق الدعم للمواد الخطرة',
                'change_carrier'  => 'اختر ناقل يدعم المواد الخطرة',
                'remove_dg_items' => 'أزل المواد الخطرة من الشحنة',
            ] : [],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-009: Save DG Metadata (optional)
    // ═══════════════════════════════════════════════════════════

    public function saveDgMetadata(string $declarationId, array $data, string $actorId, ?string $ipAddress = null): DgMetadata
    {
        $declaration = ContentDeclaration::findOrFail($declarationId);

        return DB::transaction(function () use ($declaration, $data, $actorId, $ipAddress) {
            $metadata = DgMetadata::updateOrCreate(
                ['declaration_id' => $declaration->id],
                array_filter([
                    'un_number'             => $data['un_number'] ?? null,
                    'dg_class'              => $data['dg_class'] ?? null,
                    'packing_group'         => $data['packing_group'] ?? null,
                    'proper_shipping_name'  => $data['proper_shipping_name'] ?? null,
                    'quantity'              => $data['quantity'] ?? null,
                    'quantity_unit'         => $data['quantity_unit'] ?? null,
                    'description'           => $data['description'] ?? null,
                    'additional_info'       => $data['additional_info'] ?? null,
                ], fn($v) => $v !== null),
            );

            DgAuditLog::log(
                DgAuditLog::ACTION_DG_METADATA_SAVED,
                $declaration->account_id,
                $actorId,
                $declaration->id,
                $declaration->shipment_id,
                null,
                $ipAddress,
                null,
                $data,
            );

            return $metadata;
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-006: Waiver Version Management
    // ═══════════════════════════════════════════════════════════

    public function publishWaiverVersion(string $version, string $locale, string $text, ?string $createdBy = null): WaiverVersion
    {
        return WaiverVersion::publish($version, $locale, $text, $createdBy);
    }

    public function getActiveWaiver(string $locale = 'ar'): ?WaiverVersion
    {
        return WaiverVersion::getActive($locale);
    }

    public function listWaiverVersions(string $locale = 'ar'): array
    {
        return WaiverVersion::forLocale($locale)->orderByDesc('created_at')->get()->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-008: RBAC-Aware Retrieval
    // ═══════════════════════════════════════════════════════════

    /**
     * Get declaration with detail level based on role.
     */
    public function getDeclaration(string $declarationId, bool $fullDetail = false, ?string $actorId = null): array
    {
        $declaration = ContentDeclaration::with(['dgMetadata', 'waiverVersion'])->findOrFail($declarationId);

        // Log view action
        if ($actorId) {
            DgAuditLog::log(
                DgAuditLog::ACTION_VIEWED,
                $declaration->account_id,
                $actorId,
                $declaration->id,
                $declaration->shipment_id,
            );
        }

        if ($fullDetail) {
            return $declaration->toDetailArray();
        }

        return $declaration->toSummaryArray();
    }

    /**
     * Get declaration for a specific shipment.
     */
    public function getDeclarationForShipment(string $shipmentId, string $accountId): ?ContentDeclaration
    {
        return ContentDeclaration::withoutGlobalScopes()
            ->forShipment($shipmentId)
            ->where('account_id', $accountId)
            ->latest()
            ->first();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-DG-005: Audit Log Retrieval & Export
    // ═══════════════════════════════════════════════════════════

    public function getAuditLog(string $declarationId, int $perPage = 50): LengthAwarePaginator
    {
        return DgAuditLog::forDeclaration($declarationId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getShipmentAuditLog(string $shipmentId, int $perPage = 50): LengthAwarePaginator
    {
        return DgAuditLog::forShipment($shipmentId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function exportAuditLog(string $accountId, array $filters = []): array
    {
        $query = DgAuditLog::where('account_id', $accountId);

        if (!empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        return $query->orderByDesc('created_at')->get()->toArray();
    }

    // ═══════════════════════════════════════════════════════════
    // Listing / Dashboard
    // ═══════════════════════════════════════════════════════════

    public function listDeclarations(string $accountId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = ContentDeclaration::where('account_id', $accountId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['contains_dg'])) {
            $query->where('contains_dangerous_goods', $filters['contains_dg']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    public function listBlockedShipments(string $accountId): array
    {
        return ContentDeclaration::where('account_id', $accountId)
            ->blocked()
            ->get()
            ->toArray();
    }

    private function syncShipmentWorkflowFromDeclaration(ContentDeclaration $declaration): void
    {
        $shipment = Shipment::query()
            ->where('account_id', (string) $declaration->account_id)
            ->where('id', (string) $declaration->shipment_id)
            ->first();

        if (! $shipment) {
            return;
        }

        $updates = [
            'has_dangerous_goods' => (bool) $declaration->contains_dangerous_goods,
        ];

        if ($declaration->status === ContentDeclaration::STATUS_HOLD_DG || $declaration->status === ContentDeclaration::STATUS_REQUIRES_ACTION) {
            $updates['status'] = Shipment::STATUS_REQUIRES_ACTION;
            $updates['status_reason'] = (string) ($declaration->hold_reason ?: 'Dangerous goods declaration requires manual handling.');
        } elseif ($declaration->status === ContentDeclaration::STATUS_COMPLETED && $declaration->waiver_accepted) {
            $updates['status'] = Shipment::STATUS_DECLARATION_COMPLETE;
            $updates['status_reason'] = null;
        } else {
            $updates['status'] = Shipment::STATUS_DECLARATION_REQUIRED;
            $updates['status_reason'] = 'Dangerous goods declaration and legal disclaimer must be completed before payment or issuance.';
        }

        $shipment->update($updates);
    }
}
