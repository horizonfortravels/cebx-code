<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('permissions')) {
            $this->command?->warn('permissions table is missing. Skipping RBAC seeding.');
            return;
        }

        $permissionsByKey = $this->seedPermissionsCatalog();

        $this->seedInternalRoles($permissionsByKey);
        $this->seedTenantRoles($permissionsByKey);
        $this->normalizeLegacyRoleAliases();
        $this->assignCanonicalDemoUserRoles();

        $this->command?->info('RBAC seed complete.');
    }

    /**
     * @return array<string, string>
     */
    private function seedPermissionsCatalog(): array
    {
        $permissionsByKey = [];

        foreach ($this->permissionDefinitions() as $definition) {
            if (str_contains($definition['key'], ':')) {
                throw new \RuntimeException(
                    sprintf('Phase 2B2 requires dot-notation permission keys only. Invalid key: %s', $definition['key'])
                );
            }

            $attributes = ['key' => $definition['key']];
            $values = [
                'group' => $definition['group'],
                'display_name' => $definition['display_name'],
                'description' => $definition['description'],
            ];

            if (Schema::hasColumn('permissions', 'audience')) {
                $values['audience'] = $definition['audience'];
            }

            $permission = Permission::query()->updateOrCreate($attributes, $values);
            $permissionsByKey[$permission->key] = (string) $permission->id;
        }

        return $permissionsByKey;
    }

    /**
     * @param array<string, string> $permissionsByKey
     */
    private function seedInternalRoles(array $permissionsByKey): void
    {
        if (
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_role_permission')
        ) {
            return;
        }

        $allPermissionIds = array_values($permissionsByKey);

        $roleDefinitions = [
            [
                'name' => 'super_admin',
                'display_name' => 'SuperAdmin',
                'description' => 'Platform super admin with explicit RBAC grants only.',
                'is_system' => true,
                'permissions' => $allPermissionIds,
            ],
            [
                'name' => 'ops_readonly',
                'display_name' => 'OpsReadonly',
                'description' => 'Platform read-only operations role.',
                'is_system' => true,
                'permissions' => $this->permissionIds([
                    'shipments.read',
                    'shipments.documents.read',
                    'shipments.update',
                    'orders.read',
                    'users.read',
                    'roles.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'api_keys.read',
                    'feature_flags.read',
                    'pricing.read',
                    'rates.read',
                    'quotes.read',
                    'integrations.read',
                    'webhooks.read',
                    'tickets.read',
                    'kyc.read',
                    'kyc.documents',
                    'compliance.read',
                    'compliance.audit.read',
                    'dg.read',
                    'reports.read',
                    'analytics.read',
                    'intelligence.read',
                    'profitability.read',
                    'notifications.read',
                ], $permissionsByKey),
            ],
            [
                'name' => 'support',
                'display_name' => 'Support',
                'description' => 'Customer support role.',
                'is_system' => true,
                'permissions' => $this->permissionIds([
                    'accounts.read',
                    'accounts.support.manage',
                    'shipments.read',
                    'shipments.documents.read',
                    'orders.read',
                    'users.read',
                    'wallet.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'api_keys.read',
                    'feature_flags.read',
                    'integrations.read',
                    'webhooks.read',
                    'pricing.read',
                    'rates.read',
                    'quotes.read',
                    'tickets.read',
                    'tickets.manage',
                    'kyc.read',
                    'kyc.documents',
                    'compliance.read',
                    'dg.read',
                    'reports.read',
                    'reports.export',
                    'analytics.read',
                    'intelligence.read',
                    'notifications.read',
                    'notifications.manage',
                ], $permissionsByKey),
            ],
            [
                'name' => 'carrier_manager',
                'display_name' => 'CarrierManager',
                'description' => 'Carrier activation, platform API, and integration management role.',
                'is_system' => true,
                'permissions' => $this->permissionIds([
                    'shipments.documents.read',
                    'integrations.read',
                    'integrations.manage',
                    'api_keys.read',
                    'api_keys.manage',
                    'webhooks.read',
                    'webhooks.manage',
                    'pricing.read',
                    'pricing.manage',
                    'rates.read',
                    'rates.manage',
                    'quotes.read',
                    'quotes.manage',
                    'pricing_rules.manage',
                    'reports.read',
                    'reports.export',
                    'reports.manage',
                    'analytics.read',
                    'intelligence.read',
                    'intelligence.manage',
                    'profitability.read',
                    'profitability.manage',
                    'notifications.read',
                    'notifications.manage',
                    'notifications.templates.manage',
                    'notifications.channels.manage',
                    'notifications.schedules.manage',
                ], $permissionsByKey),
            ],
        ];

        foreach ($roleDefinitions as $roleDefinition) {
            $roleId = $this->upsertInternalRole($roleDefinition);
            $this->syncRolePermissionPivot(
                table: 'internal_role_permission',
                roleColumn: 'internal_role_id',
                permissionColumn: 'permission_id',
                roleId: $roleId,
                permissionIds: $roleDefinition['permissions']
            );
        }

        // Guarantee the minimum required permission assignment.
        if (isset($permissionsByKey['tenancy.context.select'])) {
            $superAdminId = DB::table('internal_roles')->where('name', 'super_admin')->value('id');
            if ($superAdminId) {
                DB::table('internal_role_permission')->updateOrInsert(
                    [
                        'internal_role_id' => $superAdminId,
                        'permission_id' => $permissionsByKey['tenancy.context.select'],
                    ],
                    [
                        'granted_at' => now(),
                    ]
                );
            }
        }
    }

    /**
     * @param array<string, string> $permissionsByKey
     */
    private function seedTenantRoles(array $permissionsByKey): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('role_permission') || !Schema::hasTable('accounts')) {
            return;
        }

        $hasRoleSlug = Schema::hasColumn('roles', 'slug');

        $accountsQuery = DB::table('accounts')->select('id');
        if (Schema::hasColumn('accounts', 'type')) {
            $accountsQuery->addSelect('type');
        }

        $accounts = $accountsQuery->get();

        if ($accounts->isEmpty()) {
            return;
        }

        foreach ($accounts as $account) {
            foreach ($this->tenantRoleDefinitions($permissionsByKey, (string) ($account->type ?? 'organization')) as $roleDefinition) {
                $roleId = $this->upsertTenantRole(
                    accountId: (string) $account->id,
                    roleDefinition: $roleDefinition,
                    hasRoleSlug: $hasRoleSlug,
                );

                $this->syncRolePermissionPivot(
                    table: 'role_permission',
                    roleColumn: 'role_id',
                    permissionColumn: 'permission_id',
                    roleId: $roleId,
                    permissionIds: $roleDefinition['permissions']
                );
            }
        }
    }

    /**
     * @param array{name: string, display_name: string, description: string, is_system: bool, template: string, permissions: array<int, string>} $roleDefinition
     */
    private function upsertTenantRole(string $accountId, array $roleDefinition, bool $hasRoleSlug): string
    {
        $query = DB::table('roles')
            ->where('account_id', $accountId)
            ->where('name', $roleDefinition['name']);

        $existingRoleId = $query->value('id');

        $values = array_filter([
            'slug' => $hasRoleSlug ? Str::slug($roleDefinition['name'], '_') : null,
            'display_name' => $roleDefinition['display_name'],
            'description' => $roleDefinition['description'],
            'is_system' => $roleDefinition['is_system'],
            'template' => $roleDefinition['template'],
            'updated_at' => now(),
        ], static fn ($value) => $value !== null);

        if ($existingRoleId) {
            DB::table('roles')
                ->where('id', (string) $existingRoleId)
                ->update($values);

            return (string) $existingRoleId;
        }

        $roleId = (string) Str::uuid();

        DB::table('roles')->insert(array_merge($values, [
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => $roleDefinition['name'],
            'created_at' => now(),
        ]));

        return $roleId;
    }

    /**
     * @param array<string, string> $permissionsByKey
     * @return array<int, array{name: string, display_name: string, description: string, is_system: bool, template: string, permissions: array<int, string>}>
     */
    private function tenantRoleDefinitions(array $permissionsByKey, string $accountType): array
    {
        $definitions = [
            [
                'name' => 'organization_owner',
                'display_name' => 'OrganizationOwner',
                'description' => 'Default organization owner role.',
                'is_system' => true,
                'template' => 'organization_owner',
                'permissions' => $this->permissionIds(array_merge([
                    'shipments.read', 'shipments.create', 'shipments.update_draft', 'shipments.update', 'shipments.manage',
                    'orders.read', 'orders.manage',
                    'wallet.read', 'wallet.manage',
                    'pricing.read', 'pricing.manage',
                    'rates.read', 'rates.manage',
                    'quotes.read', 'quotes.manage',
                    'pricing_rules.manage',
                    'users.read', 'users.manage', 'users.invite',
                    'roles.read', 'roles.manage', 'roles.assign',
                    'tickets.read', 'tickets.manage',
                    'integrations.read', 'integrations.manage',
                    'api_keys.read', 'api_keys.manage',
                    'webhooks.read', 'webhooks.manage',
                    'kyc.read', 'kyc.manage',
                    'kyc.documents', 'kyc.documents.read', 'kyc.documents.manage',
                    'compliance.read', 'compliance.manage',
                    'compliance.audit.read', 'compliance.audit.export',
                    'dg.read', 'dg.manage',
                    'reports.read', 'reports.export', 'reports.manage',
                    'analytics.read',
                    'intelligence.read', 'intelligence.manage',
                    'profitability.read', 'profitability.manage',
                    'notifications.read', 'notifications.manage',
                    'notifications.templates.manage',
                    'notifications.channels.manage',
                    'notifications.schedules.manage',
                ], $this->organizationOwnerCoveragePermissionKeys()), $permissionsByKey),
            ],
            [
                'name' => 'organization_admin',
                'display_name' => 'OrganizationAdmin',
                'description' => 'Default organization admin role.',
                'is_system' => true,
                'template' => 'organization_admin',
                'permissions' => $this->permissionIds(array_merge([
                    'shipments.read', 'shipments.create', 'shipments.update_draft', 'shipments.update', 'shipments.manage',
                    'orders.read', 'orders.manage',
                    'wallet.read',
                    'pricing.read', 'pricing.manage',
                    'rates.read', 'rates.manage',
                    'quotes.read', 'quotes.manage',
                    'pricing_rules.manage',
                    'users.read', 'users.manage', 'users.invite',
                    'roles.read',
                    'tickets.read', 'tickets.manage',
                    'integrations.read', 'integrations.manage',
                    'api_keys.read', 'api_keys.manage',
                    'webhooks.read', 'webhooks.manage',
                    'kyc.read', 'kyc.manage',
                    'kyc.documents', 'kyc.documents.read', 'kyc.documents.manage',
                    'compliance.read', 'compliance.manage',
                    'compliance.audit.read',
                    'dg.read', 'dg.manage',
                    'reports.read', 'reports.export', 'reports.manage',
                    'analytics.read',
                    'intelligence.read', 'intelligence.manage',
                    'profitability.read', 'profitability.manage',
                    'notifications.read', 'notifications.manage',
                    'notifications.templates.manage',
                    'notifications.channels.manage',
                    'notifications.schedules.manage',
                ], $this->organizationAdminCoveragePermissionKeys()), $permissionsByKey),
            ],
            [
                'name' => 'staff',
                'display_name' => 'Staff',
                'description' => 'Default organization staff role.',
                'is_system' => true,
                'template' => 'staff',
                'permissions' => $this->permissionIds(array_merge([
                    'shipments.read', 'shipments.create', 'shipments.update_draft',
                    'orders.read',
                    'wallet.read',
                    'pricing.read',
                    'rates.read',
                    'quotes.read', 'quotes.manage',
                    'users.read',
                    'roles.read',
                    'tickets.read',
                    'tickets.manage',
                    'kyc.read',
                    'kyc.documents', 'kyc.documents.read',
                    'compliance.read',
                    'dg.read', 'dg.manage',
                    'reports.read',
                    'analytics.read',
                    'intelligence.read',
                    'profitability.read',
                    'notifications.read',
                    'notifications.manage',
                ], $this->staffCoveragePermissionKeys()), $permissionsByKey),
            ],
        ];

        if ($accountType === 'individual') {
            $definitions[] = [
                'name' => 'individual_account_holder',
                'display_name' => 'IndividualAccountHolder',
                'description' => 'System role for the sole external user on an individual account.',
                'is_system' => true,
                'template' => 'individual_account_holder',
                'permissions' => $this->permissionIds([
                    'account.read',
                    'account.manage',
                    'addresses.read',
                    'addresses.manage',
                    'stores.read',
                    'tickets.read',
                    'tickets.manage',
                    'shipments.read',
                    'shipments.create',
                    'shipments.update_draft',
                    'rates.read',
                    'quotes.read',
                    'quotes.manage',
                    'dg.read',
                    'dg.manage',
                    'tracking.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'wallet.topup',
                    'wallet.configure',
                    'billing.view',
                    'billing.manage',
                    'notifications.read',
                    'notifications.manage',
                ], $permissionsByKey),
            ];
        }

        return $definitions;
    }

    /**
     * @param array<string, mixed> $roleDefinition
     */
    private function upsertInternalRole(array $roleDefinition): string
    {
        $existingRoleId = DB::table('internal_roles')
            ->where('name', $roleDefinition['name'])
            ->value('id');

        if ($existingRoleId) {
            DB::table('internal_roles')
                ->where('id', $existingRoleId)
                ->update([
                    'display_name' => $roleDefinition['display_name'],
                    'description' => $roleDefinition['description'],
                    'is_system' => (bool) $roleDefinition['is_system'],
                    'updated_at' => now(),
                ]);

            return (string) $existingRoleId;
        }

        $id = (string) Str::uuid();

        DB::table('internal_roles')->insert([
            'id' => $id,
            'name' => $roleDefinition['name'],
            'display_name' => $roleDefinition['display_name'],
            'description' => $roleDefinition['description'],
            'is_system' => (bool) $roleDefinition['is_system'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @return array<int, string>
     */
    private function organizationOwnerCoveragePermissionKeys(): array
    {
        return array_merge(
            $this->commonExternalSelfServicePermissionKeys(),
            $this->broadExternalReadPermissionKeys(),
            $this->broadExternalManagePermissionKeys()
        );
    }

    /**
     * @return array<int, string>
     */
    private function organizationAdminCoveragePermissionKeys(): array
    {
        return array_merge(
            $this->commonExternalSelfServicePermissionKeys(),
            $this->broadExternalReadPermissionKeys(),
            array_diff(
                $this->broadExternalManagePermissionKeys(),
                ['organizations.manage']
            )
        );
    }

    /**
     * @return array<int, string>
     */
    private function staffCoveragePermissionKeys(): array
    {
        return array_merge(
            $this->commonExternalSelfServicePermissionKeys(),
            [
                'wallet.balance',
                'wallet.ledger',
                'billing.view',
            ],
            $this->broadExternalReadPermissionKeys()
        );
    }

    /**
     * @return array<int, string>
     */
    private function commonExternalSelfServicePermissionKeys(): array
    {
        return [
            'account.read',
            'account.manage',
            'stores.read',
            'stores.manage',
            'addresses.read',
            'addresses.manage',
            'wallet.balance',
            'wallet.ledger',
            'wallet.topup',
            'wallet.configure',
            'billing.view',
            'billing.manage',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function broadExternalReadPermissionKeys(): array
    {
        return [
            'organizations.read',
            'financial.read',
            'tracking.read',
            'payments.read',
            'subscriptions.read',
            'companies.read',
            'branches.read',
            'customs.read',
            'containers.read',
            'vessels.read',
            'vessel_schedules.read',
            'claims.read',
            'risk.read',
            'drivers.read',
            'delivery.read',
            'proof_of_deliveries.read',
            'incoterms.read',
            'hs_codes.read',
            'tariffs.read',
            'tax_rules.read',
            'shipment_workflow.read',
            'sla.read',
            'content_declarations.read',
            'route_optimization.read',
            'capacity.read',
            'currency.read',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function broadExternalManagePermissionKeys(): array
    {
        return [
            'organizations.manage',
            'financial.manage',
            'tracking.manage',
            'payments.manage',
            'subscriptions.manage',
            'companies.manage',
            'branches.manage',
            'customs.manage',
            'containers.manage',
            'vessels.manage',
            'vessel_schedules.manage',
            'claims.manage',
            'risk.manage',
            'drivers.manage',
            'delivery.manage',
            'incoterms.manage',
            'hs_codes.manage',
            'tariffs.manage',
            'tax_rules.manage',
            'booking.manage',
            'shipment_workflow.manage',
            'insurance.manage',
            'content_declarations.manage',
            'route_optimization.manage',
            'capacity.manage',
            'currency.manage',
        ];
    }

    /**
     * @param array<int, string> $keys
     * @param array<string, string> $permissionsByKey
     * @return array<int, string>
     */
    private function permissionIds(array $keys, array $permissionsByKey): array
    {
        $ids = [];

        foreach ($keys as $key) {
            if (!isset($permissionsByKey[$key])) {
                continue;
            }

            $ids[] = $permissionsByKey[$key];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, string> $permissionIds
     */
    private function syncRolePermissionPivot(
        string $table,
        string $roleColumn,
        string $permissionColumn,
        string $roleId,
        array $permissionIds
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where($roleColumn, $roleId)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table($table)->insert([
                $roleColumn => $roleId,
                $permissionColumn => $permissionId,
                'granted_at' => now(),
            ]);
        }
    }

    private function normalizeLegacyRoleAliases(): void
    {
        $this->mergeInternalRoleAlias('integration_admin', 'carrier_manager');
        $this->mergeInternalRoleAlias('ops', 'ops_readonly');

        $this->mergeTenantRoleAlias('tenant_owner', 'organization_owner');
        $this->mergeTenantRoleAlias('tenant_admin', 'organization_admin');
        $this->mergeTenantRoleAlias('api_developer', 'organization_admin');
    }

    private function assignCanonicalDemoUserRoles(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles') || !Schema::hasTable('user_role')) {
            return;
        }

        $assignments = [
            'sultan@techco.sa' => 'organization_owner',
            'hind@techco.sa' => 'organization_admin',
            'majed@techco.sa' => 'staff',
            'lama@techco.sa' => 'staff',
            'mohammed@example.sa' => 'individual_account_holder',
        ];

        foreach ($assignments as $email => $roleName) {
            $user = DB::table('users')
                ->select(['id', 'account_id'])
                ->where('email', $email)
                ->first();

            if (!$user || empty($user->account_id)) {
                continue;
            }

            $role = DB::table('roles')
                ->select('id')
                ->where('account_id', (string) $user->account_id)
                ->where('name', $roleName)
                ->first();

            if (!$role) {
                continue;
            }

            DB::table('user_role')->where('user_id', (string) $user->id)->delete();

            $values = [];
            if (Schema::hasColumn('user_role', 'assigned_by')) {
                $values['assigned_by'] = null;
            }
            if (Schema::hasColumn('user_role', 'assigned_at')) {
                $values['assigned_at'] = now();
            }

            DB::table('user_role')->updateOrInsert(
                [
                    'user_id' => (string) $user->id,
                    'role_id' => (string) $role->id,
                ],
                $values
            );
        }
    }

    private function mergeInternalRoleAlias(string $legacyName, string $canonicalName): void
    {
        if (
            !Schema::hasTable('internal_roles') ||
            !Schema::hasTable('internal_user_role') ||
            !Schema::hasTable('internal_role_permission')
        ) {
            return;
        }

        $legacyRoleId = DB::table('internal_roles')->where('name', $legacyName)->value('id');
        $canonicalRoleId = DB::table('internal_roles')->where('name', $canonicalName)->value('id');

        if (!$legacyRoleId) {
            return;
        }

        if (!$canonicalRoleId) {
            DB::table('internal_roles')
                ->where('id', $legacyRoleId)
                ->update([
                    'name' => $canonicalName,
                    'display_name' => Str::headline(str_replace('_', ' ', $canonicalName)),
                    'updated_at' => now(),
                ]);

            return;
        }

        foreach (DB::table('internal_user_role')->where('internal_role_id', $legacyRoleId)->get() as $assignment) {
            DB::table('internal_user_role')->updateOrInsert(
                [
                    'user_id' => $assignment->user_id,
                    'internal_role_id' => $canonicalRoleId,
                ],
                [
                    'assigned_by' => $assignment->assigned_by ?? null,
                    'assigned_at' => $assignment->assigned_at ?? now(),
                ]
            );
        }

        foreach (DB::table('internal_role_permission')->where('internal_role_id', $legacyRoleId)->get() as $grant) {
            DB::table('internal_role_permission')->updateOrInsert(
                [
                    'internal_role_id' => $canonicalRoleId,
                    'permission_id' => $grant->permission_id,
                ],
                [
                    'granted_at' => $grant->granted_at ?? now(),
                ]
            );
        }

        DB::table('internal_user_role')->where('internal_role_id', $legacyRoleId)->delete();
        DB::table('internal_role_permission')->where('internal_role_id', $legacyRoleId)->delete();
        DB::table('internal_roles')->where('id', $legacyRoleId)->delete();
    }

    private function mergeTenantRoleAlias(string $legacyName, string $canonicalName): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('user_role') || !Schema::hasTable('role_permission')) {
            return;
        }

        $legacyRoles = DB::table('roles')->where('name', $legacyName)->get();

        foreach ($legacyRoles as $legacyRole) {
            $canonicalRole = DB::table('roles')
                ->where('account_id', $legacyRole->account_id)
                ->where('name', $canonicalName)
                ->first();

            if (!$canonicalRole) {
                DB::table('roles')
                    ->where('id', $legacyRole->id)
                    ->update(array_filter([
                        'name' => $canonicalName,
                        'slug' => Schema::hasColumn('roles', 'slug') ? Str::slug($canonicalName, '_') : null,
                        'display_name' => Str::headline(str_replace('_', ' ', $canonicalName)),
                        'template' => Schema::hasColumn('roles', 'template') ? $canonicalName : null,
                        'updated_at' => Schema::hasColumn('roles', 'updated_at') ? now() : null,
                    ], static fn ($value) => $value !== null));

                continue;
            }

            foreach (DB::table('user_role')->where('role_id', $legacyRole->id)->get() as $assignment) {
                DB::table('user_role')->updateOrInsert(
                    [
                        'user_id' => $assignment->user_id,
                        'role_id' => $canonicalRole->id,
                    ],
                    [
                        'assigned_by' => $assignment->assigned_by ?? null,
                        'assigned_at' => $assignment->assigned_at ?? now(),
                    ]
                );
            }

            foreach (DB::table('role_permission')->where('role_id', $legacyRole->id)->get() as $grant) {
                DB::table('role_permission')->updateOrInsert(
                    [
                        'role_id' => $canonicalRole->id,
                        'permission_id' => $grant->permission_id,
                    ],
                    [
                        'granted_at' => $grant->granted_at ?? now(),
                    ]
                );
            }

            DB::table('user_role')->where('role_id', $legacyRole->id)->delete();
            DB::table('role_permission')->where('role_id', $legacyRole->id)->delete();
            DB::table('roles')->where('id', $legacyRole->id)->delete();
        }
    }

    /**
     * @return array<int, array{key: string, group: string, display_name: string, description: string, audience: string}>
     */
    private function permissionDefinitions(): array
    {
        return [
            [
                'key' => 'tenancy.context.select',
                'group' => 'tenancy',
                'display_name' => 'Select tenant context',
                'description' => 'Allow internal actor to select tenant context explicitly.',
                'audience' => 'internal',
            ],
            [
                'key' => 'shipments.read',
                'group' => 'shipments',
                'display_name' => 'View shipments',
                'description' => 'Read shipment resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'shipments.documents.read',
                'group' => 'shipments',
                'display_name' => 'View shipment documents',
                'description' => 'Read internal shipment document and label artifacts.',
                'audience' => 'internal',
            ],
            [
                'key' => 'shipments.create',
                'group' => 'shipments',
                'display_name' => 'Create shipments',
                'description' => 'Create shipment resources.',
                'audience' => 'external',
            ],
            [
                'key' => 'shipments.update_draft',
                'group' => 'shipments',
                'display_name' => 'Update shipment drafts',
                'description' => 'Update draft shipments before pricing and purchase.',
                'audience' => 'external',
            ],
            [
                'key' => 'shipments.update',
                'group' => 'shipments',
                'display_name' => 'Update shipments',
                'description' => 'Update shipment resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'orders.read',
                'group' => 'orders',
                'display_name' => 'View orders',
                'description' => 'Read order resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'orders.manage',
                'group' => 'orders',
                'display_name' => 'Manage orders',
                'description' => 'Manage order resources.',
                'audience' => 'external',
            ],
            [
                'key' => 'wallet.read',
                'group' => 'wallet',
                'display_name' => 'View wallet',
                'description' => 'Read wallet resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'wallet.manage',
                'group' => 'wallet',
                'display_name' => 'Manage wallet',
                'description' => 'Manage wallet operations.',
                'audience' => 'external',
            ],
            [
                'key' => 'users.read',
                'group' => 'users',
                'display_name' => 'View users',
                'description' => 'Read user resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'users.manage',
                'group' => 'users',
                'display_name' => 'Manage users',
                'description' => 'Manage user resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'roles.read',
                'group' => 'roles',
                'display_name' => 'View roles',
                'description' => 'Read role resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'roles.manage',
                'group' => 'roles',
                'display_name' => 'Manage roles',
                'description' => 'Manage role resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'roles.assign',
                'group' => 'roles',
                'display_name' => 'Assign roles',
                'description' => 'Assign and revoke roles.',
                'audience' => 'both',
            ],
            [
                'key' => 'api_keys.read',
                'group' => 'api_keys',
                'display_name' => 'View API keys',
                'description' => 'Read API key resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'api_keys.manage',
                'group' => 'api_keys',
                'display_name' => 'Manage API keys',
                'description' => 'Manage API key resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'feature_flags.read',
                'group' => 'feature_flags',
                'display_name' => 'View feature flags',
                'description' => 'Read the internal feature-flags operations center.',
                'audience' => 'internal',
            ],
            [
                'key' => 'feature_flags.manage',
                'group' => 'feature_flags',
                'display_name' => 'Manage feature flags',
                'description' => 'Toggle feature-flag state from the internal operations center.',
                'audience' => 'internal',
            ],
            [
                'key' => 'webhooks.read',
                'group' => 'webhooks',
                'display_name' => 'View webhooks',
                'description' => 'Read webhook resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'webhooks.manage',
                'group' => 'webhooks',
                'display_name' => 'Manage webhooks',
                'description' => 'Manage webhook resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'integrations.read',
                'group' => 'integrations',
                'display_name' => 'View integrations',
                'description' => 'Read integrations and integration health resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'integrations.manage',
                'group' => 'integrations',
                'display_name' => 'Manage integrations',
                'description' => 'Run/manage integration connectivity operations.',
                'audience' => 'both',
            ],
            [
                'key' => 'users.invite',
                'group' => 'users',
                'display_name' => 'Invite users',
                'description' => 'Invite tenant users.',
                'audience' => 'external',
            ],
            [
                'key' => 'tickets.read',
                'group' => 'tickets',
                'display_name' => 'View tickets',
                'description' => 'Read support tickets.',
                'audience' => 'both',
            ],
            [
                'key' => 'tickets.manage',
                'group' => 'tickets',
                'display_name' => 'Manage tickets',
                'description' => 'Create and manage support tickets.',
                'audience' => 'both',
            ],
            [
                'key' => 'account.manage',
                'group' => 'account',
                'display_name' => 'Manage account settings',
                'description' => 'Manage tenant account settings.',
                'audience' => 'external',
            ],
            [
                'key' => 'stores.manage',
                'group' => 'stores',
                'display_name' => 'Manage stores',
                'description' => 'Manage tenant stores and channels.',
                'audience' => 'external',
            ],
            [
                'key' => 'shipments.manage',
                'group' => 'shipments',
                'display_name' => 'Manage shipments',
                'description' => 'Manage shipment lifecycle operations.',
                'audience' => 'external',
            ],
            [
                'key' => 'shipments.print_label',
                'group' => 'shipments',
                'display_name' => 'Print shipment labels',
                'description' => 'Print and reprint shipment labels.',
                'audience' => 'external',
            ],
            [
                'key' => 'shipments.view_financial',
                'group' => 'shipments',
                'display_name' => 'View shipment financial fields',
                'description' => 'View shipment cost and margin fields.',
                'audience' => 'both',
            ],
            [
                'key' => 'audit.view',
                'group' => 'audit',
                'display_name' => 'View audit logs',
                'description' => 'View audit log entries.',
                'audience' => 'both',
            ],
            [
                'key' => 'audit.export',
                'group' => 'audit',
                'display_name' => 'Export audit logs',
                'description' => 'Export audit log entries.',
                'audience' => 'both',
            ],
            [
                'key' => 'compliance.audit.read',
                'group' => 'compliance',
                'display_name' => 'View compliance audit logs',
                'description' => 'Read compliance audit log entries.',
                'audience' => 'both',
            ],
            [
                'key' => 'compliance.audit.export',
                'group' => 'compliance',
                'display_name' => 'Export compliance audit logs',
                'description' => 'Export compliance audit log entries.',
                'audience' => 'both',
            ],
            [
                'key' => 'kyc.read',
                'group' => 'kyc',
                'display_name' => 'View KYC',
                'description' => 'Read KYC status and verification resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'kyc.manage',
                'group' => 'kyc',
                'display_name' => 'Manage KYC',
                'description' => 'Manage KYC reviews and actions.',
                'audience' => 'both',
            ],
            [
                'key' => 'kyc.documents.read',
                'group' => 'kyc',
                'display_name' => 'View KYC documents',
                'description' => 'Read KYC document resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'kyc.documents.manage',
                'group' => 'kyc',
                'display_name' => 'Manage KYC documents',
                'description' => 'Upload, delete, and manage KYC document resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'kyc.documents',
                'group' => 'kyc',
                'display_name' => 'View KYC documents',
                'description' => 'Access sensitive KYC documents.',
                'audience' => 'both',
            ],
            [
                'key' => 'compliance.read',
                'group' => 'compliance',
                'display_name' => 'View compliance resources',
                'description' => 'Read compliance resources and status.',
                'audience' => 'both',
            ],
            [
                'key' => 'compliance.manage',
                'group' => 'compliance',
                'display_name' => 'Manage compliance resources',
                'description' => 'Create and update compliance resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'dg.read',
                'group' => 'dg',
                'display_name' => 'View dangerous goods declarations',
                'description' => 'Read dangerous goods declarations and audit resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'dg.manage',
                'group' => 'dg',
                'display_name' => 'Manage dangerous goods declarations',
                'description' => 'Create and manage dangerous goods declarations.',
                'audience' => 'both',
            ],
            [
                'key' => 'notifications.read',
                'group' => 'notifications',
                'display_name' => 'View notifications',
                'description' => 'Read notification logs, in-app notifications, unread counters, and preferences.',
                'audience' => 'both',
            ],
            [
                'key' => 'notifications.manage',
                'group' => 'notifications',
                'display_name' => 'Manage notifications',
                'description' => 'Mark notifications as read and update notification preferences.',
                'audience' => 'both',
            ],
            [
                'key' => 'notifications.templates.manage',
                'group' => 'notifications',
                'display_name' => 'Manage notification templates',
                'description' => 'Create, update, and preview notification templates.',
                'audience' => 'both',
            ],
            [
                'key' => 'notifications.channels.manage',
                'group' => 'notifications',
                'display_name' => 'Manage notification channels',
                'description' => 'List and configure notification channels.',
                'audience' => 'both',
            ],
            [
                'key' => 'notifications.schedules.manage',
                'group' => 'notifications',
                'display_name' => 'Manage notification schedules',
                'description' => 'Create and list scheduled notification settings.',
                'audience' => 'both',
            ],
            [
                'key' => 'reports.read',
                'group' => 'reports',
                'display_name' => 'View reports',
                'description' => 'Read reports, dashboards, exports list, and schedules list.',
                'audience' => 'both',
            ],
            [
                'key' => 'reports.export',
                'group' => 'reports',
                'display_name' => 'Export reports',
                'description' => 'Create report exports.',
                'audience' => 'both',
            ],
            [
                'key' => 'reports.manage',
                'group' => 'reports',
                'display_name' => 'Manage reports',
                'description' => 'Create and manage saved reports and report schedules.',
                'audience' => 'both',
            ],
            [
                'key' => 'analytics.read',
                'group' => 'analytics',
                'display_name' => 'View analytics',
                'description' => 'Read analytics dashboards and performance insights.',
                'audience' => 'both',
            ],
            [
                'key' => 'intelligence.read',
                'group' => 'intelligence',
                'display_name' => 'View intelligence',
                'description' => 'Read intelligence dashboards, predictions, and fraud signals.',
                'audience' => 'both',
            ],
            [
                'key' => 'intelligence.manage',
                'group' => 'intelligence',
                'display_name' => 'Manage intelligence',
                'description' => 'Trigger intelligence predictions and review fraud signals.',
                'audience' => 'both',
            ],
            [
                'key' => 'profitability.read',
                'group' => 'profitability',
                'display_name' => 'View profitability',
                'description' => 'Read profitability dashboards and shipment cost reports.',
                'audience' => 'both',
            ],
            [
                'key' => 'profitability.manage',
                'group' => 'profitability',
                'display_name' => 'Manage profitability',
                'description' => 'Create and update shipment profitability cost records.',
                'audience' => 'both',
            ],
            [
                'key' => 'financial.view',
                'group' => 'financial',
                'display_name' => 'View financial data',
                'description' => 'View general financial data.',
                'audience' => 'both',
            ],
            [
                'key' => 'financial.profit.view',
                'group' => 'financial',
                'display_name' => 'View profit breakdown',
                'description' => 'View profit and margin fields.',
                'audience' => 'both',
            ],
            [
                'key' => 'financial.cards.view',
                'group' => 'financial',
                'display_name' => 'View card details',
                'description' => 'View unmasked card details.',
                'audience' => 'both',
            ],
            [
                'key' => 'pricing.read',
                'group' => 'pricing',
                'display_name' => 'View pricing',
                'description' => 'Read pricing breakdowns and rule sets.',
                'audience' => 'both',
            ],
            [
                'key' => 'pricing.manage',
                'group' => 'pricing',
                'display_name' => 'Manage pricing',
                'description' => 'Create and manage pricing rule sets and policies.',
                'audience' => 'both',
            ],
            [
                'key' => 'rates.read',
                'group' => 'rates',
                'display_name' => 'View rates',
                'description' => 'Read rate rules and rate resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'rates.manage',
                'group' => 'rates',
                'display_name' => 'Manage rates',
                'description' => 'Fetch and reprice shipment rates.',
                'audience' => 'both',
            ],
            [
                'key' => 'quotes.read',
                'group' => 'quotes',
                'display_name' => 'View quotes',
                'description' => 'Read rate quote resources.',
                'audience' => 'both',
            ],
            [
                'key' => 'quotes.manage',
                'group' => 'quotes',
                'display_name' => 'Manage quotes',
                'description' => 'Select and manage quote options.',
                'audience' => 'both',
            ],
            [
                'key' => 'pricing_rules.manage',
                'group' => 'pricing_rules',
                'display_name' => 'Manage pricing rules',
                'description' => 'Create, edit, and delete pricing rules.',
                'audience' => 'both',
            ],
            [
                'key' => 'rates.view_breakdown',
                'group' => 'rates',
                'display_name' => 'View rate breakdown',
                'description' => 'View net and markup pricing breakdown.',
                'audience' => 'both',
            ],
            [
                'key' => 'rates.manage_rules',
                'group' => 'rates',
                'display_name' => 'Manage pricing rules',
                'description' => 'Create, edit, and delete pricing rules.',
                'audience' => 'external',
            ],
            [
                'key' => 'wallet.balance',
                'group' => 'wallet',
                'display_name' => 'View wallet balance',
                'description' => 'View wallet balance summary.',
                'audience' => 'both',
            ],
            [
                'key' => 'wallet.ledger',
                'group' => 'wallet',
                'display_name' => 'View wallet ledger',
                'description' => 'View wallet ledger entries.',
                'audience' => 'both',
            ],
            [
                'key' => 'wallet.topup',
                'group' => 'wallet',
                'display_name' => 'Top up wallet',
                'description' => 'Top up wallet balance.',
                'audience' => 'both',
            ],
            [
                'key' => 'wallet.configure',
                'group' => 'wallet',
                'display_name' => 'Configure wallet',
                'description' => 'Configure wallet thresholds/settings.',
                'audience' => 'both',
            ],
            [
                'key' => 'billing.view',
                'group' => 'billing',
                'display_name' => 'View billing methods',
                'description' => 'View billing/payment methods.',
                'audience' => 'both',
            ],
            [
                'key' => 'billing.manage',
                'group' => 'billing',
                'display_name' => 'Manage billing methods',
                'description' => 'Add/remove billing/payment methods.',
                'audience' => 'both',
            ],
            ...$this->finalExternalCoveragePermissionDefinitions(),
            [
                'key' => 'accounts.read',
                'group' => 'accounts',
                'display_name' => 'View internal customer accounts center',
                'description' => 'Read the internal external-accounts index and detail center.',
                'audience' => 'internal',
            ],
            [
                'key' => 'accounts.create',
                'group' => 'accounts',
                'display_name' => 'Create external customer accounts',
                'description' => 'Create external individual and organization accounts from the internal portal.',
                'audience' => 'internal',
            ],
            [
                'key' => 'accounts.update',
                'group' => 'accounts',
                'display_name' => 'Update external customer accounts',
                'description' => 'Edit core external account profile fields from the internal portal.',
                'audience' => 'internal',
            ],
            [
                'key' => 'accounts.lifecycle.manage',
                'group' => 'accounts',
                'display_name' => 'Manage external account lifecycle',
                'description' => 'Activate, deactivate, suspend, and unsuspend external accounts from the internal portal.',
                'audience' => 'internal',
            ],
            [
                'key' => 'accounts.support.manage',
                'group' => 'accounts',
                'display_name' => 'Run internal external-account support actions',
                'description' => 'Trigger password reset and safe invitation resend actions from the internal portal.',
                'audience' => 'internal',
            ],
            [
                'key' => 'accounts.members.manage',
                'group' => 'accounts',
                'display_name' => 'Manage organization members from the internal accounts center',
                'description' => 'Invite members and change member status for organization accounts from the internal portal.',
                'audience' => 'internal',
            ],
            [
                'key' => 'admin.access',
                'group' => 'admin',
                'display_name' => 'Access admin APIs',
                'description' => 'Access internal/admin APIs.',
                'audience' => 'internal',
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, group: string, display_name: string, description: string, audience: string}>
     */
    private function finalExternalCoveragePermissionDefinitions(): array
    {
        return [
            ['key' => 'account.read', 'group' => 'account', 'display_name' => 'View account', 'description' => 'Read tenant account profile and session context endpoints.', 'audience' => 'external'],
            ['key' => 'stores.read', 'group' => 'stores', 'display_name' => 'View stores', 'description' => 'Read store resources and store stats.', 'audience' => 'external'],
            ['key' => 'addresses.read', 'group' => 'addresses', 'display_name' => 'View addresses', 'description' => 'Read tenant address book resources.', 'audience' => 'external'],
            ['key' => 'addresses.manage', 'group' => 'addresses', 'display_name' => 'Manage addresses', 'description' => 'Create and delete tenant address book resources.', 'audience' => 'external'],
            ['key' => 'organizations.read', 'group' => 'organizations', 'display_name' => 'View organizations', 'description' => 'Read organization, member, and invite resources.', 'audience' => 'external'],
            ['key' => 'organizations.manage', 'group' => 'organizations', 'display_name' => 'Manage organizations', 'description' => 'Create and manage organization, member, and invite resources.', 'audience' => 'external'],
            ['key' => 'financial.read', 'group' => 'financial', 'display_name' => 'View financial masking tools', 'description' => 'Read financial visibility and masking configuration endpoints.', 'audience' => 'both'],
            ['key' => 'financial.manage', 'group' => 'financial', 'display_name' => 'Manage financial masking tools', 'description' => 'Run financial filtering and masking operations.', 'audience' => 'both'],
            ['key' => 'tracking.read', 'group' => 'tracking', 'display_name' => 'View tracking', 'description' => 'Read tracking dashboards, timelines, and exceptions.', 'audience' => 'both'],
            ['key' => 'tracking.manage', 'group' => 'tracking', 'display_name' => 'Manage tracking', 'description' => 'Manage tracking subscriptions, polls, and exception actions.', 'audience' => 'both'],
            ['key' => 'payments.read', 'group' => 'payments', 'display_name' => 'View payments', 'description' => 'Read payment, invoice, and gateway resources.', 'audience' => 'both'],
            ['key' => 'payments.manage', 'group' => 'payments', 'display_name' => 'Manage payments', 'description' => 'Create charges, refunds, promos, and payment alerts.', 'audience' => 'both'],
            ['key' => 'subscriptions.read', 'group' => 'subscriptions', 'display_name' => 'View subscriptions', 'description' => 'Read subscription status and plans.', 'audience' => 'both'],
            ['key' => 'subscriptions.manage', 'group' => 'subscriptions', 'display_name' => 'Manage subscriptions', 'description' => 'Manage subscription lifecycle operations.', 'audience' => 'both'],
            ['key' => 'companies.read', 'group' => 'companies', 'display_name' => 'View companies', 'description' => 'Read company resources and stats.', 'audience' => 'external'],
            ['key' => 'companies.manage', 'group' => 'companies', 'display_name' => 'Manage companies', 'description' => 'Create and manage company resources.', 'audience' => 'external'],
            ['key' => 'branches.read', 'group' => 'branches', 'display_name' => 'View branches', 'description' => 'Read branch resources and branch staff listings.', 'audience' => 'external'],
            ['key' => 'branches.manage', 'group' => 'branches', 'display_name' => 'Manage branches', 'description' => 'Create and manage branch resources and staff assignments.', 'audience' => 'external'],
            ['key' => 'customs.read', 'group' => 'customs', 'display_name' => 'View customs', 'description' => 'Read customs declarations, brokers, documents, and duties.', 'audience' => 'both'],
            ['key' => 'customs.manage', 'group' => 'customs', 'display_name' => 'Manage customs', 'description' => 'Create and manage customs declarations, brokers, and verification actions.', 'audience' => 'both'],
            ['key' => 'containers.read', 'group' => 'containers', 'display_name' => 'View containers', 'description' => 'Read container resources and shipment assignments.', 'audience' => 'both'],
            ['key' => 'containers.manage', 'group' => 'containers', 'display_name' => 'Manage containers', 'description' => 'Create and manage containers and shipment assignments.', 'audience' => 'both'],
            ['key' => 'vessels.read', 'group' => 'vessels', 'display_name' => 'View vessels', 'description' => 'Read vessel resources.', 'audience' => 'both'],
            ['key' => 'vessels.manage', 'group' => 'vessels', 'display_name' => 'Manage vessels', 'description' => 'Create and manage vessel resources.', 'audience' => 'both'],
            ['key' => 'vessel_schedules.read', 'group' => 'vessel_schedules', 'display_name' => 'View vessel schedules', 'description' => 'Read vessel schedules and schedule stats.', 'audience' => 'both'],
            ['key' => 'vessel_schedules.manage', 'group' => 'vessel_schedules', 'display_name' => 'Manage vessel schedules', 'description' => 'Create and manage vessel schedules.', 'audience' => 'both'],
            ['key' => 'claims.read', 'group' => 'claims', 'display_name' => 'View claims', 'description' => 'Read claim resources, histories, and documents.', 'audience' => 'both'],
            ['key' => 'claims.manage', 'group' => 'claims', 'display_name' => 'Manage claims', 'description' => 'Create and manage claim resources and claim documents.', 'audience' => 'both'],
            ['key' => 'risk.read', 'group' => 'risk', 'display_name' => 'View risk insights', 'description' => 'Read risk scoring and risk stats.', 'audience' => 'both'],
            ['key' => 'risk.manage', 'group' => 'risk', 'display_name' => 'Manage risk workflows', 'description' => 'Generate risk scores and manage route risk workflows.', 'audience' => 'both'],
            ['key' => 'drivers.read', 'group' => 'drivers', 'display_name' => 'View drivers', 'description' => 'Read driver resources and stats.', 'audience' => 'both'],
            ['key' => 'drivers.manage', 'group' => 'drivers', 'display_name' => 'Manage drivers', 'description' => 'Create and manage driver resources.', 'audience' => 'both'],
            ['key' => 'delivery.read', 'group' => 'delivery', 'display_name' => 'View delivery operations', 'description' => 'Read delivery dashboards and assignments.', 'audience' => 'both'],
            ['key' => 'delivery.manage', 'group' => 'delivery', 'display_name' => 'Manage delivery operations', 'description' => 'Manage last-mile delivery assignments and outcomes.', 'audience' => 'both'],
            ['key' => 'proof_of_deliveries.read', 'group' => 'proof_of_deliveries', 'display_name' => 'View proof of delivery', 'description' => 'Read proof-of-delivery records.', 'audience' => 'both'],
            ['key' => 'incoterms.read', 'group' => 'incoterms', 'display_name' => 'View incoterms', 'description' => 'Read incoterm resources.', 'audience' => 'both'],
            ['key' => 'incoterms.manage', 'group' => 'incoterms', 'display_name' => 'Manage incoterms', 'description' => 'Create and manage incoterm resources.', 'audience' => 'both'],
            ['key' => 'hs_codes.read', 'group' => 'hs_codes', 'display_name' => 'View HS codes', 'description' => 'Read HS code search and catalog resources.', 'audience' => 'both'],
            ['key' => 'hs_codes.manage', 'group' => 'hs_codes', 'display_name' => 'Manage HS codes', 'description' => 'Create and manage HS code resources.', 'audience' => 'both'],
            ['key' => 'tariffs.read', 'group' => 'tariffs', 'display_name' => 'View tariffs', 'description' => 'Read tariff resources.', 'audience' => 'both'],
            ['key' => 'tariffs.manage', 'group' => 'tariffs', 'display_name' => 'Manage tariffs', 'description' => 'Create and manage tariff calculations and tariff resources.', 'audience' => 'both'],
            ['key' => 'tax_rules.read', 'group' => 'tax_rules', 'display_name' => 'View tax rules', 'description' => 'Read tax rule resources.', 'audience' => 'both'],
            ['key' => 'tax_rules.manage', 'group' => 'tax_rules', 'display_name' => 'Manage tax rules', 'description' => 'Create and manage tax rule resources.', 'audience' => 'both'],
            ['key' => 'booking.manage', 'group' => 'booking', 'display_name' => 'Manage bookings', 'description' => 'Create, confirm, cancel, and quote bookings.', 'audience' => 'both'],
            ['key' => 'shipment_workflow.read', 'group' => 'shipment_workflow', 'display_name' => 'View shipment workflow', 'description' => 'Read shipment workflow statuses and SLA checks.', 'audience' => 'both'],
            ['key' => 'shipment_workflow.manage', 'group' => 'shipment_workflow', 'display_name' => 'Manage shipment workflow', 'description' => 'Run shipment workflow transitions.', 'audience' => 'both'],
            ['key' => 'insurance.manage', 'group' => 'insurance', 'display_name' => 'Manage insurance', 'description' => 'Quote, purchase, and claim shipment insurance.', 'audience' => 'both'],
            ['key' => 'sla.read', 'group' => 'sla', 'display_name' => 'View SLA insights', 'description' => 'Read SLA dashboards, checks, and breach scans.', 'audience' => 'both'],
            ['key' => 'content_declarations.read', 'group' => 'content_declarations', 'display_name' => 'View content declarations', 'description' => 'Read content declaration resources.', 'audience' => 'both'],
            ['key' => 'content_declarations.manage', 'group' => 'content_declarations', 'display_name' => 'Manage content declarations', 'description' => 'Create and manage content declaration resources.', 'audience' => 'both'],
            ['key' => 'route_optimization.read', 'group' => 'route_optimization', 'display_name' => 'View route optimization', 'description' => 'Read route optimization plans and cost factors.', 'audience' => 'both'],
            ['key' => 'route_optimization.manage', 'group' => 'route_optimization', 'display_name' => 'Manage route optimization', 'description' => 'Create route optimization plans and select optimized routes.', 'audience' => 'both'],
            ['key' => 'capacity.read', 'group' => 'capacity', 'display_name' => 'View capacity', 'description' => 'Read capacity pools and capacity stats.', 'audience' => 'both'],
            ['key' => 'capacity.manage', 'group' => 'capacity', 'display_name' => 'Manage capacity', 'description' => 'Create capacity pools and capacity bookings.', 'audience' => 'both'],
            ['key' => 'currency.read', 'group' => 'currency', 'display_name' => 'View currency', 'description' => 'Read currency rates, transactions, and FX reports.', 'audience' => 'both'],
            ['key' => 'currency.manage', 'group' => 'currency', 'display_name' => 'Manage currency', 'description' => 'Set rates and run currency conversions.', 'audience' => 'both'],
        ];
    }
}
