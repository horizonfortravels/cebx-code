<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\BillingWallet;
use App\Models\CarrierDocument;
use App\Models\CarrierShipment;
use App\Models\ContentDeclaration;
use App\Models\DgAuditLog;
use App\Models\DgMetadata;
use App\Models\Invitation;
use App\Models\KycDocument;
use App\Models\KycVerification;
use App\Models\Notification;
use App\Models\OrganizationProfile;
use App\Models\Parcel;
use App\Models\PaymentGateway;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\StoreSyncLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\TicketReply;
use App\Models\TrackingWebhook;
use App\Models\User;
use App\Models\VerificationRestriction;
use App\Models\WalletHold;
use App\Models\WalletLedgerEntry;
use App\Models\WalletTopup;
use App\Models\WaiverVersion;
use App\Models\WebhookEvent;
use App\Models\FeatureFlag;
use App\Models\IntegrationHealthLog;
use App\Services\AuditService;
use App\Support\CanonicalShipmentStatus;
use App\Support\Kyc\AccountKycStatusMapper;
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
                    'accounts.read',
                    'accounts.create',
                    'accounts.update',
                    'accounts.lifecycle.manage',
                    'accounts.support.manage',
                    'accounts.members.manage',
                    'admin.access',
                    'tenancy.context.select',
                    'users.read',
                    'users.manage',
                    'users.invite',
                    'roles.read',
                    'roles.manage',
                    'roles.assign',
                    'shipments.read',
                    'shipments.documents.read',
                    'shipments.manage',
                    'orders.read',
                    'orders.manage',
                    'wallet.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'wallet.manage',
                    'api_keys.read',
                    'api_keys.manage',
                    'feature_flags.read',
                    'feature_flags.manage',
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
                    'compliance.read',
                    'dg.read',
                ]
            );

        $supportRoleId = $this->resolveInternalRoleId('support')
            ?? $this->ensureInternalRole(
                name: 'support',
                displayName: 'Support',
                description: 'Fallback support role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'accounts.read',
                    'accounts.support.manage',
                    'api_keys.read',
                    'feature_flags.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'integrations.read',
                    'webhooks.read',
                    'shipments.read',
                    'shipments.documents.read',
                    'tickets.read',
                    'tickets.manage',
                    'compliance.read',
                    'dg.read',
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
                    'api_keys.read',
                    'feature_flags.read',
                    'wallet.balance',
                    'wallet.ledger',
                    'integrations.read',
                    'webhooks.read',
                    'tickets.read',
                    'shipments.read',
                    'shipments.documents.read',
                    'compliance.read',
                    'dg.read',
                ]
            );

        $carrierManagerRoleId = $this->resolveInternalRoleId('carrier_manager')
            ?? $this->ensureInternalRole(
                name: 'carrier_manager',
                displayName: 'CarrierManager',
                description: 'Fallback carrier manager role seeded by E2EUserMatrixSeeder.',
                permissionKeys: [
                    'notifications.channels.manage',
                    'integrations.read',
                    'shipments.documents.read',
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

        $this->seedDeterministicPendingInvitations($accounts, $externalUsers);
        $this->seedDeterministicWallets($accounts);
        $this->seedDeterministicKycFixtures($accounts, $externalUsers);
        $this->seedDeterministicShipmentReadFixtures($accounts, $externalUsers);
        $this->seedDeterministicComplianceFixtures($accounts, $externalUsers);
        $this->seedDeterministicWalletReadFixtures($accounts, $externalUsers);
        $this->seedDeterministicIntegrationReadFixtures($accounts);
        $this->seedDeterministicTicketFixtures($accounts, $externalUsers, [
            'super_admin' => $internalSuperAdmin,
            'support' => $internalSupport,
        ]);

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

    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     */
    private function seedDeterministicWalletReadFixtures(array $accounts, array $externalUsers): void
    {
        if (!Schema::hasTable('billing_wallets') || !Schema::hasTable('wallet_holds') || !Schema::hasTable('wallet_ledger_entries')) {
            return;
        }

        $walletA = BillingWallet::query()->withoutGlobalScopes()
            ->where('account_id', (string) $accounts['a']->id)
            ->where('currency', 'USD')
            ->first();

        if (!$walletA instanceof BillingWallet) {
            return;
        }

        $shipmentA = Shipment::query()->withoutGlobalScopes()
            ->where('reference_number', 'SHP-I5A-A-001')
            ->first();

        if (!$shipmentA instanceof Shipment) {
            return;
        }

        $walletA->forceFill([
            'available_balance' => 980.00,
            'reserved_balance' => 25.00,
            'total_credited' => 1200.00,
            'total_debited' => 220.00,
            'status' => 'active',
        ])->save();

        $shipmentA->forceFill($this->filterExistingColumns('shipments', [
            'status' => Shipment::STATUS_PAYMENT_PENDING,
            'currency' => 'USD',
            'total_charge' => 25.00,
            'reserved_amount' => 25.00,
        ]))->save();

        $shipmentCaptured = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I6B-A-002'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['a']->id,
                'user_id' => (string) $externalUsers['a']['primary']->id,
                'created_by' => (string) $externalUsers['a']['primary']->id,
                'reference_number' => 'SHP-I6B-A-002',
                'source' => Shipment::SOURCE_DIRECT,
                'status' => Shipment::STATUS_PURCHASED,
                'sender_name' => 'E2E A Billing Sender',
                'sender_phone' => '+966500100111',
                'sender_address_1' => 'Riyadh Billing Hub 1',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I6B Captured Recipient',
                'recipient_phone' => '+966500100112',
                'recipient_address_1' => 'Jeddah Billing District 7',
                'recipient_city' => 'Jeddah',
                'recipient_country' => 'SA',
                'is_international' => false,
                'currency' => 'USD',
                'total_charge' => 52.00,
                'reserved_amount' => 52.00,
                'tracking_number' => 'I6B-A-002',
                'total_weight' => 1.4,
                'parcels_count' => 1,
                'pieces' => 1,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(3),
            ])
        );

        $shipmentReleased = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I6B-A-003'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['a']->id,
                'user_id' => (string) $externalUsers['a']['primary']->id,
                'created_by' => (string) $externalUsers['a']['primary']->id,
                'reference_number' => 'SHP-I6B-A-003',
                'source' => Shipment::SOURCE_DIRECT,
                'status' => Shipment::STATUS_REQUIRES_ACTION,
                'sender_name' => 'E2E A Billing Sender',
                'sender_phone' => '+966500100113',
                'sender_address_1' => 'Riyadh Billing Hub 2',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I6B Released Recipient',
                'recipient_phone' => '+966500100114',
                'recipient_address_1' => 'Dammam Billing District 3',
                'recipient_city' => 'Dammam',
                'recipient_country' => 'SA',
                'is_international' => false,
                'currency' => 'USD',
                'total_charge' => 18.00,
                'reserved_amount' => 0.00,
                'total_weight' => 1.1,
                'parcels_count' => 1,
                'pieces' => 1,
                'created_at' => now()->subHours(4),
                'updated_at' => now()->subHours(2),
            ])
        );

        $topup = WalletTopup::query()->withoutGlobalScopes()->updateOrCreate(
            ['wallet_id' => (string) $walletA->id, 'idempotency_key' => 'e2e:i6b:topup:a'],
            $this->filterExistingColumns('wallet_topups', [
                'wallet_id' => (string) $walletA->id,
                'account_id' => (string) $accounts['a']->id,
                'amount' => 200.00,
                'currency' => 'USD',
                'status' => WalletTopup::STATUS_SUCCESS,
                'payment_gateway' => 'seeded-fixture',
                'idempotency_key' => 'e2e:i6b:topup:a',
                'confirmed_at' => now()->subHours(8),
            ])
        );

        $activeHold = WalletHold::query()->withoutGlobalScopes()->updateOrCreate(
            ['wallet_id' => (string) $walletA->id, 'idempotency_key' => 'e2e:i6b:hold:active:a'],
            $this->filterExistingColumns('wallet_holds', [
                'wallet_id' => (string) $walletA->id,
                'account_id' => (string) $accounts['a']->id,
                'amount' => 25.00,
                'currency' => 'USD',
                'shipment_id' => (string) $shipmentA->id,
                'source' => 'shipment_preflight',
                'status' => WalletHold::STATUS_ACTIVE,
                'idempotency_key' => 'e2e:i6b:hold:active:a',
                'expires_at' => now()->subHours(2),
                'created_at' => now()->subHours(6),
            ])
        );

        $capturedHold = WalletHold::query()->withoutGlobalScopes()->updateOrCreate(
            ['wallet_id' => (string) $walletA->id, 'idempotency_key' => 'e2e:i6b:hold:captured:a'],
            $this->filterExistingColumns('wallet_holds', [
                'wallet_id' => (string) $walletA->id,
                'account_id' => (string) $accounts['a']->id,
                'amount' => 52.00,
                'currency' => 'USD',
                'shipment_id' => (string) $shipmentCaptured->id,
                'source' => 'shipment_preflight',
                'status' => WalletHold::STATUS_CAPTURED,
                'idempotency_key' => 'e2e:i6b:hold:captured:a',
                'captured_at' => now()->subHours(2),
                'expires_at' => now()->addHours(4),
                'created_at' => now()->subHours(5),
            ])
        );

        $releasedHold = WalletHold::query()->withoutGlobalScopes()->updateOrCreate(
            ['wallet_id' => (string) $walletA->id, 'idempotency_key' => 'e2e:i6b:hold:released:a'],
            $this->filterExistingColumns('wallet_holds', [
                'wallet_id' => (string) $walletA->id,
                'account_id' => (string) $accounts['a']->id,
                'amount' => 18.00,
                'currency' => 'USD',
                'shipment_id' => (string) $shipmentReleased->id,
                'source' => 'shipment_preflight',
                'status' => WalletHold::STATUS_RELEASED,
                'idempotency_key' => 'e2e:i6b:hold:released:a',
                'released_at' => now()->subHour(),
                'expires_at' => now()->addHours(3),
                'created_at' => now()->subHours(4),
            ])
        );

        $shipmentA->forceFill($this->filterExistingColumns('shipments', [
            'balance_reservation_id' => (string) $activeHold->id,
            'reserved_amount' => 25.00,
        ]))->save();

        $shipmentCaptured->forceFill($this->filterExistingColumns('shipments', [
            'balance_reservation_id' => (string) $capturedHold->id,
            'reserved_amount' => 52.00,
        ]))->save();

        $shipmentReleased->forceFill($this->filterExistingColumns('shipments', [
            'balance_reservation_id' => (string) $releasedHold->id,
            'reserved_amount' => 0.00,
        ]))->save();

        $this->seedWalletLedgerEntry($walletA, 1, 'e2e:i6b:ledger:topup', 'topup', 'credit', 200.00, 1200.00, 'topup', (string) $topup->id, 'Seeded top-up visibility fixture', now()->subHours(8));
        $this->seedWalletLedgerEntry($walletA, 2, 'e2e:i6b:ledger:adjustment', WalletLedgerEntry::TYPE_ADJUSTMENT, 'debit', 20.00, 1180.00, 'adjustment', 'e2e:i6b:adjustment:a', 'Manual credit review adjustment', now()->subHours(7));
        $this->seedWalletLedgerEntry($walletA, 3, 'e2e:i6b:ledger:hold-active', 'hold', 'debit', 25.00, 1155.00, 'hold', (string) $activeHold->id, 'Shipment preflight reserved funds for the active reservation.', now()->subHours(6));
        $this->seedWalletLedgerEntry($walletA, 4, 'e2e:i6b:ledger:hold-captured', 'hold', 'debit', 52.00, 1103.00, 'hold', (string) $capturedHold->id, 'Shipment preflight reserved funds for the captured reservation.', now()->subHours(5));
        $this->seedWalletLedgerEntry($walletA, 5, 'e2e:i6b:ledger:hold-capture', 'hold_capture', 'debit', 52.00, 1051.00, 'shipment', (string) $shipmentCaptured->id, 'Reservation captured when the shipment moved forward.', now()->subHours(4));
        $this->seedWalletLedgerEntry($walletA, 6, 'e2e:i6b:ledger:hold-released', 'hold', 'debit', 18.00, 1033.00, 'hold', (string) $releasedHold->id, 'Shipment preflight reserved funds before the shipment returned to requires action.', now()->subHours(3));
        $this->seedWalletLedgerEntry($walletA, 7, 'e2e:i6b:ledger:hold-release', 'hold_release', 'credit', 18.00, 1051.00, 'hold', (string) $releasedHold->id, 'Reservation released after the shipment required more action.', now()->subHours(2));
        $this->seedWalletLedgerEntry($walletA, 8, 'e2e:i6b:ledger:shipment-debit', 'debit', 'debit', 45.00, 1006.00, 'shipment', (string) $shipmentA->id, 'Shipment debit after label purchase.', now()->subHour());
    }

    private function seedWalletLedgerEntry(
        BillingWallet $wallet,
        int $sequence,
        string $correlationId,
        string $transactionType,
        string $direction,
        float $amount,
        float $runningBalance,
        string $referenceType,
        string $referenceId,
        string $notes,
        \Illuminate\Support\Carbon $createdAt
    ): void {
        WalletLedgerEntry::query()->withoutGlobalScopes()->updateOrCreate(
            ['wallet_id' => (string) $wallet->id, 'correlation_id' => $correlationId],
            $this->filterExistingColumns('wallet_ledger_entries', [
                'wallet_id' => (string) $wallet->id,
                'sequence' => $sequence,
                'correlation_id' => $correlationId,
                'transaction_type' => $transactionType,
                'direction' => $direction,
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
                'created_at' => $createdAt,
            ])
        );
    }

    /**
     * @param array<string, Account> $accounts
     */
    private function seedDeterministicIntegrationReadFixtures(array $accounts): void
    {
        if (Schema::hasTable('stores')) {
            $storeLookup = $this->filterExistingColumns('stores', [
                'account_id' => (string) $accounts['c']->id,
                'slug' => 'i8a-shopify',
                'name' => 'I8A Shopify Store',
            ]);

            $storeValues = $this->filterExistingColumns('stores', [
                'account_id' => (string) $accounts['c']->id,
                'name' => 'I8A Shopify Store',
                'slug' => 'i8a-shopify',
                'platform' => 'shopify',
                'currency' => 'USD',
                'language' => 'en',
                'timezone' => 'UTC',
                'external_store_id' => 'gid://shopify/Store/1001',
                'external_store_url' => 'https://i8a-shopify.example.test',
                'connection_config' => json_encode([
                    'access_token' => 'i8a-shopify-token-001',
                    'webhook_secret' => 'i8a-shopify-webhook-secret-001',
                    'client_id' => 'i8a-shopify-client-id-001',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_synced_at' => now()->subMinutes(45),
                'store_url' => 'https://i8a-shopify.example.test',
                'api_key' => 'i8a-shopify-token-001',
                'api_secret' => 'i8a-shopify-webhook-secret-001',
                'last_sync_at' => now()->subMinutes(45),
                'created_by' => null,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subMinutes(30),
            ]);

            if (Schema::hasColumn('stores', 'connection_status')) {
                $storeValues['status'] = 'active';
                $storeValues['connection_status'] = 'connected';
            } elseif (Schema::hasColumn('stores', 'status')) {
                $storeValues['status'] = 'connected';
            }

            $existingStoreId = DB::table('stores')
                ->where($storeLookup)
                ->value('id');

            $storeId = $existingStoreId ? (string) $existingStoreId : (string) Str::uuid();

            if ($existingStoreId) {
                DB::table('stores')
                    ->where('id', $storeId)
                    ->update($storeValues);
            } else {
                DB::table('stores')->insert(array_merge(
                    ['id' => $storeId],
                    $storeLookup,
                    $storeValues
                ));
            }

            if (Schema::hasTable('store_sync_logs')) {
                StoreSyncLog::query()->withoutGlobalScopes()->updateOrCreate(
                    ['store_id' => $storeId, 'sync_type' => StoreSyncLog::SYNC_WEBHOOK],
                    $this->filterExistingColumns('store_sync_logs', [
                        'account_id' => (string) $accounts['c']->id,
                        'store_id' => $storeId,
                        'sync_type' => StoreSyncLog::SYNC_WEBHOOK,
                        'status' => StoreSyncLog::STATUS_COMPLETED,
                        'orders_found' => 4,
                        'orders_imported' => 3,
                        'orders_skipped' => 1,
                        'orders_failed' => 0,
                        'errors' => [],
                        'retry_count' => 0,
                        'started_at' => now()->subMinutes(46),
                        'completed_at' => now()->subMinutes(45),
                        'created_at' => now()->subMinutes(46),
                        'updated_at' => now()->subMinutes(45),
                    ])
                );
            }

            if (Schema::hasTable('webhook_events')) {
                WebhookEvent::query()->withoutGlobalScopes()->updateOrCreate(
                    ['store_id' => $storeId, 'external_event_id' => 'i8a-shopify-webhook-001'],
                    $this->filterExistingColumns('webhook_events', [
                        'account_id' => (string) $accounts['c']->id,
                        'store_id' => $storeId,
                        'platform' => 'shopify',
                        'event_type' => 'orders/updated',
                        'external_event_id' => 'i8a-shopify-webhook-001',
                        'external_resource_id' => '1001-ORD',
                        'status' => WebhookEvent::STATUS_PROCESSED,
                        'payload' => ['safe' => 'masked'],
                        'error_message' => null,
                        'retry_count' => 0,
                        'processed_at' => now()->subMinutes(44),
                        'created_at' => now()->subMinutes(44),
                        'updated_at' => now()->subMinutes(44),
                    ])
                );

                WebhookEvent::query()->withoutGlobalScopes()->updateOrCreate(
                    ['store_id' => $storeId, 'external_event_id' => 'i8b-shopify-webhook-failed-001'],
                    $this->filterExistingColumns('webhook_events', [
                        'account_id' => (string) $accounts['c']->id,
                        'store_id' => $storeId,
                        'platform' => 'shopify',
                        'event_type' => 'orders/create',
                        'external_event_id' => 'i8b-shopify-webhook-failed-001',
                        'external_resource_id' => '1002-ORD',
                        'status' => WebhookEvent::STATUS_FAILED,
                        'payload' => [
                            'id' => 1002,
                            'order_number' => '1002',
                            'name' => '#1002',
                            'currency' => 'USD',
                            'subtotal_price' => '34.00',
                            'total_tax' => '0.00',
                            'total_discounts' => '0.00',
                            'total_price' => '40.00',
                            'shipping_lines' => [
                                ['price' => '6.00'],
                            ],
                            'customer' => [
                                'first_name' => 'Webhook',
                                'last_name' => 'Retry',
                                'email' => 'webhook.retry@example.test',
                                'phone' => '+966500200100',
                            ],
                            'shipping_address' => [
                                'first_name' => 'Webhook',
                                'last_name' => 'Retry',
                                'phone' => '+966500200100',
                                'address1' => 'Webhook Street 22',
                                'address2' => 'Suite 5',
                                'city' => 'Riyadh',
                                'province' => 'Riyadh',
                                'zip' => '12345',
                                'country_code' => 'SA',
                            ],
                            'line_items' => [
                                [
                                    'id' => 501,
                                    'sku' => 'I8B-RETRY-001',
                                    'title' => 'Webhook retry item',
                                    'quantity' => 1,
                                    'price' => '34.00',
                                    'grams' => 1200,
                                ],
                            ],
                            'created_at' => now()->subMinutes(16)->toIso8601String(),
                            'updated_at' => now()->subMinutes(15)->toIso8601String(),
                        ],
                        'error_message' => 'Timed out while a prior worker attempted to import this delivery.',
                        'retry_count' => 1,
                        'processed_at' => null,
                        'created_at' => now()->subMinutes(15),
                        'updated_at' => now()->subMinutes(15),
                    ])
                );
            }
        }

        if (Schema::hasTable('payment_gateways')) {
            PaymentGateway::query()->updateOrCreate(
                ['slug' => 'moyasar'],
                [
                    'name' => 'Moyasar',
                    'slug' => 'moyasar',
                    'provider' => 'moyasar',
                    'supported_currencies' => ['SAR', 'USD'],
                    'supported_methods' => ['card', 'apple_pay'],
                    'is_active' => true,
                    'is_sandbox' => true,
                    'sort_order' => 1,
                    'transaction_fee_pct' => 2.75,
                    'transaction_fee_fixed' => 1.00,
                ]
            );
        }

        if (Schema::hasTable('integration_health_logs')) {
            foreach ([
                ['service' => 'carrier:dhl', 'status' => IntegrationHealthLog::STATUS_HEALTHY, 'response_time_ms' => 220, 'error_rate' => 0.0, 'total_requests' => 140, 'failed_requests' => 1],
                ['service' => 'carrier:fedex', 'status' => IntegrationHealthLog::STATUS_DEGRADED, 'response_time_ms' => 610, 'error_rate' => 1.5, 'total_requests' => 88, 'failed_requests' => 4],
                ['service' => 'store:shopify', 'status' => IntegrationHealthLog::STATUS_HEALTHY, 'response_time_ms' => 180, 'error_rate' => 0.0, 'total_requests' => 64, 'failed_requests' => 0],
                ['service' => 'gateway:moyasar', 'status' => IntegrationHealthLog::STATUS_HEALTHY, 'response_time_ms' => 240, 'error_rate' => 0.0, 'total_requests' => 52, 'failed_requests' => 0],
            ] as $row) {
                IntegrationHealthLog::query()->updateOrCreate(
                    ['service' => $row['service']],
                    array_merge($row, [
                        'error_message' => null,
                        'correlation_id' => 'i8a-' . str_replace(':', '-', $row['service']) . '-health',
                        'metadata' => ['safe' => true],
                        'checked_at' => now()->subMinutes(20),
                    ])
                );
            }
        }

        if (Schema::hasTable('tracking_webhooks')) {
            TrackingWebhook::query()->updateOrCreate(
                ['carrier_code' => 'dhl', 'message_reference' => 'i8a-dhl-webhook-001'],
                [
                    'carrier_code' => 'dhl',
                    'signature' => 'masked-signature',
                    'signature_valid' => true,
                    'message_reference' => 'i8a-dhl-webhook-001',
                    'replay_token' => 'masked-replay-token',
                    'source_ip' => '198.51.100.20',
                    'user_agent' => 'I8A webhook agent',
                    'headers' => ['x-safe' => 'masked'],
                    'event_type' => 'shipment.updated',
                    'tracking_number' => 'I8A-DHL-0001',
                    'payload' => ['safe' => 'masked'],
                    'payload_size' => 512,
                    'status' => TrackingWebhook::STATUS_PROCESSED,
                    'rejection_reason' => null,
                    'events_extracted' => 2,
                    'processing_time_ms' => 140,
                ]
            );

            TrackingWebhook::query()->updateOrCreate(
                ['carrier_code' => 'dhl', 'message_reference' => 'i8b-dhl-webhook-failed-001'],
                [
                    'carrier_code' => 'dhl',
                    'signature' => 'masked-signature-failed',
                    'signature_valid' => false,
                    'message_reference' => 'i8b-dhl-webhook-failed-001',
                    'replay_token' => 'masked-replay-token-failed',
                    'source_ip' => '198.51.100.21',
                    'user_agent' => 'I8B webhook agent',
                    'headers' => ['x-safe' => 'masked'],
                    'event_type' => 'shipment.updated',
                    'tracking_number' => 'I8B-DHL-0002',
                    'payload' => ['safe' => 'masked'],
                    'payload_size' => 480,
                    'status' => TrackingWebhook::STATUS_REJECTED,
                    'rejection_reason' => 'Signature validation failed for the stored webhook delivery.',
                    'events_extracted' => 0,
                    'processing_time_ms' => 92,
                ]
            );
        }

        if (Schema::hasTable('feature_flags')) {
            foreach ([
                ['key' => 'carrier_dhl', 'name' => 'Carrier DHL', 'description' => 'Enable DHL carrier workflows', 'is_enabled' => true],
                ['key' => 'ecommerce_shopify', 'name' => 'Ecommerce Shopify', 'description' => 'Enable Shopify store connector', 'is_enabled' => true],
                ['key' => 'payment_moyasar', 'name' => 'Payment Moyasar', 'description' => 'Enable Moyasar payment gateway', 'is_enabled' => true],
                ['key' => 'internal_ops_visibility_fixture', 'name' => 'I8D Internal Ops Fixture', 'description' => 'Seeded DB-backed flag for safe internal feature-flag verification.', 'is_enabled' => true],
            ] as $flag) {
                FeatureFlag::query()->updateOrCreate(
                    ['key' => $flag['key']],
                    array_merge($flag, [
                        'rollout_percentage' => $flag['key'] === 'internal_ops_visibility_fixture' ? 50 : 100,
                        'target_accounts' => $flag['key'] === 'internal_ops_visibility_fixture'
                            ? [(string) $accounts['c']->id]
                            : [],
                        'target_plans' => $flag['key'] === 'internal_ops_visibility_fixture'
                            ? ['enterprise']
                            : [],
                        'created_by' => 'e2e-seeder',
                    ])
                );
            }
        }

        if (Schema::hasTable('api_keys')) {
            $internalSuperAdminId = (string) User::query()->withoutGlobalScopes()
                ->where('email', 'e2e.internal.super_admin@example.test')
                ->value('id');

            $activeRawKey = 'sgw_i8c_seed_active_001';
            ApiKey::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'account_id' => (string) $accounts['c']->id,
                    'name' => 'I8C Active Operations Key',
                ],
                [
                    'created_by' => $internalSuperAdminId,
                    'key_prefix' => substr($activeRawKey, 0, 8),
                    'key_hash' => hash('sha256', $activeRawKey),
                    'scopes' => ['shipments:read'],
                    'allowed_ips' => ['203.0.113.20'],
                    'last_used_at' => now()->subHours(4),
                    'expires_at' => now()->addDays(30),
                    'revoked_at' => null,
                    'is_active' => true,
                    'created_at' => now()->subDays(4),
                    'updated_at' => now()->subHours(4),
                ]
            );

            $revokedRawKey = 'sgw_i8c_seed_revoked_001';
            ApiKey::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'account_id' => (string) $accounts['a']->id,
                    'name' => 'I8C Revoked Legacy Key',
                ],
                [
                    'created_by' => $internalSuperAdminId,
                    'key_prefix' => substr($revokedRawKey, 0, 8),
                    'key_hash' => hash('sha256', $revokedRawKey),
                    'scopes' => ['shipments:read'],
                    'allowed_ips' => ['198.51.100.44'],
                    'last_used_at' => now()->subDays(8),
                    'expires_at' => now()->addDays(7),
                    'revoked_at' => now()->subDays(2),
                    'is_active' => false,
                    'created_at' => now()->subDays(12),
                    'updated_at' => now()->subDays(2),
                ]
            );
        }
    }

    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     */
    private function seedDeterministicKycFixtures(array $accounts, array $externalUsers): void
    {
        if (!Schema::hasTable('kyc_verifications')) {
            return;
        }

        /** @var User $internalReviewer */
        $internalReviewer = User::query()->withoutGlobalScopes()
            ->where('email', 'e2e.internal.super_admin@example.test')
            ->firstOrFail();

        foreach ([
            'a' => KycVerification::STATUS_PENDING,
            'b' => KycVerification::STATUS_PENDING,
            'c' => KycVerification::STATUS_REJECTED,
            'd' => KycVerification::STATUS_APPROVED,
        ] as $key => $status) {
            if (Schema::hasColumn('accounts', 'kyc_status')) {
                $accounts[$key]->forceFill([
                    'kyc_status' => AccountKycStatusMapper::fromVerificationStatus($status),
                ])->save();
            }
        }

        if (Schema::hasTable('organization_profiles')) {
            OrganizationProfile::query()->updateOrCreate(
                ['account_id' => (string) $accounts['c']->id],
                [
                    'legal_name' => 'E2E Account C Logistics LLC',
                    'trade_name' => 'E2E Logistics',
                    'registration_number' => 'CR-300300300',
                    'industry' => 'logistics',
                    'company_size' => 'medium',
                    'country' => 'SA',
                    'city' => 'Riyadh',
                    'email' => 'ops@e2e-account-c.example.test',
                ]
            );
        }

        $individualVerification = KycVerification::query()->updateOrCreate(
            ['account_id' => (string) $accounts['a']->id],
            [
                'status' => KycVerification::STATUS_PENDING,
                'verification_type' => 'individual',
                'verification_level' => KycVerification::LEVEL_BASIC,
                'required_documents' => ['national_id' => 'الهوية الوطنية', 'address_proof' => 'إثبات العنوان'],
                'submitted_documents' => ['national_id' => 'kyc/e2e-account-a-id.pdf'],
                'review_notes' => 'تمت مراجعة أولية للهوية وما زال إثبات العنوان مطلوبًا قبل الاعتماد الكامل.',
                'submitted_at' => now()->subDays(2),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
                'review_count' => 0,
                'expires_at' => null,
            ]
        );

        $secondPendingVerification = KycVerification::query()->updateOrCreate(
            ['account_id' => (string) $accounts['b']->id],
            [
                'status' => KycVerification::STATUS_PENDING,
                'verification_type' => 'individual',
                'verification_level' => KycVerification::LEVEL_BASIC,
                'required_documents' => ['passport' => 'جواز السفر'],
                'submitted_documents' => ['passport' => 'kyc/e2e-account-b-passport.pdf'],
                'review_notes' => 'تم استلام نسخة أولية من الجواز وتحتاج إلى قرار نهائي.',
                'submitted_at' => now()->subDay(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
                'review_count' => 0,
                'expires_at' => null,
            ]
        );

        $organizationVerification = KycVerification::query()->updateOrCreate(
            ['account_id' => (string) $accounts['c']->id],
            [
                'status' => KycVerification::STATUS_REJECTED,
                'verification_type' => 'organization',
                'verification_level' => KycVerification::LEVEL_ENHANCED,
                'required_documents' => [
                    'commercial_registration' => 'السجل التجاري',
                    'tax_certificate' => 'شهادة الضريبة',
                ],
                'submitted_documents' => [
                    'commercial_registration' => 'kyc/e2e-account-c-cr.pdf',
                    'tax_certificate' => 'kyc/e2e-account-c-tax.pdf',
                ],
                'review_notes' => 'يلزم إعادة رفع السجل التجاري بنسخة أوضح ومطابقة للاسم القانوني.',
                'submitted_at' => now()->subDays(5),
                'reviewed_at' => now()->subDays(3),
                'reviewed_by' => (string) $internalReviewer->id,
                'rejection_reason' => 'عدم تطابق السجل التجاري مع الاسم القانوني.',
                'review_count' => 1,
                'expires_at' => null,
            ]
        );

        $approvedVerification = KycVerification::query()->updateOrCreate(
            ['account_id' => (string) $accounts['d']->id],
            [
                'status' => KycVerification::STATUS_APPROVED,
                'verification_type' => 'organization',
                'verification_level' => KycVerification::LEVEL_ENHANCED,
                'required_documents' => [
                    'commercial_registration' => 'السجل التجاري',
                    'tax_certificate' => 'شهادة الضريبة',
                ],
                'submitted_documents' => [
                    'commercial_registration' => 'kyc/e2e-account-d-cr.pdf',
                    'tax_certificate' => 'kyc/e2e-account-d-tax.pdf',
                ],
                'review_notes' => 'تمت مراجعة المستندات واعتماد الحساب بنجاح.',
                'submitted_at' => now()->subDays(8),
                'reviewed_at' => now()->subDays(6),
                'reviewed_by' => (string) $internalReviewer->id,
                'rejection_reason' => null,
                'review_count' => 1,
                'expires_at' => now()->addYear(),
            ]
        );

        if (Schema::hasTable('kyc_documents')) {
            KycDocument::query()->updateOrCreate(
                ['account_id' => (string) $accounts['a']->id, 'document_type' => 'national_id'],
                [
                    'kyc_verification_id' => (string) $individualVerification->id,
                    'original_filename' => 'e2e-account-a-id.pdf',
                    'stored_path' => 'kyc/e2e-account-a-id.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 182400,
                    'file_hash' => hash('sha256', 'e2e-account-a-id.pdf'),
                    'uploaded_by' => (string) $externalUsers['a']['primary']->id,
                ]
            );

            KycDocument::query()->updateOrCreate(
                ['account_id' => (string) $accounts['b']->id, 'document_type' => 'passport'],
                [
                    'kyc_verification_id' => (string) $secondPendingVerification->id,
                    'original_filename' => 'e2e-account-b-passport.pdf',
                    'stored_path' => 'kyc/e2e-account-b-passport.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 164220,
                    'file_hash' => hash('sha256', 'e2e-account-b-passport.pdf'),
                    'uploaded_by' => (string) $externalUsers['b']['primary']->id,
                ]
            );

            KycDocument::query()->updateOrCreate(
                ['account_id' => (string) $accounts['c']->id, 'document_type' => 'commercial_registration'],
                [
                    'kyc_verification_id' => (string) $organizationVerification->id,
                    'original_filename' => 'e2e-account-c-cr.pdf',
                    'stored_path' => 'kyc/e2e-account-c-cr.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 245760,
                    'file_hash' => hash('sha256', 'e2e-account-c-cr.pdf'),
                    'uploaded_by' => (string) $externalUsers['c']['organization_owner']->id,
                ]
            );

            KycDocument::query()->updateOrCreate(
                ['account_id' => (string) $accounts['c']->id, 'document_type' => 'tax_certificate'],
                [
                    'kyc_verification_id' => (string) $organizationVerification->id,
                    'original_filename' => 'e2e-account-c-tax.pdf',
                    'stored_path' => 'kyc/e2e-account-c-tax.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 198640,
                    'file_hash' => hash('sha256', 'e2e-account-c-tax.pdf'),
                    'uploaded_by' => (string) $externalUsers['c']['organization_owner']->id,
                ]
            );

            KycDocument::query()->updateOrCreate(
                ['account_id' => (string) $accounts['d']->id, 'document_type' => 'commercial_registration'],
                [
                    'kyc_verification_id' => (string) $approvedVerification->id,
                    'original_filename' => 'e2e-account-d-cr.pdf',
                    'stored_path' => 'kyc/e2e-account-d-cr.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size' => 221184,
                    'file_hash' => hash('sha256', 'e2e-account-d-cr.pdf'),
                    'uploaded_by' => (string) $externalUsers['d']['organization_owner']->id,
                ]
            );

            Shipment::factory()->create([
                'account_id' => (string) $accounts['c']->id,
                'user_id' => (string) $externalUsers['c']['organization_owner']->id,
                'created_by' => (string) $externalUsers['c']['organization_owner']->id,
                'status' => Shipment::STATUS_KYC_BLOCKED,
                'reference_number' => 'SHP-KYC-C-001',
            ]);

            Shipment::factory()->create([
                'account_id' => (string) $accounts['c']->id,
                'user_id' => (string) $externalUsers['c']['organization_owner']->id,
                'created_by' => (string) $externalUsers['c']['organization_owner']->id,
                'status' => Shipment::STATUS_KYC_BLOCKED,
                'reference_number' => 'SHP-KYC-C-002',
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ]);
        }

        if (Schema::hasTable('verification_restrictions')) {
            VerificationRestriction::query()->updateOrCreate(
                ['restriction_key' => 'kyc_pending_international_shipping'],
                [
                    'name' => 'تعليق الشحن الدولي',
                    'description' => 'لا يتاح الشحن الدولي حتى اكتمال المراجعة.',
                    'applies_to_statuses' => [KycVerification::STATUS_PENDING],
                    'restriction_type' => VerificationRestriction::TYPE_BLOCK_FEATURE,
                    'feature_key' => 'international_shipping',
                    'is_active' => true,
                ]
            );
        }

        if (Schema::hasTable('audit_logs')) {
            /** @var AuditService $audit */
            $audit = app(AuditService::class);

            $audit->info(
                (string) $accounts['a']->id,
                (string) $externalUsers['a']['primary']->id,
                'kyc.document_uploaded',
                AuditLog::CATEGORY_KYC,
                'KycVerification',
                (string) $individualVerification->id,
                null,
                null,
                ['document_type' => 'national_id']
            );

            $audit->info(
                (string) $accounts['b']->id,
                (string) $externalUsers['b']['primary']->id,
                'kyc.document_uploaded',
                AuditLog::CATEGORY_KYC,
                'KycVerification',
                (string) $secondPendingVerification->id,
                null,
                null,
                ['document_type' => 'passport']
            );

            $audit->warning(
                (string) $accounts['c']->id,
                (string) $internalReviewer->id,
                'kyc.rejected',
                AuditLog::CATEGORY_KYC,
                'KycVerification',
                (string) $organizationVerification->id,
                ['status' => 'pending'],
                ['status' => 'rejected'],
                ['reason' => 'عدم تطابق السجل التجاري مع الاسم القانوني.']
            );

            $audit->info(
                (string) $accounts['d']->id,
                (string) $internalReviewer->id,
                'kyc.approved',
                AuditLog::CATEGORY_KYC,
                'KycVerification',
                (string) $approvedVerification->id,
                ['status' => 'pending'],
                ['status' => 'approved'],
                ['review_notes' => 'تمت مراجعة المستندات واعتماد الحساب بنجاح.']
            );
        }
    }

    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     */
    private function seedDeterministicPendingInvitations(array $accounts, array $externalUsers): void
    {
        $account = $accounts['c'];
        $inviter = $externalUsers['c']['organization_owner'];

        $payload = [
            'name' => 'E2E Pending Invite',
            'token' => hash('sha256', 'e2e-account-c-pending-invite'),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(3),
        ];

        if (Schema::hasColumn('invitations', 'role_id')) {
            $payload['role_id'] = DB::table('roles')
                ->where('account_id', (string) $account->id)
                ->where('name', 'staff')
                ->value('id');
        } elseif (Schema::hasColumn('invitations', 'role_name')) {
            $payload['role_name'] = 'staff';
        }

        if (Schema::hasColumn('invitations', 'invited_by')) {
            $payload['invited_by'] = (string) $inviter->id;
        }

        if (Schema::hasColumn('invitations', 'last_sent_at')) {
            $payload['last_sent_at'] = now();
        }

        if (Schema::hasColumn('invitations', 'send_count')) {
            $payload['send_count'] = 1;
        }

        Invitation::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'account_id' => (string) $account->id,
                'email' => 'e2e.c.pending.invite@example.test',
            ],
            $payload
        );
    }

    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     */
    private function seedDeterministicShipmentReadFixtures(array $accounts, array $externalUsers): void
    {
        if (!Schema::hasTable('shipments')) {
            return;
        }

        $shipmentA = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I5A-A-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['a']->id,
                'user_id' => (string) $externalUsers['a']['primary']->id,
                'created_by' => (string) $externalUsers['a']['primary']->id,
                'reference_number' => 'SHP-I5A-A-001',
                'source' => Shipment::SOURCE_DIRECT,
                'status' => Shipment::STATUS_REQUIRES_ACTION,
                'carrier_code' => 'fedex',
                'carrier_name' => 'FedEx',
                'service_code' => 'intl_priority',
                'service_name' => 'FedEx International Priority',
                'tracking_number' => 'I5A-FDX-A-001',
                'carrier_shipment_id' => 'FDX-I5A-A-001',
                'tracking_status' => CanonicalShipmentStatus::EXCEPTION,
                'tracking_updated_at' => now()->subHours(3),
                'sender_name' => 'E2E A Sender',
                'sender_phone' => '+966500100101',
                'sender_address_1' => 'Riyadh Warehouse 10',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I5A A Recipient',
                'recipient_phone' => '+971500100101',
                'recipient_address_1' => 'Dubai Marina 21',
                'recipient_city' => 'Dubai',
                'recipient_country' => 'AE',
                'is_international' => true,
                'is_cod' => false,
                'is_insured' => false,
                'has_dangerous_goods' => false,
                'currency' => 'USD',
                'weight' => 1.8,
                'total_weight' => 1.8,
                'parcels_count' => 1,
                'pieces' => 1,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(2),
            ])
        );

        $shipmentC = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I5A-C-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['c']->id,
                'user_id' => (string) $externalUsers['c']['organization_owner']->id,
                'created_by' => (string) $externalUsers['c']['organization_owner']->id,
                'reference_number' => 'SHP-I5A-C-001',
                'source' => Shipment::SOURCE_ORDER,
                'status' => Shipment::STATUS_KYC_BLOCKED,
                'sender_name' => 'E2E C Sender',
                'sender_phone' => '+966500100201',
                'sender_address_1' => 'Riyadh Hub 8',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I5A C Recipient',
                'recipient_phone' => '+966500100202',
                'recipient_address_1' => 'Jeddah District 14',
                'recipient_city' => 'Jeddah',
                'recipient_country' => 'SA',
                'is_international' => false,
                'is_cod' => false,
                'is_insured' => false,
                'has_dangerous_goods' => false,
                'currency' => 'SAR',
                'weight' => 2.2,
                'total_weight' => 2.2,
                'parcels_count' => 1,
                'pieces' => 1,
                'created_at' => now()->subHours(5),
                'updated_at' => now()->subHours(1),
            ])
        );

        $publicTrackingToken = 'i5a-public-token-d-001';

        $shipmentD = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I5A-D-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['d']->id,
                'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                'created_by' => (string) $externalUsers['d']['organization_owner']->id,
                'reference_number' => 'SHP-I5A-D-001',
                'source' => Shipment::SOURCE_ORDER,
                'status' => Shipment::STATUS_IN_TRANSIT,
                'carrier_code' => 'dhl',
                'carrier_name' => 'DHL Express',
                'service_code' => 'express_worldwide',
                'service_name' => 'DHL Express Worldwide',
                'tracking_number' => 'I5A-DHL-D-001',
                'carrier_shipment_id' => 'DHL-I5A-D-001',
                'tracking_status' => CanonicalShipmentStatus::IN_TRANSIT,
                'tracking_updated_at' => now()->subHour(),
                'tracking_url' => 'https://tracking.example.test/i5a-dhl-d-001',
                'sender_name' => 'E2E D Sender',
                'sender_phone' => '+966500100301',
                'sender_address_1' => 'Dammam Branch 3',
                'sender_city' => 'Dammam',
                'sender_country' => 'SA',
                'recipient_name' => 'I5A D Recipient',
                'recipient_phone' => '+973500100301',
                'recipient_address_1' => 'Manama Block 12',
                'recipient_city' => 'Manama',
                'recipient_country' => 'BH',
                'is_international' => true,
                'is_cod' => true,
                'is_insured' => true,
                'has_dangerous_goods' => false,
                'currency' => 'USD',
                'cod_amount' => 125.00,
                'weight' => 4.1,
                'total_weight' => 4.1,
                'parcels_count' => 2,
                'pieces' => 2,
                'public_tracking_token' => $publicTrackingToken,
                'public_tracking_token_hash' => hash('sha256', $publicTrackingToken),
                'public_tracking_enabled_at' => now()->subDay(),
                'public_tracking_expires_at' => now()->addDays(5),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subMinutes(45),
                'picked_up_at' => now()->subHours(7),
            ])
        );

        if (Schema::hasTable('carrier_shipments')) {
            CarrierShipment::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id],
                $this->filterExistingColumns('carrier_shipments', [
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'carrier_code' => 'dhl',
                    'carrier_name' => 'DHL Express',
                    'carrier_shipment_id' => 'DHL-I5A-D-001',
                    'tracking_number' => 'I5A-DHL-D-001',
                    'awb_number' => 'AWB-I5A-D-001',
                    'service_code' => 'express_worldwide',
                    'service_name' => 'DHL Express Worldwide',
                    'status' => CarrierShipment::STATUS_LABEL_READY,
                    'idempotency_key' => 'i5a-dhl-d-001',
                    'attempt_count' => 1,
                    'last_attempt_at' => now()->subDays(2),
                    'label_format' => 'pdf',
                    'label_size' => '4x6',
                    'is_cancellable' => false,
                    'correlation_id' => 'i5a-correlation-d-001',
                ])
            );
        }

        if (Schema::hasTable('parcels')) {
            Parcel::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id, 'sequence' => 1],
                $this->filterExistingColumns('parcels', [
                    'shipment_id' => (string) $shipmentD->id,
                    'sequence' => 1,
                    'weight' => 2.0,
                    'length' => 30,
                    'width' => 20,
                    'height' => 18,
                    'volumetric_weight' => 2.16,
                    'packaging_type' => Parcel::PACKAGING_BOX,
                    'description' => 'Apparel',
                    'reference' => 'PCL-I5A-D-01',
                    'carrier_tracking' => 'I5A-DHL-D-001-1',
                ])
            );

            Parcel::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id, 'sequence' => 2],
                $this->filterExistingColumns('parcels', [
                    'shipment_id' => (string) $shipmentD->id,
                    'sequence' => 2,
                    'weight' => 2.1,
                    'length' => 32,
                    'width' => 21,
                    'height' => 16,
                    'volumetric_weight' => 2.15,
                    'packaging_type' => Parcel::PACKAGING_BOX,
                    'description' => 'Footwear',
                    'reference' => 'PCL-I5A-D-02',
                    'carrier_tracking' => 'I5A-DHL-D-001-2',
                ])
            );
        }

        $shipmentDPurchaseEvent = null;
        $shipmentDLabelEvent = null;
        $shipmentDTransitEvent = null;

        if (Schema::hasTable('shipment_events')) {
            $shipmentDPurchaseEvent = ShipmentEvent::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id, 'idempotency_key' => 'i5a:d:purchase'],
                $this->filterExistingColumns('shipment_events', [
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'event_type' => 'shipment.purchased',
                    'status' => CanonicalShipmentStatus::PURCHASED,
                    'normalized_status' => CanonicalShipmentStatus::PURCHASED,
                    'description' => 'Carrier purchase completed for shipment D.',
                    'location' => 'DHL',
                    'event_at' => now()->subHours(12),
                    'source' => ShipmentEvent::SOURCE_SYSTEM,
                    'idempotency_key' => 'i5a:d:purchase',
                    'payload' => ['reference_number' => 'SHP-I5A-D-001'],
                ])
            );

            $shipmentDLabelEvent = ShipmentEvent::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id, 'idempotency_key' => 'i5a:d:label'],
                $this->filterExistingColumns('shipment_events', [
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'event_type' => 'carrier.documents_available',
                    'status' => CanonicalShipmentStatus::LABEL_READY,
                    'normalized_status' => CanonicalShipmentStatus::LABEL_READY,
                    'description' => 'Carrier artifacts became available.',
                    'location' => 'DHL',
                    'event_at' => now()->subHours(9),
                    'source' => ShipmentEvent::SOURCE_CARRIER,
                    'idempotency_key' => 'i5a:d:label',
                    'payload' => ['reference_number' => 'SHP-I5A-D-001'],
                ])
            );

            $shipmentDTransitEvent = ShipmentEvent::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentD->id, 'idempotency_key' => 'i5a:d:transit'],
                $this->filterExistingColumns('shipment_events', [
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'event_type' => 'tracking.status_updated',
                    'status' => CanonicalShipmentStatus::IN_TRANSIT,
                    'normalized_status' => CanonicalShipmentStatus::IN_TRANSIT,
                    'description' => 'Shipment is in transit to the destination hub.',
                    'location' => 'Bahrain',
                    'event_at' => now()->subHours(2),
                    'source' => ShipmentEvent::SOURCE_CARRIER,
                    'idempotency_key' => 'i5a:d:transit',
                    'payload' => ['reference_number' => 'SHP-I5A-D-001'],
                ])
            );

            ShipmentEvent::query()->updateOrCreate(
                ['shipment_id' => (string) $shipmentC->id, 'idempotency_key' => 'i5a:c:blocked'],
                $this->filterExistingColumns('shipment_events', [
                    'shipment_id' => (string) $shipmentC->id,
                    'account_id' => (string) $accounts['c']->id,
                    'event_type' => 'shipment.updated',
                    'status' => Shipment::STATUS_KYC_BLOCKED,
                    'normalized_status' => CanonicalShipmentStatus::EXCEPTION,
                    'description' => 'Shipment is blocked pending KYC remediation.',
                    'location' => 'Internal KYC queue',
                    'event_at' => now()->subMinutes(90),
                    'source' => ShipmentEvent::SOURCE_SYSTEM,
                    'idempotency_key' => 'i5a:c:blocked',
                    'payload' => ['reference_number' => 'SHP-I5A-C-001'],
                ])
            );
        }

        if (Schema::hasTable('notifications')) {
            Notification::query()->updateOrCreate(
                [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_type' => Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE,
                    'channel' => Notification::CHANNEL_EMAIL,
                ],
                $this->filterExistingColumns('notifications', [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'shipment_event_id' => $shipmentDLabelEvent?->id,
                    'event_type' => Notification::EVENT_SHIPMENT_DOCUMENTS_AVAILABLE,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_data' => [
                        'title' => 'Shipment documents ready',
                        'tracking_number' => 'I5A-DHL-D-001',
                    ],
                    'channel' => Notification::CHANNEL_EMAIL,
                    'destination' => (string) $externalUsers['d']['organization_owner']->email,
                    'language' => 'en',
                    'subject' => 'Shipment documents ready',
                    'body' => 'Carrier label and invoice are ready for review.',
                    'status' => Notification::STATUS_SENT,
                    'sent_at' => now()->subHours(9),
                    'delivered_at' => now()->subHours(9),
                    'provider' => 'seeded-fixture',
                    'created_at' => now()->subHours(9),
                    'updated_at' => now()->subHours(9),
                ])
            );

            Notification::query()->updateOrCreate(
                [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_type' => Notification::EVENT_SHIPMENT_IN_TRANSIT,
                    'channel' => Notification::CHANNEL_IN_APP,
                ],
                $this->filterExistingColumns('notifications', [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'shipment_event_id' => $shipmentDTransitEvent?->id,
                    'event_type' => Notification::EVENT_SHIPMENT_IN_TRANSIT,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_data' => [
                        'title' => 'Shipment is moving through the network',
                        'tracking_number' => 'I5A-DHL-D-001',
                    ],
                    'channel' => Notification::CHANNEL_IN_APP,
                    'destination' => (string) $externalUsers['d']['organization_owner']->id,
                    'language' => 'en',
                    'subject' => 'Shipment is moving through the network',
                    'body' => 'The latest carrier scan marked this shipment as in transit.',
                    'status' => Notification::STATUS_DELIVERED,
                    'sent_at' => now()->subHours(2),
                    'delivered_at' => now()->subHours(2),
                    'read_at' => now()->subHour(),
                    'provider' => 'seeded-fixture',
                    'created_at' => now()->subHours(2),
                    'updated_at' => now()->subHour(),
                ])
            );

            Notification::query()->updateOrCreate(
                [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_admin']->id,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_type' => Notification::EVENT_SHIPMENT_PURCHASED,
                    'channel' => Notification::CHANNEL_IN_APP,
                ],
                $this->filterExistingColumns('notifications', [
                    'account_id' => (string) $accounts['d']->id,
                    'user_id' => (string) $externalUsers['d']['organization_admin']->id,
                    'shipment_event_id' => $shipmentDPurchaseEvent?->id,
                    'event_type' => Notification::EVENT_SHIPMENT_PURCHASED,
                    'entity_type' => 'shipment',
                    'entity_id' => (string) $shipmentD->id,
                    'event_data' => [
                        'title' => 'Shipment purchase completed',
                        'tracking_number' => 'I5A-DHL-D-001',
                    ],
                    'channel' => Notification::CHANNEL_IN_APP,
                    'destination' => (string) $externalUsers['d']['organization_admin']->id,
                    'language' => 'en',
                    'subject' => 'Shipment purchase completed',
                    'body' => 'Carrier purchase completed and label generation finished successfully.',
                    'status' => Notification::STATUS_DELIVERED,
                    'sent_at' => now()->subHours(12),
                    'delivered_at' => now()->subHours(12),
                    'provider' => 'seeded-fixture',
                    'created_at' => now()->subHours(12),
                    'updated_at' => now()->subHours(12),
                ])
            );
        }

        if (Schema::hasTable('carrier_documents') && Schema::hasTable('carrier_shipments')) {
            $carrierShipmentId = (string) CarrierShipment::query()
                ->where('shipment_id', (string) $shipmentD->id)
                ->value('id');

            if ($carrierShipmentId !== '') {
                $inlinePdf = base64_encode('%PDF-1.4 I5A label fixture');

                CarrierDocument::query()->updateOrCreate(
                    ['shipment_id' => (string) $shipmentD->id, 'original_filename' => 'i5a-d-label.pdf'],
                    $this->filterExistingColumns('carrier_documents', [
                        'carrier_shipment_id' => $carrierShipmentId,
                        'shipment_id' => (string) $shipmentD->id,
                        'carrier_code' => 'dhl',
                        'type' => CarrierDocument::TYPE_LABEL,
                        'format' => CarrierDocument::FORMAT_PDF,
                        'mime_type' => CarrierDocument::getMimeType(CarrierDocument::FORMAT_PDF),
                        'source' => CarrierDocument::SOURCE_CARRIER,
                        'retrieval_mode' => CarrierDocument::RETRIEVAL_INLINE,
                        'original_filename' => 'i5a-d-label.pdf',
                        'file_size' => strlen(base64_decode($inlinePdf)),
                        'checksum' => hash('sha256', base64_decode($inlinePdf)),
                        'content_base64' => $inlinePdf,
                        'is_available' => true,
                        'carrier_metadata' => [
                            'carrier_document_type' => 'label',
                            'tracking_number' => 'I5A-DHL-D-001',
                        ],
                    ])
                );

                CarrierDocument::query()->updateOrCreate(
                    ['shipment_id' => (string) $shipmentD->id, 'original_filename' => 'i5a-d-commercial-invoice.pdf'],
                    $this->filterExistingColumns('carrier_documents', [
                        'carrier_shipment_id' => $carrierShipmentId,
                        'shipment_id' => (string) $shipmentD->id,
                        'carrier_code' => 'dhl',
                        'type' => CarrierDocument::TYPE_COMMERCIAL_INVOICE,
                        'format' => CarrierDocument::FORMAT_PDF,
                        'mime_type' => CarrierDocument::getMimeType(CarrierDocument::FORMAT_PDF),
                        'source' => CarrierDocument::SOURCE_CARRIER,
                        'retrieval_mode' => CarrierDocument::RETRIEVAL_INLINE,
                        'original_filename' => 'i5a-d-commercial-invoice.pdf',
                        'file_size' => strlen(base64_decode($inlinePdf)),
                        'checksum' => hash('sha256', 'i5a-d-commercial-invoice.pdf'),
                        'content_base64' => $inlinePdf,
                        'is_available' => true,
                        'carrier_metadata' => [
                            'carrier_document_type' => 'commercial_invoice',
                            'tracking_number' => 'I5A-DHL-D-001',
                        ],
                    ])
                );
            }
        }
    }

    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     */
    private function seedDeterministicComplianceFixtures(array $accounts, array $externalUsers): void
    {
        if (!Schema::hasTable('shipments') || !Schema::hasTable('content_declarations')) {
            return;
        }

        if (Schema::hasTable('organization_profiles')) {
            OrganizationProfile::query()->updateOrCreate(
                ['account_id' => (string) $accounts['d']->id],
                [
                    'legal_name' => 'E2E Account D Trading Co.',
                    'trade_name' => 'E2E D Trade',
                    'registration_number' => 'CR-400400400',
                    'industry' => 'retail',
                    'company_size' => 'medium',
                    'country' => 'SA',
                    'city' => 'Dammam',
                    'email' => 'ops@e2e-account-d.example.test',
                ]
            );
        }

        $waiverVersion = null;
        if (Schema::hasTable('waiver_versions')) {
            $waiverText = 'I7A hidden waiver text snapshot A';
            $waiverVersion = WaiverVersion::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'version' => 'I7A-EN-1',
                    'locale' => 'en',
                ],
                $this->filterExistingColumns('waiver_versions', [
                    'version' => 'I7A-EN-1',
                    'locale' => 'en',
                    'waiver_text' => $waiverText,
                    'waiver_hash' => hash('sha256', $waiverText),
                    'is_active' => true,
                    'created_by' => (string) $externalUsers['a']['primary']->id,
                ])
            );
        }

        $shipmentA = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I7A-A-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['a']->id,
                'user_id' => (string) $externalUsers['a']['primary']->id,
                'created_by' => (string) $externalUsers['a']['primary']->id,
                'reference_number' => 'SHP-I7A-A-001',
                'source' => Shipment::SOURCE_DIRECT,
                'status' => Shipment::STATUS_DECLARATION_COMPLETE,
                'tracking_number' => 'I7A-A-001',
                'sender_name' => 'E2E A Compliance Sender',
                'sender_phone' => '+966500110101',
                'sender_address_1' => 'Riyadh Compliance Dock 1',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I7A A Recipient',
                'recipient_phone' => '+971500110101',
                'recipient_address_1' => 'Dubai Compliance Park 4',
                'recipient_city' => 'Dubai',
                'recipient_country' => 'AE',
                'is_international' => true,
                'is_cod' => false,
                'is_insured' => false,
                'has_dangerous_goods' => false,
                'currency' => 'USD',
                'weight' => 1.1,
                'total_weight' => 1.1,
                'parcels_count' => 1,
                'pieces' => 1,
                'status_reason' => 'Declaration completed after standard non-DG acknowledgement.',
                'created_at' => now()->subHours(20),
                'updated_at' => now()->subHours(8),
            ])
        );

        $shipmentC = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I7A-C-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['c']->id,
                'user_id' => (string) $externalUsers['c']['organization_owner']->id,
                'created_by' => (string) $externalUsers['c']['organization_owner']->id,
                'reference_number' => 'SHP-I7A-C-001',
                'source' => Shipment::SOURCE_ORDER,
                'status' => Shipment::STATUS_REQUIRES_ACTION,
                'tracking_number' => 'I7A-C-001',
                'sender_name' => 'E2E C Compliance Sender',
                'sender_phone' => '+966500110201',
                'sender_address_1' => 'Riyadh DG Bay 3',
                'sender_city' => 'Riyadh',
                'sender_country' => 'SA',
                'recipient_name' => 'I7A C Recipient',
                'recipient_phone' => '+966500110202',
                'recipient_address_1' => 'Jeddah Port District 2',
                'recipient_city' => 'Jeddah',
                'recipient_country' => 'SA',
                'is_international' => false,
                'is_cod' => false,
                'is_insured' => false,
                'has_dangerous_goods' => true,
                'currency' => 'SAR',
                'weight' => 3.4,
                'total_weight' => 3.4,
                'parcels_count' => 1,
                'pieces' => 1,
                'status_reason' => 'Dangerous-goods declaration is blocked pending internal review.',
                'created_at' => now()->subHours(14),
                'updated_at' => now()->subHours(5),
            ])
        );

        $shipmentD = Shipment::query()->withoutGlobalScopes()->updateOrCreate(
            ['reference_number' => 'SHP-I7A-D-001'],
            $this->filterExistingColumns('shipments', [
                'account_id' => (string) $accounts['d']->id,
                'user_id' => (string) $externalUsers['d']['organization_owner']->id,
                'created_by' => (string) $externalUsers['d']['organization_owner']->id,
                'reference_number' => 'SHP-I7A-D-001',
                'source' => Shipment::SOURCE_ORDER,
                'status' => Shipment::STATUS_DECLARATION_REQUIRED,
                'tracking_number' => 'I7A-D-001',
                'sender_name' => 'E2E D Compliance Sender',
                'sender_phone' => '+966500110301',
                'sender_address_1' => 'Dammam Compliance Wing 5',
                'sender_city' => 'Dammam',
                'sender_country' => 'SA',
                'recipient_name' => 'I7A D Recipient',
                'recipient_phone' => '+973500110301',
                'recipient_address_1' => 'Manama Free Zone 9',
                'recipient_city' => 'Manama',
                'recipient_country' => 'BH',
                'is_international' => true,
                'is_cod' => false,
                'is_insured' => true,
                'has_dangerous_goods' => false,
                'currency' => 'USD',
                'weight' => 2.7,
                'total_weight' => 2.7,
                'parcels_count' => 1,
                'pieces' => 1,
                'status_reason' => 'Waiting for legal acknowledgement before declaration completion.',
                'created_at' => now()->subHours(7),
                'updated_at' => now()->subHours(2),
            ])
        );

        $declarationA = ContentDeclaration::query()->withoutGlobalScopes()->updateOrCreate(
            ['shipment_id' => (string) $shipmentA->id],
            $this->filterExistingColumns('content_declarations', [
                'account_id' => (string) $accounts['a']->id,
                'shipment_id' => (string) $shipmentA->id,
                'contains_dangerous_goods' => false,
                'dg_flag_declared' => true,
                'status' => ContentDeclaration::STATUS_COMPLETED,
                'hold_reason' => null,
                'waiver_accepted' => true,
                'waiver_version_id' => $waiverVersion?->id,
                'waiver_hash_snapshot' => 'i7a-hidden-waiver-hash-a',
                'waiver_text_snapshot' => 'I7A hidden waiver text snapshot A',
                'waiver_accepted_at' => now()->subHours(10),
                'declared_by' => (string) $externalUsers['a']['primary']->id,
                'ip_address' => '203.0.113.41',
                'user_agent' => 'I7A hidden user agent A',
                'locale' => 'en',
                'declared_at' => now()->subHours(11),
            ])
        );

        $declarationC = ContentDeclaration::query()->withoutGlobalScopes()->updateOrCreate(
            ['shipment_id' => (string) $shipmentC->id],
            $this->filterExistingColumns('content_declarations', [
                'account_id' => (string) $accounts['c']->id,
                'shipment_id' => (string) $shipmentC->id,
                'contains_dangerous_goods' => true,
                'dg_flag_declared' => true,
                'status' => ContentDeclaration::STATUS_HOLD_DG,
                'hold_reason' => 'Manual DG review required for the declared flammable liquid shipment.',
                'waiver_accepted' => false,
                'waiver_version_id' => null,
                'waiver_hash_snapshot' => null,
                'waiver_text_snapshot' => null,
                'waiver_accepted_at' => null,
                'declared_by' => (string) $externalUsers['c']['organization_owner']->id,
                'ip_address' => '203.0.113.42',
                'user_agent' => 'I7A hidden user agent C',
                'locale' => 'en',
                'declared_at' => now()->subHours(12),
            ])
        );

        $declarationD = ContentDeclaration::query()->withoutGlobalScopes()->updateOrCreate(
            ['shipment_id' => (string) $shipmentD->id],
            $this->filterExistingColumns('content_declarations', [
                'account_id' => (string) $accounts['d']->id,
                'shipment_id' => (string) $shipmentD->id,
                'contains_dangerous_goods' => false,
                'dg_flag_declared' => true,
                'status' => ContentDeclaration::STATUS_PENDING,
                'hold_reason' => null,
                'waiver_accepted' => false,
                'waiver_version_id' => $waiverVersion?->id,
                'waiver_hash_snapshot' => null,
                'waiver_text_snapshot' => null,
                'waiver_accepted_at' => null,
                'declared_by' => (string) $externalUsers['d']['organization_owner']->id,
                'ip_address' => '203.0.113.43',
                'user_agent' => 'I7A hidden user agent D',
                'locale' => 'en',
                'declared_at' => now()->subHours(4),
            ])
        );

        if (Schema::hasTable('dg_metadata')) {
            DgMetadata::query()->withoutGlobalScopes()
                ->whereIn('declaration_id', [(string) $declarationA->id, (string) $declarationD->id])
                ->delete();

            DgMetadata::query()->withoutGlobalScopes()->updateOrCreate(
                ['declaration_id' => (string) $declarationC->id],
                $this->filterExistingColumns('dg_metadata', [
                    'declaration_id' => (string) $declarationC->id,
                    'un_number' => 'UN1993',
                    'dg_class' => '3',
                    'packing_group' => 'II',
                    'proper_shipping_name' => 'Flammable liquid, n.o.s.',
                    'quantity' => 1.500,
                    'quantity_unit' => 'L',
                    'description' => 'Seeded dangerous-goods metadata fixture for internal compliance visibility.',
                    'additional_info' => ['internal_secret' => 'I7A hidden dg additional info C'],
                ])
            );
        }

        if (Schema::hasTable('dg_audit_logs')) {
            DB::table('dg_audit_logs')
                ->whereIn('declaration_id', [
                    (string) $declarationA->id,
                    (string) $declarationC->id,
                    (string) $declarationD->id,
                ])
                ->delete();

            $auditRows = [
                [
                    'declaration_id' => (string) $declarationA->id,
                    'shipment_id' => (string) $shipmentA->id,
                    'account_id' => (string) $accounts['a']->id,
                    'action' => DgAuditLog::ACTION_CREATED,
                    'actor_id' => (string) $externalUsers['a']['primary']->id,
                    'actor_role' => 'individual_account_holder',
                    'ip_address' => '203.0.113.41',
                    'notes' => 'Customer opened the compliance declaration flow.',
                    'payload' => ['secret' => 'I7A hidden audit payload A'],
                    'created_at' => now()->subHours(11),
                ],
                [
                    'declaration_id' => (string) $declarationA->id,
                    'shipment_id' => (string) $shipmentA->id,
                    'account_id' => (string) $accounts['a']->id,
                    'action' => DgAuditLog::ACTION_WAIVER_ACCEPTED,
                    'actor_id' => (string) $externalUsers['a']['primary']->id,
                    'actor_role' => 'individual_account_holder',
                    'ip_address' => '203.0.113.41',
                    'notes' => 'Legal acknowledgement captured for the non-DG shipment.',
                    'payload' => ['secret' => 'I7A hidden audit payload A-2'],
                    'created_at' => now()->subHours(10),
                ],
                [
                    'declaration_id' => (string) $declarationA->id,
                    'shipment_id' => (string) $shipmentA->id,
                    'account_id' => (string) $accounts['a']->id,
                    'action' => DgAuditLog::ACTION_COMPLETED,
                    'actor_id' => (string) $externalUsers['a']['primary']->id,
                    'actor_role' => 'individual_account_holder',
                    'ip_address' => '203.0.113.41',
                    'notes' => 'Declaration completed and cleared for the next shipment phase.',
                    'payload' => ['secret' => 'I7A hidden audit payload A-3'],
                    'created_at' => now()->subHours(9),
                ],
                [
                    'declaration_id' => (string) $declarationC->id,
                    'shipment_id' => (string) $shipmentC->id,
                    'account_id' => (string) $accounts['c']->id,
                    'action' => DgAuditLog::ACTION_CREATED,
                    'actor_id' => (string) $externalUsers['c']['organization_owner']->id,
                    'actor_role' => 'organization_owner',
                    'ip_address' => '203.0.113.42',
                    'notes' => 'Organization owner started the DG declaration flow.',
                    'payload' => ['secret' => 'I7A hidden audit payload C'],
                    'created_at' => now()->subHours(12),
                ],
                [
                    'declaration_id' => (string) $declarationC->id,
                    'shipment_id' => (string) $shipmentC->id,
                    'account_id' => (string) $accounts['c']->id,
                    'action' => DgAuditLog::ACTION_DG_METADATA_SAVED,
                    'actor_id' => (string) $externalUsers['c']['organization_owner']->id,
                    'actor_role' => 'organization_owner',
                    'ip_address' => '203.0.113.42',
                    'notes' => 'Dangerous-goods metadata was captured for internal review.',
                    'payload' => ['secret' => 'I7A hidden audit payload C-2'],
                    'created_at' => now()->subHours(11),
                ],
                [
                    'declaration_id' => (string) $declarationC->id,
                    'shipment_id' => (string) $shipmentC->id,
                    'account_id' => (string) $accounts['c']->id,
                    'action' => DgAuditLog::ACTION_HOLD_APPLIED,
                    'actor_id' => (string) $externalUsers['c']['organization_owner']->id,
                    'actor_role' => 'organization_owner',
                    'ip_address' => '203.0.113.42',
                    'notes' => 'DG declaration moved into manual compliance review.',
                    'payload' => ['secret' => 'I7A hidden audit payload C-3'],
                    'created_at' => now()->subHours(10),
                ],
                [
                    'declaration_id' => (string) $declarationD->id,
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'action' => DgAuditLog::ACTION_CREATED,
                    'actor_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'actor_role' => 'organization_owner',
                    'ip_address' => '203.0.113.43',
                    'notes' => 'Organization owner opened the declaration flow.',
                    'payload' => ['secret' => 'I7A hidden audit payload D'],
                    'created_at' => now()->subHours(4),
                ],
                [
                    'declaration_id' => (string) $declarationD->id,
                    'shipment_id' => (string) $shipmentD->id,
                    'account_id' => (string) $accounts['d']->id,
                    'action' => DgAuditLog::ACTION_DG_FLAG_SET,
                    'actor_id' => (string) $externalUsers['d']['organization_owner']->id,
                    'actor_role' => 'organization_owner',
                    'ip_address' => '203.0.113.43',
                    'notes' => 'Shipment was marked as non-DG but still needs legal acknowledgement.',
                    'payload' => ['secret' => 'I7A hidden audit payload D-2'],
                    'created_at' => now()->subHours(3),
                ],
            ];

            foreach ($auditRows as $auditRow) {
                if (Schema::hasColumn('dg_audit_logs', 'id')) {
                    $auditRow['id'] = (string) Str::uuid();
                }

                if (Schema::hasColumn('dg_audit_logs', 'shipment_uuid')) {
                    $auditRow['shipment_uuid'] = $auditRow['shipment_id'];
                }

                foreach (['old_values', 'new_values', 'payload'] as $jsonColumn) {
                    if (array_key_exists($jsonColumn, $auditRow) && is_array($auditRow[$jsonColumn])) {
                        $auditRow[$jsonColumn] = json_encode($auditRow[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }

                DB::table('dg_audit_logs')->insert($this->filterExistingColumns('dg_audit_logs', $auditRow));
            }
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
    /**
     * @param array<string, Account> $accounts
     * @param array<string, array<string, User>> $externalUsers
     * @param array{super_admin: User, support: User} $internalUsers
     */
    private function seedDeterministicTicketFixtures(array $accounts, array $externalUsers, array $internalUsers): void
    {
        if (! Schema::hasTable('support_tickets')) {
            return;
        }

        $numberColumn = Schema::hasColumn('support_tickets', 'ticket_number') ? 'ticket_number' : 'reference_number';
        $bodyColumn = Schema::hasColumn('support_tickets', 'description') ? 'description' : 'body';
        $shipmentIdAcceptsCurrentShipmentIds = Schema::hasColumn('support_tickets', 'shipment_id')
            && Schema::getColumnType('support_tickets', 'shipment_id') !== 'bigint';
        $entityColumns = Schema::hasColumn('support_tickets', 'entity_type') && Schema::hasColumn('support_tickets', 'entity_id');
        $assignedToAcceptsCurrentUserIds = Schema::hasColumn('support_tickets', 'assigned_to')
            && Schema::getColumnType('support_tickets', 'assigned_to') !== 'bigint';

        $shipmentA = Shipment::query()->withoutGlobalScopes()->where('reference_number', 'SHP-I5A-A-001')->first();
        $shipmentC = Shipment::query()->withoutGlobalScopes()->where('reference_number', 'SHP-I5A-C-001')->first();
        $shipmentD = Shipment::query()->withoutGlobalScopes()->where('reference_number', 'SHP-I5A-D-001')->first();

        $definitions = [
            [
                'number' => 'TKT-I9A-C-001',
                'account' => $accounts['c'],
                'requester' => $externalUsers['c']['organization_owner'],
                'assignee' => $internalUsers['support'],
                'assigned_team' => 'support',
                'subject' => 'Delayed organization shipment follow-up',
                'body' => 'Customer reported a delay on the seeded organization shipment and wants an operational update.',
                'category' => 'shipping',
                'priority' => 'high',
                'status' => 'waiting_agent',
                'shipment' => $shipmentC,
                'created_at' => now()->subHours(9),
                'updated_at' => now()->subHours(2),
            ],
            [
                'number' => 'TKT-I9A-A-001',
                'account' => $accounts['a'],
                'requester' => $externalUsers['a']['primary'],
                'assignee' => $internalUsers['super_admin'],
                'assigned_team' => 'billing',
                'subject' => 'Wallet charge clarification requested',
                'body' => 'Customer asked for a safe explanation of a recent wallet charge linked to a shipment purchase.',
                'category' => 'billing',
                'priority' => 'medium',
                'status' => 'resolved',
                'shipment' => $shipmentA,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDay(),
                'resolved_at' => now()->subDay(),
            ],
            [
                'number' => 'TKT-I9A-D-001',
                'account' => $accounts['d'],
                'requester' => $externalUsers['d']['organization_admin'],
                'assignee' => null,
                'assigned_team' => 'technical',
                'subject' => 'Tracking event mismatch on recent shipment',
                'body' => 'Customer flagged a tracking mismatch on the seeded shipment and requested a compliance-safe review.',
                'category' => 'technical',
                'priority' => 'urgent',
                'status' => 'open',
                'shipment' => $shipmentD,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(1),
            ],
        ];

        foreach ($definitions as $definition) {
            $payload = [
                'account_id' => (string) $definition['account']->id,
                'user_id' => (string) $definition['requester']->id,
                $numberColumn => $definition['number'],
                'subject' => $definition['subject'],
                $bodyColumn => $definition['body'],
                'category' => $definition['category'],
                'priority' => $definition['priority'],
                'status' => $definition['status'],
                'assigned_team' => $definition['assigned_team'],
                'created_at' => $definition['created_at'],
                'updated_at' => $definition['updated_at'],
                'first_response_at' => $definition['assignee'] ? $definition['created_at']->copy()->addHour() : null,
                'resolved_at' => $definition['resolved_at'] ?? null,
                'closed_at' => null,
                'resolution_notes' => isset($definition['resolved_at']) ? 'Customer received the requested billing clarification.' : null,
            ];

            if ($assignedToAcceptsCurrentUserIds) {
                $payload['assigned_to'] = $definition['assignee'] ? (string) $definition['assignee']->id : null;
            }

            if ($shipmentIdAcceptsCurrentShipmentIds && $definition['shipment'] instanceof Shipment) {
                $payload['shipment_id'] = (string) $definition['shipment']->id;
            }

            if ($entityColumns && $definition['shipment'] instanceof Shipment) {
                $payload['entity_type'] = 'shipment';
                $payload['entity_id'] = (string) $definition['shipment']->id;
            }

            $ticket = SupportTicket::query()
                ->withoutGlobalScopes()
                ->updateOrCreate(
                    [
                        'account_id' => (string) $definition['account']->id,
                        $numberColumn => $definition['number'],
                    ],
                    $this->filterExistingColumns('support_tickets', $payload)
                );

            if (Schema::hasTable('ticket_replies')) {
                TicketReply::query()
                    ->withoutGlobalScopes()
                    ->where('support_ticket_id', (string) $ticket->id)
                    ->delete();

                $replies = match ($definition['number']) {
                    'TKT-I9A-C-001' => [
                        [
                            'user_id' => (string) $internalUsers['support']->id,
                            'body' => 'We are checking the latest carrier handoff and will update the customer once the scan settles.',
                            'is_agent' => true,
                            'created_at' => $definition['created_at']->copy()->addHours(2),
                            'updated_at' => $definition['created_at']->copy()->addHours(2),
                        ],
                        [
                            'user_id' => (string) $definition['requester']->id,
                            'body' => 'Customer confirmed the shipment is still pending delivery and needs an ETA.',
                            'is_agent' => false,
                            'created_at' => $definition['created_at']->copy()->addHours(6),
                            'updated_at' => $definition['created_at']->copy()->addHours(6),
                        ],
                    ],
                    'TKT-I9A-A-001' => [
                        [
                            'user_id' => (string) $internalUsers['super_admin']->id,
                            'body' => 'A billing explanation was shared and the wallet charge matches the linked shipment purchase.',
                            'is_agent' => true,
                            'created_at' => $definition['created_at']->copy()->addHours(5),
                            'updated_at' => $definition['created_at']->copy()->addHours(5),
                        ],
                    ],
                    default => [
                        [
                            'user_id' => (string) $definition['requester']->id,
                            'body' => 'Customer added a follow-up asking for the current shipment status and any restrictions.',
                            'is_agent' => false,
                            'created_at' => $definition['created_at']->copy()->addHours(3),
                            'updated_at' => $definition['created_at']->copy()->addHours(3),
                        ],
                    ],
                };

                foreach ($replies as $reply) {
                    TicketReply::query()->withoutGlobalScopes()->create([
                        'support_ticket_id' => (string) $ticket->id,
                        'user_id' => $reply['user_id'],
                        'body' => $reply['body'],
                        'is_agent' => $reply['is_agent'],
                        'created_at' => $reply['created_at'],
                        'updated_at' => $reply['updated_at'],
                    ]);
                }
            }

            if (Schema::hasTable('support_ticket_replies')) {
                SupportTicketReply::query()
                    ->withoutGlobalScopes()
                    ->where('ticket_id', (string) $ticket->id)
                    ->delete();

                if ($definition['number'] === 'TKT-I9A-C-001') {
                    SupportTicketReply::query()->withoutGlobalScopes()->create(
                        $this->filterExistingColumns('support_ticket_replies', [
                            'ticket_id' => (string) $ticket->id,
                            'user_id' => (string) $internalUsers['super_admin']->id,
                            'body' => 'Internal escalation note for leadership only.',
                            'is_internal_note' => true,
                            'attachments' => ['hidden-note.txt'],
                            'created_at' => $definition['created_at']->copy()->addHours(7),
                            'updated_at' => $definition['created_at']->copy()->addHours(7),
                        ])
                    );
                }
            }
        }
    }

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

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function filterExistingColumns(string $table, array $values): array
    {
        if (!Schema::hasTable($table)) {
            return $values;
        }

        $filtered = [];

        foreach ($values as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $filtered[$column] = $value;
            }
        }

        return $filtered;
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
