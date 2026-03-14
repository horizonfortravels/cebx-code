<?php

namespace App\Rbac;

class PermissionsCatalog
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function all(): array
    {
        $catalog = [
            'users' => [
                'users.view' => 'View users',
                'users.manage' => 'Manage users',
                'users.invite' => 'Invite users',
            ],
            'roles' => [
                'roles.view' => 'View roles',
                'roles.manage' => 'Manage roles',
                'roles.assign' => 'Assign roles',
            ],
            'account' => [
                'account.view' => 'View account settings',
                'account.manage' => 'Manage account settings',
            ],
            'shipments' => [
                'shipments.view' => 'View shipments',
                'shipments.create' => 'Create shipments',
                'shipments.update_draft' => 'Update shipment drafts',
                'shipments.edit' => 'Edit shipments',
                'shipments.cancel' => 'Cancel shipments',
                'shipments.print' => 'Print shipments',
                'shipments.export' => 'Export shipments',
                'shipments.manage' => 'Manage shipment lifecycle',
                'shipments.print_label' => 'Print shipment labels',
                'shipments.view_financial' => 'View shipment financial fields',
            ],
            'orders' => [
                'orders.view' => 'View orders',
                'orders.manage' => 'Manage orders',
                'orders.export' => 'Export orders',
            ],
            'stores' => [
                'stores.view' => 'View stores',
                'stores.manage' => 'Manage stores',
            ],
            'financial' => [
                'financial.view' => 'View financial data',
                'financial.profit.view' => 'View profit data',
                'financial.cards.view' => 'View card data',
                'financial.wallet_topup' => 'Top up wallet',
                'financial.wallet_view' => 'View wallet balance',
                'financial.ledger_view' => 'View ledger',
                'financial.invoices_view' => 'View invoices',
                'financial.invoices_manage' => 'Manage invoices',
                'financial.refund_review' => 'Review refunds',
                'financial.threshold' => 'Manage financial thresholds',
            ],
            'wallet' => [
                'wallet.balance' => 'View wallet balance',
                'wallet.ledger' => 'View wallet ledger',
                'wallet.topup' => 'Top up wallet',
                'wallet.configure' => 'Configure wallet settings',
            ],
            'billing' => [
                'billing.view' => 'View billing methods',
                'billing.manage' => 'Manage billing methods',
            ],
            'reports' => [
                'reports.view' => 'View reports',
                'reports.export' => 'Export reports',
                'reports.create' => 'Create reports',
            ],
            'kyc' => [
                'kyc.view' => 'View KYC status',
                'kyc.manage' => 'Manage KYC',
                'kyc.documents' => 'Access KYC documents',
            ],
            'api_keys' => [
                'api_keys.read' => 'View API keys',
                'api_keys.manage' => 'Manage API keys',
            ],
            'webhooks' => [
                'webhooks.read' => 'View webhooks',
                'webhooks.manage' => 'Manage webhooks',
            ],
            'rates' => [
                'rates.view_breakdown' => 'View pricing breakdown',
                'rates.manage_rules' => 'Manage pricing rules',
            ],
            'audit' => [
                'audit.view' => 'View audit logs',
                'audit.export' => 'Export audit logs',
            ],
            'tenancy' => [
                'tenancy.context.select' => 'Select tenant context',
            ],
            'admin' => [
                'admin.access' => 'Access admin APIs',
            ],
        ];

        self::assertDotNotation($catalog);

        return $catalog;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (static::all() as $permissions) {
            $keys = array_merge($keys, array_keys($permissions));
        }

        return array_values(array_unique($keys));
    }

    public static function exists(string $key): bool
    {
        return in_array($key, static::keys(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        return array_keys(static::all());
    }

    /**
     * @return array<string, array{display_name: string, description: string, permissions: array<int, string>}>
     */
    public static function templates(): array
    {
        return [
            'admin' => [
                'display_name' => 'System Admin',
                'description' => 'Full access role.',
                'permissions' => static::keys(),
            ],
            'accountant' => [
                'display_name' => 'Accountant',
                'description' => 'Financial and reporting access.',
                'permissions' => [
                    'financial.view',
                    'financial.profit.view',
                    'financial.cards.view',
                    'financial.wallet_view',
                    'financial.ledger_view',
                    'financial.invoices_view',
                    'financial.invoices_manage',
                    'financial.refund_review',
                    'financial.threshold',
                    'reports.view',
                    'reports.export',
                    'audit.view',
                ],
            ],
            'warehouse' => [
                'display_name' => 'Warehouse',
                'description' => 'Operations role for fulfillment.',
                'permissions' => [
                    'shipments.view',
                    'shipments.create',
                    'shipments.edit',
                    'shipments.print',
                    'shipments.export',
                    'orders.view',
                    'orders.manage',
                    'orders.export',
                    'stores.view',
                ],
            ],
            'viewer' => [
                'display_name' => 'Viewer',
                'description' => 'Read-only access.',
                'permissions' => [
                    'users.view',
                    'roles.view',
                    'account.view',
                    'shipments.view',
                    'orders.view',
                    'stores.view',
                    'reports.view',
                    'kyc.view',
                    'audit.view',
                ],
            ],
            'printer' => [
                'display_name' => 'Printer',
                'description' => 'Shipment printing role without financial access.',
                'permissions' => [
                    'shipments.view',
                    'shipments.print',
                    'orders.view',
                ],
            ],
        ];
    }

    /**
     * @return array{display_name: string, description: string, permissions: array<int, string>}|null
     */
    public static function template(string $name): ?array
    {
        return static::templates()[$name] ?? null;
    }

    /**
     * @param array<string, array<string, string>> $catalog
     */
    private static function assertDotNotation(array $catalog): void
    {
        foreach ($catalog as $group => $permissions) {
            foreach ($permissions as $key => $description) {
                if (str_contains($key, ':')) {
                    throw new \RuntimeException(
                        sprintf('Phase 2B2 requires dot-notation permission keys only. Invalid key "%s" in group "%s".', $key, $group)
                    );
                }
            }
        }
    }
}
