<?php

namespace App\Support\Internal;

use App\Models\User;

class InternalControlPlane
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_SUPPORT = 'support';
    public const ROLE_OPS_READONLY = 'ops_readonly';
    public const ROLE_CARRIER_MANAGER = 'carrier_manager';

    public const SURFACE_ADMIN_DASHBOARD = 'admin_dashboard';
    public const SURFACE_TENANT_CONTEXT = 'tenant_context';
    public const SURFACE_SMTP_SETTINGS = 'smtp_settings';
    public const SURFACE_ACCOUNT_USERS = 'account_users';
    public const SURFACE_ACCOUNT_ROLES = 'account_roles';
    public const SURFACE_ACCOUNT_REPORTS = 'account_reports';
    public const SURFACE_EXTERNAL_ACCOUNTS_INDEX = 'external_accounts_index';
    public const SURFACE_EXTERNAL_ACCOUNTS_DETAIL = 'external_accounts_detail';
    public const SURFACE_EXTERNAL_ACCOUNTS_CREATE = 'external_accounts_create';
    public const SURFACE_EXTERNAL_ACCOUNTS_UPDATE = 'external_accounts_update';
    public const SURFACE_EXTERNAL_ACCOUNTS_LIFECYCLE = 'external_accounts_lifecycle';
    public const SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS = 'external_accounts_support_actions';
    public const SURFACE_EXTERNAL_ACCOUNTS_MEMBER_ADMIN = 'external_accounts_member_admin';
    public const SURFACE_INTERNAL_KYC_INDEX = 'internal_kyc_index';
    public const SURFACE_INTERNAL_KYC_DETAIL = 'internal_kyc_detail';
    public const SURFACE_INTERNAL_KYC_REVIEW = 'internal_kyc_review';
    public const SURFACE_INTERNAL_KYC_RESTRICTIONS = 'internal_kyc_restrictions';
    public const SURFACE_INTERNAL_COMPLIANCE_INDEX = 'internal_compliance_index';
    public const SURFACE_INTERNAL_COMPLIANCE_DETAIL = 'internal_compliance_detail';
    public const SURFACE_INTERNAL_COMPLIANCE_ACTIONS = 'internal_compliance_actions';
    public const SURFACE_INTERNAL_BILLING_INDEX = 'internal_billing_index';
    public const SURFACE_INTERNAL_BILLING_DETAIL = 'internal_billing_detail';
    public const SURFACE_INTERNAL_BILLING_ACTIONS = 'internal_billing_actions';
    public const SURFACE_INTERNAL_INTEGRATIONS_INDEX = 'internal_integrations_index';
    public const SURFACE_INTERNAL_INTEGRATIONS_DETAIL = 'internal_integrations_detail';
    public const SURFACE_INTERNAL_FEATURE_FLAGS_INDEX = 'internal_feature_flags_index';
    public const SURFACE_INTERNAL_FEATURE_FLAGS_DETAIL = 'internal_feature_flags_detail';
    public const SURFACE_INTERNAL_FEATURE_FLAGS_ACTIONS = 'internal_feature_flags_actions';
    public const SURFACE_INTERNAL_API_KEYS_INDEX = 'internal_api_keys_index';
    public const SURFACE_INTERNAL_API_KEYS_DETAIL = 'internal_api_keys_detail';
    public const SURFACE_INTERNAL_API_KEYS_ACTIONS = 'internal_api_keys_actions';
    public const SURFACE_INTERNAL_WEBHOOKS_INDEX = 'internal_webhooks_index';
    public const SURFACE_INTERNAL_WEBHOOKS_DETAIL = 'internal_webhooks_detail';
    public const SURFACE_INTERNAL_WEBHOOKS_ACTIONS = 'internal_webhooks_actions';
    public const SURFACE_INTERNAL_TICKETS_INDEX = 'internal_tickets_index';
    public const SURFACE_INTERNAL_TICKETS_CREATE = 'internal_tickets_create';
    public const SURFACE_INTERNAL_TICKETS_DETAIL = 'internal_tickets_detail';
    public const SURFACE_INTERNAL_TICKETS_ACTIONS = 'internal_tickets_actions';
    public const SURFACE_INTERNAL_SHIPMENTS_INDEX = 'internal_shipments_index';
    public const SURFACE_INTERNAL_SHIPMENTS_DETAIL = 'internal_shipments_detail';
    public const SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS = 'internal_shipments_documents';
    public const SURFACE_INTERNAL_STAFF_INDEX = 'internal_staff_index';
    public const SURFACE_INTERNAL_STAFF_DETAIL = 'internal_staff_detail';
    public const SURFACE_INTERNAL_STAFF_CREATE = 'internal_staff_create';
    public const SURFACE_INTERNAL_STAFF_UPDATE = 'internal_staff_update';
    public const SURFACE_INTERNAL_STAFF_LIFECYCLE = 'internal_staff_lifecycle';
    public const SURFACE_INTERNAL_STAFF_SUPPORT_ACTIONS = 'internal_staff_support_actions';

    /**
     * @var array<int, string>
     */
    private const CANONICAL_ROLE_ORDER = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_SUPPORT,
        self::ROLE_OPS_READONLY,
        self::ROLE_CARRIER_MANAGER,
    ];

    /**
     * @var array<string, string>
     */
    private const LEGACY_ROLE_ALIASES = [
        'integration_admin' => self::ROLE_CARRIER_MANAGER,
        'ops' => self::ROLE_OPS_READONLY,
    ];

    /**
     * @var array<string, array{label: string, description: string, landing_route: string}>
     */
    private const ROLE_METADATA = [
        self::ROLE_SUPER_ADMIN => [
            'label' => 'مدير المنصة',
            'description' => 'وصول داخلي كامل لإدارة المنصة والسياق والحسابات المحددة.',
            'landing_route' => 'admin.index',
        ],
        self::ROLE_SUPPORT => [
            'label' => 'الدعم',
            'description' => 'وصول داخلي مخصص للدعم مع إبقاء أسطح الإدارة الموسعة مخفية.',
            'landing_route' => 'internal.home',
        ],
        self::ROLE_OPS_READONLY => [
            'label' => 'التشغيل للقراءة فقط',
            'description' => 'وصول داخلي للمتابعة والقراءة فقط دون أسطح إدارة أو تغيير.',
            'landing_route' => 'internal.home',
        ],
        self::ROLE_CARRIER_MANAGER => [
            'label' => 'إدارة الناقلين',
            'description' => 'وصول داخلي لإعدادات البريد والتكاملات المرتبطة بإدارة الناقلين.',
            'landing_route' => 'internal.home',
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const SURFACE_MATRIX = [
        self::ROLE_SUPER_ADMIN => [
            self::SURFACE_ADMIN_DASHBOARD,
            self::SURFACE_TENANT_CONTEXT,
            self::SURFACE_SMTP_SETTINGS,
            self::SURFACE_ACCOUNT_USERS,
            self::SURFACE_ACCOUNT_ROLES,
            self::SURFACE_ACCOUNT_REPORTS,
            self::SURFACE_EXTERNAL_ACCOUNTS_INDEX,
            self::SURFACE_EXTERNAL_ACCOUNTS_DETAIL,
            self::SURFACE_EXTERNAL_ACCOUNTS_CREATE,
            self::SURFACE_EXTERNAL_ACCOUNTS_UPDATE,
            self::SURFACE_EXTERNAL_ACCOUNTS_LIFECYCLE,
            self::SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS,
            self::SURFACE_EXTERNAL_ACCOUNTS_MEMBER_ADMIN,
            self::SURFACE_INTERNAL_KYC_INDEX,
            self::SURFACE_INTERNAL_KYC_DETAIL,
            self::SURFACE_INTERNAL_KYC_REVIEW,
            self::SURFACE_INTERNAL_KYC_RESTRICTIONS,
            self::SURFACE_INTERNAL_COMPLIANCE_INDEX,
            self::SURFACE_INTERNAL_COMPLIANCE_DETAIL,
            self::SURFACE_INTERNAL_COMPLIANCE_ACTIONS,
            self::SURFACE_INTERNAL_BILLING_INDEX,
            self::SURFACE_INTERNAL_BILLING_DETAIL,
            self::SURFACE_INTERNAL_BILLING_ACTIONS,
            self::SURFACE_INTERNAL_INTEGRATIONS_INDEX,
            self::SURFACE_INTERNAL_INTEGRATIONS_DETAIL,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_INDEX,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_DETAIL,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_ACTIONS,
            self::SURFACE_INTERNAL_API_KEYS_INDEX,
            self::SURFACE_INTERNAL_API_KEYS_DETAIL,
            self::SURFACE_INTERNAL_API_KEYS_ACTIONS,
            self::SURFACE_INTERNAL_WEBHOOKS_INDEX,
            self::SURFACE_INTERNAL_WEBHOOKS_DETAIL,
            self::SURFACE_INTERNAL_WEBHOOKS_ACTIONS,
            self::SURFACE_INTERNAL_TICKETS_INDEX,
            self::SURFACE_INTERNAL_TICKETS_CREATE,
            self::SURFACE_INTERNAL_TICKETS_DETAIL,
            self::SURFACE_INTERNAL_TICKETS_ACTIONS,
            self::SURFACE_INTERNAL_SHIPMENTS_INDEX,
            self::SURFACE_INTERNAL_SHIPMENTS_DETAIL,
            self::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
            self::SURFACE_INTERNAL_STAFF_INDEX,
            self::SURFACE_INTERNAL_STAFF_DETAIL,
            self::SURFACE_INTERNAL_STAFF_CREATE,
            self::SURFACE_INTERNAL_STAFF_UPDATE,
            self::SURFACE_INTERNAL_STAFF_LIFECYCLE,
            self::SURFACE_INTERNAL_STAFF_SUPPORT_ACTIONS,
        ],
        self::ROLE_SUPPORT => [
            self::SURFACE_EXTERNAL_ACCOUNTS_INDEX,
            self::SURFACE_EXTERNAL_ACCOUNTS_DETAIL,
            self::SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS,
            self::SURFACE_INTERNAL_KYC_INDEX,
            self::SURFACE_INTERNAL_KYC_DETAIL,
            self::SURFACE_INTERNAL_COMPLIANCE_INDEX,
            self::SURFACE_INTERNAL_COMPLIANCE_DETAIL,
            self::SURFACE_INTERNAL_BILLING_INDEX,
            self::SURFACE_INTERNAL_BILLING_DETAIL,
            self::SURFACE_INTERNAL_INTEGRATIONS_INDEX,
            self::SURFACE_INTERNAL_INTEGRATIONS_DETAIL,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_INDEX,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_DETAIL,
            self::SURFACE_INTERNAL_API_KEYS_INDEX,
            self::SURFACE_INTERNAL_API_KEYS_DETAIL,
            self::SURFACE_INTERNAL_WEBHOOKS_INDEX,
            self::SURFACE_INTERNAL_WEBHOOKS_DETAIL,
            self::SURFACE_INTERNAL_TICKETS_INDEX,
            self::SURFACE_INTERNAL_TICKETS_CREATE,
            self::SURFACE_INTERNAL_TICKETS_DETAIL,
            self::SURFACE_INTERNAL_TICKETS_ACTIONS,
            self::SURFACE_INTERNAL_SHIPMENTS_INDEX,
            self::SURFACE_INTERNAL_SHIPMENTS_DETAIL,
            self::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
            self::SURFACE_INTERNAL_STAFF_INDEX,
            self::SURFACE_INTERNAL_STAFF_DETAIL,
        ],
        self::ROLE_OPS_READONLY => [
            self::SURFACE_INTERNAL_KYC_INDEX,
            self::SURFACE_INTERNAL_KYC_DETAIL,
            self::SURFACE_INTERNAL_COMPLIANCE_INDEX,
            self::SURFACE_INTERNAL_COMPLIANCE_DETAIL,
            self::SURFACE_INTERNAL_BILLING_INDEX,
            self::SURFACE_INTERNAL_BILLING_DETAIL,
            self::SURFACE_INTERNAL_INTEGRATIONS_INDEX,
            self::SURFACE_INTERNAL_INTEGRATIONS_DETAIL,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_INDEX,
            self::SURFACE_INTERNAL_FEATURE_FLAGS_DETAIL,
            self::SURFACE_INTERNAL_API_KEYS_INDEX,
            self::SURFACE_INTERNAL_API_KEYS_DETAIL,
            self::SURFACE_INTERNAL_WEBHOOKS_INDEX,
            self::SURFACE_INTERNAL_WEBHOOKS_DETAIL,
            self::SURFACE_INTERNAL_TICKETS_INDEX,
            self::SURFACE_INTERNAL_TICKETS_DETAIL,
            self::SURFACE_INTERNAL_SHIPMENTS_INDEX,
            self::SURFACE_INTERNAL_SHIPMENTS_DETAIL,
            self::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
        ],
        self::ROLE_CARRIER_MANAGER => [
            self::SURFACE_SMTP_SETTINGS,
            self::SURFACE_INTERNAL_INTEGRATIONS_INDEX,
            self::SURFACE_INTERNAL_INTEGRATIONS_DETAIL,
            self::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
        ],
    ];

    /**
     * @var array<int, string>
     */
    private const KNOWN_SURFACES = [
        self::SURFACE_ADMIN_DASHBOARD,
        self::SURFACE_TENANT_CONTEXT,
        self::SURFACE_SMTP_SETTINGS,
        self::SURFACE_ACCOUNT_USERS,
        self::SURFACE_ACCOUNT_ROLES,
        self::SURFACE_ACCOUNT_REPORTS,
        self::SURFACE_EXTERNAL_ACCOUNTS_INDEX,
        self::SURFACE_EXTERNAL_ACCOUNTS_DETAIL,
        self::SURFACE_EXTERNAL_ACCOUNTS_CREATE,
        self::SURFACE_EXTERNAL_ACCOUNTS_UPDATE,
        self::SURFACE_EXTERNAL_ACCOUNTS_LIFECYCLE,
        self::SURFACE_EXTERNAL_ACCOUNTS_SUPPORT_ACTIONS,
        self::SURFACE_EXTERNAL_ACCOUNTS_MEMBER_ADMIN,
        self::SURFACE_INTERNAL_KYC_INDEX,
        self::SURFACE_INTERNAL_KYC_DETAIL,
        self::SURFACE_INTERNAL_KYC_REVIEW,
        self::SURFACE_INTERNAL_KYC_RESTRICTIONS,
        self::SURFACE_INTERNAL_COMPLIANCE_INDEX,
        self::SURFACE_INTERNAL_COMPLIANCE_DETAIL,
        self::SURFACE_INTERNAL_COMPLIANCE_ACTIONS,
        self::SURFACE_INTERNAL_BILLING_INDEX,
        self::SURFACE_INTERNAL_BILLING_DETAIL,
        self::SURFACE_INTERNAL_BILLING_ACTIONS,
        self::SURFACE_INTERNAL_INTEGRATIONS_INDEX,
        self::SURFACE_INTERNAL_INTEGRATIONS_DETAIL,
        self::SURFACE_INTERNAL_FEATURE_FLAGS_INDEX,
        self::SURFACE_INTERNAL_FEATURE_FLAGS_DETAIL,
        self::SURFACE_INTERNAL_FEATURE_FLAGS_ACTIONS,
        self::SURFACE_INTERNAL_API_KEYS_INDEX,
        self::SURFACE_INTERNAL_API_KEYS_DETAIL,
        self::SURFACE_INTERNAL_API_KEYS_ACTIONS,
        self::SURFACE_INTERNAL_WEBHOOKS_INDEX,
        self::SURFACE_INTERNAL_WEBHOOKS_DETAIL,
        self::SURFACE_INTERNAL_WEBHOOKS_ACTIONS,
        self::SURFACE_INTERNAL_TICKETS_INDEX,
        self::SURFACE_INTERNAL_TICKETS_CREATE,
        self::SURFACE_INTERNAL_TICKETS_DETAIL,
        self::SURFACE_INTERNAL_TICKETS_ACTIONS,
        self::SURFACE_INTERNAL_SHIPMENTS_INDEX,
        self::SURFACE_INTERNAL_SHIPMENTS_DETAIL,
        self::SURFACE_INTERNAL_SHIPMENTS_DOCUMENTS,
        self::SURFACE_INTERNAL_STAFF_INDEX,
        self::SURFACE_INTERNAL_STAFF_DETAIL,
        self::SURFACE_INTERNAL_STAFF_CREATE,
        self::SURFACE_INTERNAL_STAFF_UPDATE,
        self::SURFACE_INTERNAL_STAFF_LIFECYCLE,
        self::SURFACE_INTERNAL_STAFF_SUPPORT_ACTIONS,
    ];

    /**
     * @return array<int, string>
     */
    public function canonicalRoles(): array
    {
        return self::CANONICAL_ROLE_ORDER;
    }

    /**
     * @return array<string, string>
     */
    public function legacyRoleAliases(): array
    {
        return self::LEGACY_ROLE_ALIASES;
    }

    /**
     * @return array<int, string>
     */
    public function knownSurfaces(): array
    {
        return self::KNOWN_SURFACES;
    }

    public function isKnownSurface(string $surface): bool
    {
        return in_array($surface, self::KNOWN_SURFACES, true);
    }

    /**
     * @return array<int, string>
     */
    public function assignedRoleNames(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return array_values(array_unique($user->internalRoleNames()));
    }

    /**
     * @return array<int, string>
     */
    public function resolvedCanonicalRoles(?User $user): array
    {
        return $this->resolvedCanonicalRolesFromNames($this->assignedRoleNames($user));
    }

    public function primaryCanonicalRole(?User $user): ?string
    {
        return $this->primaryCanonicalRoleFromNames($this->assignedRoleNames($user));
    }

    public function hasDeprecatedAssignments(?User $user): bool
    {
        return $this->hasDeprecatedAssignmentsFromNames($this->assignedRoleNames($user));
    }

    /**
     * @param array<int, string> $roleNames
     * @return array<int, string>
     */
    public function resolvedCanonicalRolesFromNames(array $roleNames): array
    {
        $resolved = [];

        foreach ($roleNames as $roleName) {
            $canonical = $this->canonicalRoleForName($roleName);
            if ($canonical !== null) {
                $resolved[] = $canonical;
            }
        }

        if ($resolved === []) {
            return [];
        }

        $priority = array_flip(self::CANONICAL_ROLE_ORDER);
        usort($resolved, static function (string $left, string $right) use ($priority): int {
            return ($priority[$left] ?? PHP_INT_MAX) <=> ($priority[$right] ?? PHP_INT_MAX);
        });

        return array_values(array_unique($resolved));
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function primaryCanonicalRoleFromNames(array $roleNames): ?string
    {
        return $this->resolvedCanonicalRolesFromNames($roleNames)[0] ?? null;
    }

    /**
     * @param array<int, string> $roleNames
     */
    public function hasDeprecatedAssignmentsFromNames(array $roleNames): bool
    {
        foreach ($roleNames as $roleName) {
            $normalized = $this->normalizeRoleName($roleName);
            if ($normalized === '') {
                continue;
            }

            if (!in_array($normalized, self::CANONICAL_ROLE_ORDER, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{name: string|null, label: string, description: string, landing_route: string}
     */
    public function roleProfileForCanonicalRole(?string $roleName): array
    {
        $metadata = self::ROLE_METADATA[$roleName] ?? [
            'label' => 'وصول داخلي',
            'description' => 'تمت مواءمة هذه الواجهة على الأدوار الداخلية المعتمدة فقط.',
            'landing_route' => 'internal.home',
        ];

        return [
            'name' => $roleName,
            'label' => $metadata['label'],
            'description' => $metadata['description'],
            'landing_route' => $metadata['landing_route'],
        ];
    }

    /**
     * @return array{name: string|null, label: string, description: string, landing_route: string}
     */
    public function roleProfile(?User $user): array
    {
        return $this->roleProfileForCanonicalRole($this->primaryCanonicalRole($user));
    }

    public function landingRouteName(?User $user): string
    {
        return $this->roleProfile($user)['landing_route'];
    }

    public function landingActionLabel(?User $user): string
    {
        return $this->landingRouteName($user) === 'admin.index'
            ? 'العودة إلى لوحة الإدارة'
            : 'الانتقال إلى المساحة الداخلية';
    }

    public function displayRoleName(?User $user): string
    {
        return $this->primaryCanonicalRole($user) ?? 'internal';
    }

    public function canSeeSurface(?User $user, string $surface): bool
    {
        $roleName = $this->primaryCanonicalRole($user);
        if ($roleName === null) {
            return false;
        }

        return in_array($surface, self::SURFACE_MATRIX[$roleName] ?? [], true);
    }

    private function canonicalRoleForName(string $roleName): ?string
    {
        $normalized = $this->normalizeRoleName($roleName);
        if ($normalized === '') {
            return null;
        }

        if (in_array($normalized, self::CANONICAL_ROLE_ORDER, true)) {
            return $normalized;
        }

        return self::LEGACY_ROLE_ALIASES[$normalized] ?? null;
    }

    private function normalizeRoleName(string $roleName): string
    {
        return strtolower(trim($roleName));
    }
}
