<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\BillingWallet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class E2EUserMatrixSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'Password123!';
    private const DEFAULT_LOCALE = 'en';
    private const DEFAULT_TIMEZONE = 'UTC';

    public function run(): void
    {
        $passwordHash = Hash::make(self::DEFAULT_PASSWORD);
        $this->cleanupLegacyMatrixUsers();
        $this->cleanupLegacyInternalMatrixRoles();

        $accounts = [
            'a' => $this->upsertAccount(
                slug: 'e2e-account-a',
                name: 'E2E Account A',
                requestedType: 'individual'
            ),
            'b' => $this->upsertAccount(
                slug: 'e2e-account-b',
                name: 'E2E Account B',
                requestedType: 'individual'
            ),
            'c' => $this->upsertAccount(
                slug: 'e2e-account-c',
                name: 'E2E Account C',
                requestedType: 'organization'
            ),
            'd' => $this->upsertAccount(
                slug: 'e2e-account-d',
                name: 'E2E Account D',
                requestedType: 'organization'
            ),
        ];

        // Ensure roles/permissions exist for the newly created accounts.
        $this->call(RolesAndPermissionsSeeder::class);

        $externalUsers = [
            'a' => [
                'primary' => $this->upsertExternalUser(
                    account: $accounts['a'],
                    name: 'E2E A Individual User',
                    email: 'e2e.a.individual@example.test',
                    passwordHash: $passwordHash,
                    status: 'active',
                    isOwner: true
                ),
            ],
            'b' => [
                'primary' => $this->upsertExternalUser(
                    account: $accounts['b'],
                    name: 'E2E B Individual User',
                    email: 'e2e.b.individual@example.test',
                    passwordHash: $passwordHash,
                    status: 'active',
                    isOwner: true
                ),
            ],
            'c' => [
                'organization_owner' => $this->upsertExternalUser(
                    account: $accounts['c'],
                    name: 'E2E C Organization Owner',
                    email: 'e2e.c.organization_owner@example.test',
                    passwordHash: $passwordHash,
                    status: 'active',
                    isOwner: true
                ),
                'organization_admin' => $this->upsertExternalUser(
                    account: $accounts['c'],
                    name: 'E2E C Organization Admin',
                    email: 'e2e.c.organization_admin@example.test',
                    passwordHash: $passwordHash,
                    status: 'active'
                ),
                'staff' => $this->upsertExternalUser(
                    account: $accounts['c'],
                    name: 'E2E C Staff',
                    email: 'e2e.c.staff@example.test',
                    passwordHash: $passwordHash,
                    status: 'active'
                ),
                'suspended' => $this->upsertExternalUser(
                    account: $accounts['c'],
                    name: 'E2E C Suspended User',
                    email: 'e2e.c.suspended@example.test',
                    passwordHash: $passwordHash,
                    status: 'suspended'
                ),
                'disabled' => $this->upsertExternalUser(
                    account: $accounts['c'],
                    name: 'E2E C Disabled User',
                    email: 'e2e.c.disabled@example.test',
                    passwordHash: $passwordHash,
                    status: 'disabled'
                ),
            ],
            'd' => [
                'organization_owner' => $this->upsertExternalUser(
                    account: $accounts['d'],
                    name: 'E2E D Organization Owner',
                    email: 'e2e.d.organization_owner@example.test',
                    passwordHash: $passwordHash,
                    status: 'active',
                    isOwner: true
                ),
                'organization_admin' => $this->upsertExternalUser(
                    account: $accounts['d'],
                    name: 'E2E D Organization Admin',
                    email: 'e2e.d.organization_admin@example.test',
                    passwordHash: $passwordHash,
                    status: 'active'
                ),
                'staff' => $this->upsertExternalUser(
                    account: $accounts['d'],
                    name: 'E2E D Staff',
                    email: 'e2e.d.staff@example.test',
                    passwordHash: $passwordHash,
                    status: 'active'
                ),
            ],
        ];

        $this->assignTenantRole($externalUsers['a']['primary'], (string) $accounts['a']->id, 'individual_account_holder');
        $this->assignTenantRole($externalUsers['b']['primary'], (string) $accounts['b']->id, 'individual_account_holder');

        foreach (['c', 'd'] as $accountKey) {
            $this->assignTenantRole($externalUsers[$accountKey]['organization_owner'], (string) $accounts[$accountKey]->id, 'organization_owner');
            $this->assignTenantRole($externalUsers[$accountKey]['organization_admin'], (string) $accounts[$accountKey]->id, 'organization_admin');
            $this->assignTenantRole($externalUsers[$accountKey]['staff'], (string) $accounts[$accountKey]->id, 'staff');
        }

        $this->assignTenantRole($externalUsers['c']['suspended'], (string) $accounts['c']->id, 'staff');
        $this->assignTenantRole($externalUsers['c']['disabled'], (string) $accounts['c']->id, 'staff');

        $internalSuperAdmin = $this->upsertInternalUser(
            name: 'E2E Internal Super Admin',
            email: 'e2e.internal.super_admin@example.test',
            passwordHash: $passwordHash
        );

        $internalSupport = $this->upsertInternalUser(
            name: 'E2E Internal Support',
            email: 'e2e.internal.support@example.test',
            passwordHash: $passwordHash
        );

        $internalOpsReadonly = $this->upsertInternalUser(
            name: 'E2E Internal Ops Readonly',
            email: 'e2e.internal.ops_readonly@example.test',
            passwordHash: $passwordHash
        );

        $internalCarrierManager = $this->upsertInternalUser(
            name: 'E2E Internal Carrier Manager',
            email: 'e2e.internal.carrier_manager@example.test',
            passwordHash: $passwordHash
        );

        $superAdminRoleId = $this->resolveInternalRoleId('super_admin')
            ?? $this->ensureInternalRole(
                name: 'super_admin',
                displayName: 'SuperAdmin',
                description: 'Fallback super admin role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'admin.access',
                    'tenancy.context.select',
                    'users.read',
                    'users.manage',
                    'roles.read',
                    'roles.manage',
                    'roles.assign',
                    'shipments.read',
                    'shipments.manage',
                    'orders.read',
                    'orders.manage',
                    'wallet.read',
                    'wallet.manage',
                    'api_keys.read',
                    'api_keys.manage',
                    'webhooks.read',
                    'webhooks.manage',
                    'integrations.read',
                    'integrations.manage',
                    'tickets.read',
                    'tickets.manage',
                    'reports.read',
                    'reports.export',
                    'reports.manage',
                    'analytics.read',
                ]
            );

        $supportRoleId = $this->resolveInternalRoleId('support')
            ?? $this->ensureInternalRole(
                name: 'support',
                displayName: 'Support',
                description: 'Fallback support role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'tickets.read',
                    'tickets.manage',
                ]
            );

        $opsReadonlyRoleId = $this->resolveInternalRoleId('ops_readonly')
            ?? $this->ensureInternalRole(
                name: 'ops_readonly',
                displayName: 'OpsReadonly',
                description: 'Fallback internal read-only ops role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'analytics.read',
                    'reports.read',
                ]
            );

        $carrierManagerRoleId = $this->resolveInternalRoleId('carrier_manager')
            ?? $this->ensureInternalRole(
                name: 'carrier_manager',
                displayName: 'CarrierManager',
                description: 'Fallback carrier manager role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'notifications.channels.manage',
                ]
            );

        $this->assignInternalRole(
            userId: (string) $internalSuperAdmin->id,
            roleId: $superAdminRoleId,
            assignedBy: (string) $internalSuperAdmin->id
        );
        $this->assignInternalRole(
            userId: (string) $internalSupport->id,
            roleId: $supportRoleId,
            assignedBy: (string) $internalSuperAdmin->id
        );
        $this->assignInternalRole(
            userId: (string) $internalOpsReadonly->id,
            roleId: $opsReadonlyRoleId,
            assignedBy: (string) $internalSuperAdmin->id
        );
        $this->assignInternalRole(
            userId: (string) $internalCarrierManager->id,
            roleId: $carrierManagerRoleId,
            assignedBy: (string) $internalSuperAdmin->id
        );

        $this->seedDeterministicWallets($accounts);

        $this->command?->info('E2E user matrix seeded successfully.');
        $this->command?->line('Seeded accounts: A/B as single-user individual accounts, C/D as organization team accounts, plus internal users.');
        $this->command?->line('Login password for all seeded users: Password123!');
        $this->command?->line('Funded E2E billing wallets: account A (individual) and account C (organization) in USD for browser completion checks.');
    }

    private function cleanupLegacyMatrixUsers(): void
    {
        $emails = [
            'e2e.a.tenant_owner@example.test',
            'e2e.a.staff@example.test',
            'e2e.a.api_developer@example.test',
            'e2e.a.suspended@example.test',
            'e2e.a.disabled@example.test',
            'e2e.b.tenant_owner@example.test',
            'e2e.b.staff@example.test',
            'e2e.b.api_developer@example.test',
            'e2e.c.tenant_owner@example.test',
            'e2e.c.api_developer@example.test',
            'e2e.d.tenant_owner@example.test',
            'e2e.d.api_developer@example.test',
        ];

        $userIds = User::query()->withoutGlobalScopes()
            ->whereIn('email', $emails)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if ($userIds === []) {
            return;
        }

        foreach (['user_role', 'internal_user_role', 'personal_access_tokens'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            $column = match ($table) {
                'personal_access_tokens' => 'tokenable_id',
                default => 'user_id',
            };

            DB::table($table)->whereIn($column, $userIds)->delete();
        }

        User::query()->withoutGlobalScopes()->whereIn('id', $userIds)->delete();
    }

    private function cleanupLegacyInternalMatrixRoles(): void
    {
        if (
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_role_permission') ||
            !Schema::hasTable('internal_user_role')
        ) {
            return;
        }

        $legacyRoleIds = DB::table('internal_roles')
            ->whereIn('name', [
                'e2e_internal_support',
                'e2e_internal_ops_readonly',
            ])
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        if ($legacyRoleIds === []) {
            return;
        }

        DB::table('internal_user_role')->whereIn('internal_role_id', $legacyRoleIds)->delete();
        DB::table('internal_role_permission')->whereIn('internal_role_id', $legacyRoleIds)->delete();
        DB::table('internal_roles')->whereIn('id', $legacyRoleIds)->delete();
    }

    /**
     * @param array<string, Account> $accounts
     */
    private function seedDeterministicWallets(array $accounts): void
    {
        foreach (['a', 'c'] as $accountKey) {
            BillingWallet::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'account_id' => (string) $accounts[$accountKey]->id,
                    'currency' => 'USD',
                ],
                [
                    'organization_id' => null,
                    'available_balance' => 1000.00,
                    'reserved_balance' => 0.00,
                    'total_credited' => 1000.00,
                    'total_debited' => 0.00,
                    'status' => 'active',
                    'allow_negative' => false,
                ]
            );
        }
    }

    private function upsertAccount(string $slug, string $name, string $requestedType): Account
    {
        $attributes = Schema::hasColumn('accounts', 'slug')
            ? ['slug' => $slug]
            : ['name' => $name];

        $values = [];
        if (Schema::hasColumn('accounts', 'name')) {
            $values['name'] = $name;
        }
        if (Schema::hasColumn('accounts', 'slug')) {
            $values['slug'] = $slug;
        }
        if (Schema::hasColumn('accounts', 'type')) {
            $values['type'] = $this->resolveAccountType($requestedType);
        }
        if (Schema::hasColumn('accounts', 'status')) {
            $values['status'] = 'active';
        }

        return Account::query()->withoutGlobalScopes()->updateOrCreate($attributes, $values);
    }

    private function upsertExternalUser(
        Account $account,
        string $name,
        string $email,
        string $passwordHash,
        string $status = 'active',
        bool $isOwner = false
    ): User {
        $values = $this->baseUserPayload($name, $passwordHash);

        if (Schema::hasColumn('users', 'account_id')) {
            $values['account_id'] = $account->id;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $values['user_type'] = 'external';
        }
        if (Schema::hasColumn('users', 'status')) {
            $values['status'] = $status;
        }
        if (Schema::hasColumn('users', 'is_owner')) {
            $values['is_owner'] = $isOwner;
        }

        return User::query()->withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            $values
        );
    }

    private function upsertInternalUser(string $name, string $email, string $passwordHash): User
    {
        $values = $this->baseUserPayload($name, $passwordHash);

        if (Schema::hasColumn('users', 'account_id')) {
            $values['account_id'] = null;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $values['user_type'] = 'internal';
        }
        if (Schema::hasColumn('users', 'status')) {
            $values['status'] = 'active';
        }
        if (Schema::hasColumn('users', 'is_owner')) {
            $values['is_owner'] = false;
        }

        return User::query()->withoutGlobalScopes()->updateOrCreate(
            ['email' => $email],
            $values
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function baseUserPayload(string $name, string $passwordHash): array
    {
        $payload = [
            'name' => $name,
            'password' => $passwordHash,
        ];

        if (Schema::hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = now();
        }
        if (Schema::hasColumn('users', 'locale')) {
            $payload['locale'] = self::DEFAULT_LOCALE;
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $payload['timezone'] = self::DEFAULT_TIMEZONE;
        }

        return $payload;
    }

    private function assignTenantRole(User $user, string $accountId, string $roleName): void
    {
        if (!Schema::hasTable('user_role') || !Schema::hasTable('roles')) {
            throw new RuntimeException('Cannot assign tenant role: roles/user_role tables are missing.');
        }

        $roleId = DB::table('roles')
            ->where('account_id', $accountId)
            ->where('name', $roleName)
            ->value('id');

        if (!$roleId) {
            throw new RuntimeException(sprintf(
                'Required tenant role "%s" not found for account "%s".',
                $roleName,
                $accountId
            ));
        }

        $values = [];
        if (Schema::hasColumn('user_role', 'assigned_at')) {
            $values['assigned_at'] = now();
        }
        if (Schema::hasColumn('user_role', 'assigned_by')) {
            $values['assigned_by'] = null;
        }

        DB::table('user_role')->updateOrInsert(
            [
                'user_id' => (string) $user->id,
                'role_id' => (string) $roleId,
            ],
            $values
        );
    }

    private function resolveInternalRoleId(string $name): ?string
    {
        if (!Schema::hasTable('internal_roles')) {
            return null;
        }

        $id = DB::table('internal_roles')->where('name', $name)->value('id');
        return $id ? (string) $id : null;
    }

    /**
     * @param array<int, string> $permissionKeys
     */
    private function ensureInternalRole(
        string $name,
        string $displayName,
        string $description,
        array $permissionKeys
    ): string {
        if (
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_role_permission') ||
            !Schema::hasTable('permissions')
        ) {
            throw new RuntimeException('Cannot seed internal roles: internal RBAC tables are missing.');
        }

        $roleId = $this->resolveInternalRoleId($name) ?? (string) Str::uuid();
        $existing = $this->resolveInternalRoleId($name);

        if ($existing) {
            $updates = [];
            if (Schema::hasColumn('internal_roles', 'display_name')) {
                $updates['display_name'] = $displayName;
            }
            if (Schema::hasColumn('internal_roles', 'description')) {
                $updates['description'] = $description;
            }
            if (Schema::hasColumn('internal_roles', 'is_system')) {
                $updates['is_system'] = true;
            }
            if (Schema::hasColumn('internal_roles', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            if ($updates !== []) {
                DB::table('internal_roles')
                    ->where('id', $roleId)
                    ->update($updates);
            }
        } else {
            $payload = [
                'id' => $roleId,
                'name' => $name,
            ];
            if (Schema::hasColumn('internal_roles', 'display_name')) {
                $payload['display_name'] = $displayName;
            }
            if (Schema::hasColumn('internal_roles', 'description')) {
                $payload['description'] = $description;
            }
            if (Schema::hasColumn('internal_roles', 'is_system')) {
                $payload['is_system'] = true;
            }
            if (Schema::hasColumn('internal_roles', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('internal_roles', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('internal_roles')->insert($payload);
        }

        $permissions = DB::table('permissions')
            ->whereIn('key', $permissionKeys)
            ->pluck('id', 'key');

        $missingKeys = array_values(array_diff($permissionKeys, $permissions->keys()->all()));
        if ($missingKeys !== []) {
            throw new RuntimeException(sprintf(
                'Missing permissions for internal role "%s": %s',
                $name,
                implode(', ', $missingKeys)
            ));
        }

        DB::table('internal_role_permission')->where('internal_role_id', $roleId)->delete();
        foreach ($permissions as $permissionId) {
            $payload = [
                'internal_role_id' => $roleId,
                'permission_id' => (string) $permissionId,
            ];

            if (Schema::hasColumn('internal_role_permission', 'granted_at')) {
                $payload['granted_at'] = now();
            }

            DB::table('internal_role_permission')->insert($payload);
        }

        return $roleId;
    }

    private function assignInternalRole(string $userId, string $roleId, ?string $assignedBy = null): void
    {
        if (!Schema::hasTable('internal_user_role')) {
            throw new RuntimeException('Cannot assign internal role: internal_user_role table is missing.');
        }

        $values = [];
        if (Schema::hasColumn('internal_user_role', 'assigned_by')) {
            $values['assigned_by'] = $assignedBy;
        }
        if (Schema::hasColumn('internal_user_role', 'assigned_at')) {
            $values['assigned_at'] = now();
        }

        DB::table('internal_user_role')->updateOrInsert(
            [
                'user_id' => $userId,
                'internal_role_id' => $roleId,
            ],
            $values
        );
    }

    private function resolveAccountType(string $requestedType): string
    {
        if (!Schema::hasColumn('accounts', 'type')) {
            return $requestedType;
        }

        if (DB::getDriverName() !== 'mysql') {
            return $requestedType;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT COLUMN_TYPE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'accounts'
              AND COLUMN_NAME = 'type'
            LIMIT 1
            SQL
        );

        $columnType = (string) ($row->COLUMN_TYPE ?? '');
        preg_match_all("/'([^']+)'/", $columnType, $matches);
        $allowed = $matches[1] ?? [];

        if ($allowed === []) {
            return $requestedType;
        }

        if (in_array($requestedType, $allowed, true)) {
            return $requestedType;
        }

        if (in_array('organization', $allowed, true)) {
            return 'organization';
        }

        if (in_array('individual', $allowed, true)) {
            return 'individual';
        }

        return $requestedType;
    }
}
