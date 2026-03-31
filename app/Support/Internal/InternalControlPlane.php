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
        ],
        self::ROLE_SUPPORT => [],
        self::ROLE_OPS_READONLY => [],
        self::ROLE_CARRIER_MANAGER => [
            self::SURFACE_SMTP_SETTINGS,
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
        $resolved = [];

        foreach ($this->assignedRoleNames($user) as $roleName) {
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

    public function primaryCanonicalRole(?User $user): ?string
    {
        return $this->resolvedCanonicalRoles($user)[0] ?? null;
    }

    public function hasDeprecatedAssignments(?User $user): bool
    {
        foreach ($this->assignedRoleNames($user) as $roleName) {
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
    public function roleProfile(?User $user): array
    {
        $roleName = $this->primaryCanonicalRole($user);
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
