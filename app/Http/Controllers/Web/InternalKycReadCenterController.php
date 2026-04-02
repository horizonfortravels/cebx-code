<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Services\InternalKycOperationalEffectService;
use App\Services\InternalKycRestrictionAdminService;
use App\Support\Internal\InternalControlPlane;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InternalKycReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalKycRestrictionAdminService $restrictionAdminService,
        private readonly InternalKycOperationalEffectService $operationalEffectService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $this->normalizedFilter((string) $request->query('type', ''), ['individual', 'organization']),
            'status' => $this->normalizedFilter((string) $request->query('status', ''), [
                KycVerification::STATUS_UNVERIFIED,
                KycVerification::STATUS_PENDING,
                KycVerification::STATUS_APPROVED,
                KycVerification::STATUS_REJECTED,
                KycVerification::STATUS_EXPIRED,
            ]),
            'restriction' => $this->normalizedFilter((string) $request->query('restriction', ''), ['restricted', 'clear']),
        ];

        $restrictedStatuses = $this->restrictedStatuses();
        $baseQuery = $this->kycQueueQuery($filters, $restrictedStatuses);
        $stats = $this->queueStats(clone $baseQuery, $restrictedStatuses);

        $accounts = $baseQuery
            ->orderByRaw($this->statusOrderExpression())
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $accounts->setCollection(
            $accounts->getCollection()->map(
                fn (Account $account): array => $this->buildQueueRow($account, $restrictedStatuses)
            )
        );

        return view('pages.admin.kyc-index', [
            'cases' => $accounts,
            'stats' => $stats,
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function show(Request $request, string $account, InternalControlPlane $controlPlane): View
    {
        $accountModel = Account::query()
            ->withoutGlobalScopes()
            ->with($this->eagerLoadRelations())
            ->withCount([
                'shipments as kyc_blocked_shipments_count' => function ($query): void {
                    $query->withoutGlobalScopes()->where('status', Shipment::STATUS_KYC_BLOCKED);
                },
            ])
            ->findOrFail($account);

        $owner = $this->resolveExternalOwner($accountModel);
        $kyc = $this->buildKycSummary($accountModel);
        $documents = $this->documentSummaries($accountModel);
        $restrictions = $this->restrictionSummaries($kyc['status']);
        $auditEntries = $this->auditEntries($accountModel);
        $blockedShipmentsCount = (int) ($accountModel->kyc_blocked_shipments_count ?? 0);
        $operationalEffect = $this->operationalEffectService->summarize(
            $accountModel,
            $kyc['status'],
            $kyc['capabilities'],
            $blockedShipmentsCount,
        );
        $canReviewKyc = $this->canReviewKyc($request, $controlPlane);
        $canTakeDecision = $canReviewKyc && $kyc['status'] === KycVerification::STATUS_PENDING;
        $canManageRestrictions = $this->canManageRestrictions($request, $controlPlane, $kyc['status']);
        $canViewAccount = $this->canViewAccount($request, $controlPlane);

        return view('pages.admin.kyc-show', [
            'account' => $accountModel,
            'owner' => $owner,
            'accountTypeLabel' => $this->accountTypeLabel((string) $accountModel->type),
            'accountStatusLabel' => $this->accountStatusLabel((string) ($accountModel->status ?? 'pending')),
            'kyc' => $kyc,
            'documents' => $documents,
            'restrictions' => $restrictions,
            'operationalEffect' => $operationalEffect,
            'shipmentLimitSummary' => $this->shipmentLimitSummary($accountModel, $kyc),
            'recentImpactedShipments' => $this->operationalEffectService->recentImpactedShipments($accountModel),
            'auditEntries' => $auditEntries,
            'hasDocumentVisibility' => $this->canViewDocumentSummaries($request),
            'canViewAccount' => $canViewAccount,
            'canReviewKyc' => $canReviewKyc,
            'canTakeDecision' => $canTakeDecision,
            'canManageRestrictions' => $canManageRestrictions,
            'restrictionControls' => $this->restrictionAdminService->controlStatesForStatus($kyc['status']),
        ]);
    }

    /**
     * @param array{q: string, type: string, status: string, restriction: string} $filters
     * @param array<int, string> $restrictedStatuses
     */
    private function kycQueueQuery(array $filters, array $restrictedStatuses): Builder
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->with($this->eagerLoadRelations())
            ->withCount([
                'shipments as kyc_blocked_shipments_count' => function ($query): void {
                    $query->withoutGlobalScopes()->where('status', Shipment::STATUS_KYC_BLOCKED);
                },
            ])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $search = '%' . $filters['q'] . '%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('users', function ($userQuery) use ($search): void {
                            $userQuery->withoutGlobalScopes()
                                ->where(function (Builder $userInner) use ($search): void {
                                    $userInner->where('name', 'like', $search)
                                        ->orWhere('email', 'like', $search);
                                });

                            if (Schema::hasColumn('users', 'user_type')) {
                                $userQuery->where('user_type', 'external');
                            }
                        })
                        ->orWhereHas('organizationProfile', function (Builder $orgQuery) use ($search): void {
                            $orgQuery->where('legal_name', 'like', $search)
                                ->orWhere('trade_name', 'like', $search)
                                ->orWhere('registration_number', 'like', $search);
                        });
                });
            })
            ->when($filters['type'] !== '', static function (Builder $query) use ($filters): void {
                $query->where('type', $filters['type']);
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                if ($filters['status'] === KycVerification::STATUS_EXPIRED) {
                    $query->whereHas('kycVerifications', static function (Builder $kycQuery): void {
                        $kycQuery->where('status', KycVerification::STATUS_EXPIRED);
                    });

                    return;
                }

                if (Schema::hasColumn('accounts', 'kyc_status')) {
                    $query->where('kyc_status', AccountKycStatusMapper::fromVerificationStatus($filters['status']));

                    return;
                }

                $query->whereHas('kycVerifications', static function (Builder $kycQuery) use ($filters): void {
                    $kycQuery->where('status', $filters['status']);
                });
            })
            ->when($filters['restriction'] !== '' && $restrictedStatuses !== [], function (Builder $query) use ($filters, $restrictedStatuses): void {
                if (!Schema::hasColumn('accounts', 'kyc_status')) {
                    return;
                }

                if ($filters['restriction'] === 'restricted') {
                    $query->whereIn('kyc_status', $restrictedStatuses);

                    return;
                }

                $query->where(function (Builder $inner) use ($restrictedStatuses): void {
                    $inner->whereNull('kyc_status')
                        ->orWhereNotIn('kyc_status', $restrictedStatuses);
                });
            });
    }

    /**
     * @return array<int, string>
     */
    private function eagerLoadRelations(): array
    {
        return [
            'organizationProfile',
            'kycVerification.reviewer',
            'users' => function ($query): void {
                $query->withoutGlobalScopes()
                    ->orderByDesc('is_owner')
                    ->orderBy('name');

                if (Schema::hasColumn('users', 'user_type')) {
                    $query->where('user_type', 'external');
                }
            },
        ];
    }

    /**
     * @param array<int, string> $restrictedStatuses
     * @return array{total: int, pending: int, rejected: int, restricted: int}
     */
    private function queueStats(Builder $baseQuery, array $restrictedStatuses): array
    {
        $all = (clone $baseQuery)->get();

        return [
            'total' => $all->count(),
            'pending' => $all->filter(fn (Account $account): bool => $this->buildKycSummary($account)['status'] === KycVerification::STATUS_PENDING)->count(),
            'rejected' => $all->filter(fn (Account $account): bool => $this->buildKycSummary($account)['status'] === KycVerification::STATUS_REJECTED)->count(),
            'restricted' => $all->filter(fn (Account $account): bool => in_array($this->buildKycSummary($account)['status'], $restrictedStatuses, true))->count(),
        ];
    }

    /**
     * @param array<int, string> $restrictedStatuses
     * @return array<string, mixed>
     */
    private function buildQueueRow(Account $account, array $restrictedStatuses): array
    {
        $owner = $this->resolveExternalOwner($account);
        $kyc = $this->buildKycSummary($account);
        $documentCounts = $this->documentCounts($account, $kyc);
        $organizationProfile = $account->organizationProfile;

        return [
            'account' => $account,
            'owner' => $owner,
            'kyc' => $kyc,
            'typeLabel' => $this->accountTypeLabel((string) $account->type),
            'isRestricted' => in_array($kyc['status'], $restrictedStatuses, true),
            'organizationSummary' => $account->isOrganization()
                ? trim((string) ($organizationProfile?->legal_name ?: $organizationProfile?->trade_name ?: 'ملف المؤسسة غير مكتمل'))
                : null,
            'documentCounts' => $documentCounts,
            'shipmentLimitSummary' => $this->operationalEffectService->summarize(
                $account,
                $kyc['status'],
                $kyc['capabilities'],
                (int) ($account->kyc_blocked_shipments_count ?? 0),
            )['queue_summary'],
            'reviewSummary' => $this->queueReviewSummary($kyc),
        ];
    }

    /**
     * @return array{
     *   status: string,
     *   label: string,
     *   account_status: string,
     *   verification_type: string,
     *   verification_level: string,
     *   description: string,
     *   submitted_at: string|null,
     *   reviewed_at: string|null,
     *   expires_at: string|null,
     *   rejection_reason: string|null,
     *   review_notes_excerpt: string|null,
     *   reviewer_name: string|null,
     *   review_count: int,
     *   capabilities: array<string, mixed>,
     *   required_documents: array<int, array{key: string, label: string}>,
     *   submitted_documents: array<int, array{key: string, label: string}>
     * }
     */
    private function buildKycSummary(Account $account): array
    {
        /** @var KycVerification|null $verification */
        $verification = $account->kycVerification;
        $status = $verification?->status
            ?? AccountKycStatusMapper::toVerificationStatus((string) ($account->kyc_status ?? ''));

        $status = trim((string) $status);
        $display = $verification?->statusDisplay() ?? $this->fallbackKycDisplay($status);
        $capabilities = $verification?->capabilities() ?? $this->fallbackCapabilities($status);
        $reviewNotes = trim((string) ($verification?->review_notes ?? ''));

        return [
            'status' => $status,
            'label' => (string) ($display['label'] ?? ucfirst($status)),
            'account_status' => (string) ($account->kyc_status ?? AccountKycStatusMapper::fromVerificationStatus($status)),
            'verification_type' => (string) ($verification?->verification_type ?: $account->type ?: 'individual'),
            'verification_level' => (string) ($verification?->verification_level ?: 'basic'),
            'description' => trim((string) ($capabilities['message'] ?? 'لا توجد حالة تحقق محدثة لهذا الحساب بعد.')),
            'submitted_at' => optional($verification?->submitted_at)->format('Y-m-d H:i'),
            'reviewed_at' => optional($verification?->reviewed_at)->format('Y-m-d H:i'),
            'expires_at' => optional($verification?->expires_at)->format('Y-m-d'),
            'rejection_reason' => $verification?->rejection_reason,
            'review_notes_excerpt' => $reviewNotes !== '' ? Str::limit($reviewNotes, 180) : null,
            'reviewer_name' => $verification?->reviewer?->name,
            'review_count' => (int) ($verification?->review_count ?? 0),
            'capabilities' => $capabilities,
            'required_documents' => $this->normalizeDocumentLabels(
                $verification?->required_documents ?? KycVerification::requiredDocumentsFor((string) ($account->type ?: 'individual'))
            ),
            'submitted_documents' => $this->normalizeDocumentLabels($verification?->submitted_documents ?? []),
        ];
    }

    /**
     * @param array{
     *   required_documents: array<int, array{key: string, label: string}>,
     *   submitted_documents: array<int, array{key: string, label: string}>
     * } $kyc
     * @return array{required: int, submitted: int}
     */
    private function documentCounts(Account $account, array $kyc): array
    {
        $submitted = count($kyc['submitted_documents']);

        if ($account->kycVerification) {
            $submitted = max(
                $submitted,
                $account->kycVerification->documents()->notPurged()->count()
            );
        }

        return [
            'required' => count($kyc['required_documents']),
            'submitted' => $submitted,
        ];
    }

    private function resolveExternalOwner(Account $account): ?User
    {
        $users = $account->users instanceof Collection
            ? $account->users
            : collect();

        /** @var User|null $owner */
        $owner = $users->first(static fn (User $user): bool => (bool) ($user->is_owner ?? false));

        if ($owner instanceof User) {
            return $owner;
        }

        $first = $users->first();

        return $first instanceof User ? $first : null;
    }

    private function canReviewKyc(Request $request, InternalControlPlane $controlPlane): bool
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return false;
        }

        return $user->hasPermission('kyc.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_REVIEW);
    }

    /**
     * @return Collection<int, array{type_label: string, filename: string, mime_type: string, human_size: string, uploaded_by: string, uploaded_at: string|null, sensitivity_label: string}>
     */
    private function documentSummaries(Account $account): Collection
    {
        if (!$account->kycVerification || !Schema::hasTable('kyc_documents')) {
            return collect();
        }

        return $account->kycVerification
            ->documents()
            ->notPurged()
            ->with('uploader:id,name,email')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function (KycDocument $document): array {
                return [
                    'type_label' => $this->documentLabel((string) $document->document_type),
                    'filename' => (string) $document->original_filename,
                    'mime_type' => (string) $document->mime_type,
                    'human_size' => $document->humanFileSize(),
                    'uploaded_by' => (string) ($document->uploader?->name ?? 'مستخدم غير معروف'),
                    'uploaded_at' => optional($document->created_at)->format('Y-m-d H:i'),
                    'sensitivity_label' => $document->isSensitive() ? 'وثيقة حساسة' : 'وثيقة عادية',
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{name: string, description: string, summary: string}>
     */
    private function restrictionSummaries(string $status): Collection
    {
        if (!Schema::hasTable('verification_restrictions')) {
            return collect();
        }

        return VerificationRestriction::getForStatus($status)
            ->map(function (VerificationRestriction $restriction): array {
                $summary = $restriction->restriction_type === VerificationRestriction::TYPE_QUOTA_LIMIT
                    ? 'حد تشغيلي: ' . number_format((float) ($restriction->quota_value ?? 0))
                    : 'تعليق على الخاصية: ' . ($restriction->feature_key ?: 'غير محددة');

                return [
                    'name' => (string) $restriction->name,
                    'description' => (string) ($restriction->description ?: 'لا يوجد وصف إضافي.'),
                    'summary' => $summary,
                ];
            })
            ->values();
    }

    /**
     * @return array{headline: string, detail: string}
     */
    private function shipmentLimitSummary(Account $account, array $kyc): array
    {
        $capabilities = $kyc['capabilities'];
        $blockedShipments = (int) ($account->kyc_blocked_shipments_count ?? 0);

        $headline = match (true) {
            $blockedShipments > 0 => 'توجد شحنات معلقة بسبب التحقق',
            ($capabilities['can_ship_international'] ?? false) === false => 'الشحن الدولي مقيد حاليًا',
            ($capabilities['daily_shipment_limit'] ?? null) !== null => 'يوجد حد تشغيلي يومي',
            default => 'لا توجد قيود تشغيلية حرجة ظاهرة',
        };

        $parts = [];

        if (($capabilities['shipping_limit'] ?? null) !== null) {
            $parts[] = 'حد الشحن: ' . number_format((float) $capabilities['shipping_limit']);
        }

        if (($capabilities['daily_shipment_limit'] ?? null) !== null) {
            $parts[] = 'حد يومي: ' . number_format((float) $capabilities['daily_shipment_limit']);
        }

        if ($blockedShipments > 0) {
            $parts[] = 'شحنات محجوبة: ' . number_format($blockedShipments);
        }

        if ($parts === []) {
            $parts[] = 'لا توجد مؤشرات تشغيلية إضافية مرتبطة بالتحقق على هذا الحساب الآن.';
        }

        return [
            'headline' => $headline,
            'detail' => implode(' • ', $parts),
        ];
    }

    /**
     * @return Collection<int, array{action: string, severity: string, actor: string, at: string|null}>
     */
    private function auditEntries(Account $account): Collection
    {
        if (!Schema::hasTable('audit_logs') || !Schema::hasColumn('audit_logs', 'category') || !Schema::hasColumn('audit_logs', 'account_id')) {
            return collect();
        }

        return AuditLog::query()
            ->withoutGlobalScopes()
            ->with('performer:id,name,email')
            ->where('account_id', (string) $account->id)
            ->where('category', AuditLog::CATEGORY_KYC)
            ->latest()
            ->limit(8)
            ->get()
            ->map(function (AuditLog $entry): array {
                return [
                    'action' => $this->auditActionLabel((string) $entry->action),
                    'severity' => (string) ($entry->severity ?? AuditLog::SEVERITY_INFO),
                    'actor' => (string) ($entry->performer?->name ?? 'النظام'),
                    'at' => optional($entry->created_at)->format('Y-m-d H:i'),
                ];
            })
            ->values();
    }

    private function canViewDocumentSummaries(Request $request): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user?->hasPermission('kyc.documents') ?? false;
    }

    private function canViewAccount(Request $request, InternalControlPlane $controlPlane): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        return $user instanceof User
            && $user->hasPermission('accounts.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function canManageRestrictions(Request $request, InternalControlPlane $controlPlane, string $status): bool
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user instanceof User) {
            return false;
        }

        if (!in_array($status, InternalKycRestrictionAdminService::mutableStatuses(), true)) {
            return false;
        }

        return $user->hasPermission('kyc.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_KYC_RESTRICTIONS);
    }

    /**
     * @param array<int, string> $restrictedStatuses
     * @return string
     */
    private function queueShipmentLimitSummary(array $kyc, int $blockedShipments): string
    {
        $capabilities = $kyc['capabilities'];

        if ($blockedShipments > 0) {
            return 'شحنات محجوبة: ' . number_format($blockedShipments);
        }

        if (($capabilities['daily_shipment_limit'] ?? null) !== null) {
            return 'حد يومي: ' . number_format((float) $capabilities['daily_shipment_limit']);
        }

        if (($capabilities['shipping_limit'] ?? null) !== null) {
            return 'حد شحن: ' . number_format((float) $capabilities['shipping_limit']);
        }

        return 'لا يوجد حد تشغيلي ظاهر';
    }

    /**
     * @param array{
     *   submitted_at: string|null,
     *   reviewed_at: string|null,
     *   reviewer_name: string|null,
     *   review_notes_excerpt: string|null
     * } $kyc
     */
    private function queueReviewSummary(array $kyc): string
    {
        if ($kyc['reviewed_at']) {
            return 'آخر مراجعة: ' . $kyc['reviewed_at'] . ($kyc['reviewer_name'] ? ' • ' . $kyc['reviewer_name'] : '');
        }

        if ($kyc['submitted_at']) {
            return 'تاريخ الإرسال: ' . $kyc['submitted_at'];
        }

        return 'لا توجد مراجعة داخلية مسجلة بعد';
    }

    /**
     * @return array<int, string>
     */
    private function restrictedStatuses(): array
    {
        if (!Schema::hasTable('verification_restrictions')) {
            return [];
        }

        return VerificationRestriction::query()
            ->active()
            ->get()
            ->flatMap(static fn (VerificationRestriction $restriction): array => $restriction->applies_to_statuses ?? [])
            ->filter(static fn ($status): bool => is_string($status) && trim($status) !== '')
            ->map(static fn (string $status): string => trim($status))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            KycVerification::STATUS_UNVERIFIED => 'غير موثق',
            KycVerification::STATUS_PENDING => 'قيد المراجعة',
            KycVerification::STATUS_APPROVED => 'موثق',
            KycVerification::STATUS_REJECTED => 'مرفوض',
            KycVerification::STATUS_EXPIRED => 'منتهي',
        ];
    }

    private function normalizedFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function fallbackKycDisplay(string $status): array
    {
        return match ($status) {
            KycVerification::STATUS_PENDING => ['label' => 'قيد المراجعة'],
            KycVerification::STATUS_APPROVED => ['label' => 'موثق'],
            KycVerification::STATUS_REJECTED => ['label' => 'مرفوض'],
            KycVerification::STATUS_EXPIRED => ['label' => 'منتهي'],
            default => ['label' => 'غير موثق'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackCapabilities(string $status): array
    {
        return match ($status) {
            KycVerification::STATUS_APPROVED => [
                'can_ship_international' => true,
                'can_use_cod' => true,
                'daily_shipment_limit' => null,
                'shipping_limit' => null,
                'message' => 'الحساب موثق بالكامل ولا توجد قيود تشغيلية من جهة التحقق.',
            ],
            KycVerification::STATUS_PENDING => [
                'can_ship_international' => false,
                'can_use_cod' => false,
                'daily_shipment_limit' => 10,
                'shipping_limit' => 50,
                'message' => 'الحساب قيد المراجعة وبعض الميزات التشغيلية ما زالت محدودة.',
            ],
            KycVerification::STATUS_REJECTED => [
                'can_ship_international' => false,
                'can_use_cod' => false,
                'daily_shipment_limit' => 5,
                'shipping_limit' => 10,
                'message' => 'تم رفض طلب التحقق الحالي ويستلزم ذلك متابعة قبل استعادة كامل الميزات.',
            ],
            default => [
                'can_ship_international' => false,
                'can_use_cod' => false,
                'daily_shipment_limit' => 3,
                'shipping_limit' => 5,
                'message' => 'ما زال الحساب يعمل ضمن حدود تحقق أولية حتى اكتمال التوثيق.',
            ],
        };
    }

    /**
     * @param array<string, mixed>|null $documents
     * @return array<int, array{key: string, label: string}>
     */
    private function normalizeDocumentLabels(?array $documents): array
    {
        if (!$documents) {
            return [];
        }

        $items = [];

        foreach ($documents as $key => $value) {
            if (is_string($key) && $key !== '') {
                $items[] = [
                    'key' => $key,
                    'label' => is_string($value) && !str_contains($value, '/') ? $value : $this->documentLabel($key),
                ];

                continue;
            }

            if (is_string($value) && $value !== '') {
                $items[] = [
                    'key' => $value,
                    'label' => $this->documentLabel($value),
                ];
            }
        }

        return $items;
    }

    private function documentLabel(string $documentType): string
    {
        return match ($documentType) {
            'national_id' => 'الهوية الوطنية',
            'passport' => 'جواز السفر',
            'address_proof', 'utility_bill' => 'إثبات العنوان',
            'commercial_registration', 'commercial_register' => 'السجل التجاري',
            'tax_certificate' => 'شهادة الضريبة',
            'bank_statement' => 'كشف حساب بنكي',
            default => Str::headline(str_replace('_', ' ', $documentType)),
        };
    }

    private function accountTypeLabel(string $type): string
    {
        return $type === 'organization' ? 'حساب منظمة' : 'حساب فردي';
    }

    private function accountStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'نشط',
            'suspended' => 'موقوف',
            'closed' => 'مغلق',
            default => 'قيد التفعيل',
        };
    }

    private function statusOrderExpression(): string
    {
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            return "case accounts.kyc_status when 'pending' then 0 when 'rejected' then 1 when 'not_submitted' then 2 when 'verified' then 3 else 4 end";
        }

        return 'accounts.updated_at desc';
    }

    private function auditActionLabel(string $action): string
    {
        return match ($action) {
            'kyc.approved', 'kyc.case_approved' => 'تم اعتماد حالة التحقق',
            'kyc.rejected', 'kyc.case_rejected' => 'تم رفض حالة التحقق',
            'kyc.resubmitted', 'kyc.case_submitted' => 'تم تحديث ملف التحقق',
            'kyc.document_uploaded' => 'تم رفع وثيقة تحقق',
            'kyc.documents_listed' => 'تمت مراجعة ملخص الوثائق',
            'kyc.document_accessed' => 'تم الوصول إلى وثيقة تحقق',
            'kyc.restriction_updated' => 'تم تحديث قيد تحقق تشغيلي',
            'kyc.restriction_disabled' => 'تم تعطيل قيد تحقق تشغيلي',
            'kyc.expired' => 'انتهت صلاحية التحقق',
            default => $action,
        };
    }
}
