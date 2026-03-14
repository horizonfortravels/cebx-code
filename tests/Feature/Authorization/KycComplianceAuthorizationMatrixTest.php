<?php

namespace Tests\Feature\Authorization;

use App\Models\Account;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KycComplianceAuthorizationMatrixTest extends TestCase
{
    #[Test]
    public function external_same_tenant_with_permission_gets_2xx_for_kyc_and_compliance_reads(): void
    {
        $this->skipIfMissingTables(['kyc_verifications', 'kyc_documents']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $user->id, (string) $account->id, [
            'kyc.documents.read',
            'kyc.documents',
            'compliance.read',
        ]);

        $documentId = $this->createKycDocument((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/kyc/documents/' . $documentId . '/download')
            ->assertOk();

        if (Schema::hasTable('transport_documents')) {
            $this->getJson('/api/v1/compliance/documents')
                ->assertOk();
        }
    }

    #[Test]
    public function external_same_tenant_missing_permissions_gets_403(): void
    {
        $this->skipIfMissingTables(['kyc_verifications', 'kyc_documents']);

        $account = $this->createAccount();
        $user = $this->createUser([
            'account_id' => (string) $account->id,
            'user_type' => 'external',
        ]);

        $documentId = $this->createKycDocument((string) $account->id, (string) $user->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/kyc/documents/' . $documentId . '/download')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');

        $this->getJson('/api/v1/compliance/documents')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'ERR_PERMISSION');
    }

    #[Test]
    public function external_cross_tenant_kyc_document_returns_404_even_with_permission(): void
    {
        $this->skipIfMissingTables(['kyc_verifications', 'kyc_documents']);

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);

        $userB = $this->createUser([
            'account_id' => (string) $accountB->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, [
            'kyc.documents.read',
            'kyc.documents',
        ]);

        $documentId = $this->createKycDocument((string) $accountB->id, (string) $userB->id);

        Sanctum::actingAs($userA);

        $this->getJson('/api/v1/kyc/documents/' . $documentId . '/download')
            ->assertNotFound();
    }

    #[Test]
    public function external_cross_tenant_compliance_validate_returns_404(): void
    {
        $this->skipIfMissingTables(['transport_documents']);

        $accountA = $this->createAccount();
        $accountB = $this->createAccount();

        $userA = $this->createUser([
            'account_id' => (string) $accountA->id,
            'user_type' => 'external',
        ]);

        $this->grantExternalPermissions((string) $userA->id, (string) $accountA->id, [
            'compliance.manage',
        ]);

        $documentId = $this->createTransportDocument((string) $accountB->id);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/compliance/documents/' . $documentId . '/validate')
            ->assertNotFound();
    }

    /**
     * @param array<int, string> $tables
     */
    private function skipIfMissingTables(array $tables): void
    {
        foreach ($tables as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped(sprintf('%s table is not available in this environment.', $table));
            }
        }
    }

    /**
     * @param array<int, string> $permissions
     */
    private function grantExternalPermissions(string $userId, string $accountId, array $permissions): void
    {
        $roleId = (string) Str::uuid();

        $rolePayload = [
            'id' => $roleId,
            'account_id' => $accountId,
            'name' => 'kyc_comp_' . Str::random(8),
            'display_name' => 'Kyc Compliance Matrix Role',
            'description' => 'KYC/compliance authorization matrix role',
            'is_system' => false,
            'template' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'slug')) {
            $rolePayload['slug'] = Str::slug($rolePayload['name']);
        }
        if (Schema::hasColumn('roles', 'deleted_at')) {
            $rolePayload['deleted_at'] = null;
        }

        DB::table('roles')->insert($rolePayload);

        foreach ($permissions as $permissionKey) {
            $permission = $this->upsertPermission($permissionKey, 'external');

            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permission->id,
            ], [
                'granted_at' => now(),
            ]);
        }

        DB::table('user_role')->updateOrInsert([
            'user_id' => $userId,
            'role_id' => $roleId,
        ], [
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);
    }

    private function createKycDocument(string $accountId, string $uploadedBy): string
    {
        $verificationId = (string) Str::uuid();
        $documentId = (string) Str::uuid();

        DB::table('kyc_verifications')->insert(array_filter([
            'id' => $verificationId,
            'account_id' => $accountId,
            'status' => 'pending',
            'verification_type' => 'organization',
            'required_documents' => Schema::hasColumn('kyc_verifications', 'required_documents') ? json_encode(['commercial_register']) : null,
            'submitted_documents' => Schema::hasColumn('kyc_verifications', 'submitted_documents') ? json_encode([]) : null,
            'verification_level' => Schema::hasColumn('kyc_verifications', 'verification_level') ? 'basic' : null,
            'review_notes' => Schema::hasColumn('kyc_verifications', 'review_notes') ? null : null,
            'review_count' => Schema::hasColumn('kyc_verifications', 'review_count') ? 0 : null,
            'created_at' => Schema::hasColumn('kyc_verifications', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('kyc_verifications', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null));

        DB::table('kyc_documents')->insert(array_filter([
            'id' => $documentId,
            'account_id' => $accountId,
            'kyc_verification_id' => $verificationId,
            'document_type' => 'commercial_register',
            'original_filename' => 'doc.pdf',
            'stored_path' => '/tmp/doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'file_hash' => Schema::hasColumn('kyc_documents', 'file_hash') ? hash('sha256', (string) Str::uuid()) : null,
            'uploaded_by' => $uploadedBy,
            'is_sensitive' => Schema::hasColumn('kyc_documents', 'is_sensitive') ? true : null,
            'is_purged' => Schema::hasColumn('kyc_documents', 'is_purged') ? false : null,
            'created_at' => Schema::hasColumn('kyc_documents', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('kyc_documents', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null));

        return $documentId;
    }

    private function createTransportDocument(string $accountId): string
    {
        $documentId = (string) Str::uuid();

        DB::table('transport_documents')->insert(array_filter([
            'id' => $documentId,
            'account_id' => $accountId,
            'shipment_id' => (string) Str::uuid(),
            'document_type' => 'AWB',
            'document_number' => '999-' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'issuer' => Schema::hasColumn('transport_documents', 'issuer') ? 'Issuer' : null,
            'origin_code' => 'RUH',
            'destination_code' => 'JED',
            'pieces' => 1,
            'gross_weight_kg' => 1.5,
            'chargeable_weight_kg' => Schema::hasColumn('transport_documents', 'chargeable_weight_kg') ? 1.5 : null,
            'declared_value' => Schema::hasColumn('transport_documents', 'declared_value') ? 100 : null,
            'declared_value_currency' => Schema::hasColumn('transport_documents', 'declared_value_currency') ? 'SAR' : null,
            'goods_description' => Schema::hasColumn('transport_documents', 'goods_description') ? 'Docs' : null,
            'handling_codes' => Schema::hasColumn('transport_documents', 'handling_codes') ? 'GEN' : null,
            'is_validated' => Schema::hasColumn('transport_documents', 'is_validated') ? false : null,
            'validation_errors' => Schema::hasColumn('transport_documents', 'validation_errors') ? json_encode([]) : null,
            'status' => Schema::hasColumn('transport_documents', 'status') ? 'draft' : null,
            'metadata' => Schema::hasColumn('transport_documents', 'metadata') ? json_encode([]) : null,
            'created_at' => Schema::hasColumn('transport_documents', 'created_at') ? now() : null,
            'updated_at' => Schema::hasColumn('transport_documents', 'updated_at') ? now() : null,
        ], static fn ($value): bool => $value !== null));

        return $documentId;
    }

    private function createAccount(): Account
    {
        $payload = [
            'name' => 'Account ' . Str::random(8),
        ];

        if (Schema::hasColumn('accounts', 'slug')) {
            $payload['slug'] = 'acct-' . Str::lower(Str::random(8));
        }
        if (Schema::hasColumn('accounts', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('accounts', 'type')) {
            $payload['type'] = 'organization';
        }
        if (Schema::hasColumn('accounts', 'kyc_status')) {
            $payload['kyc_status'] = 'not_submitted';
        }
        if (Schema::hasColumn('accounts', 'settings')) {
            $payload['settings'] = [];
        }
        if (Schema::hasColumn('accounts', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('accounts', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $accountId = $this->insertRowAndReturnId('accounts', $payload);

        /** @var Account $account */
        $account = Account::withoutGlobalScopes()->where('id', $accountId)->firstOrFail();

        return $account;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createUser(array $overrides): User
    {
        $payload = [
            'name' => 'User ' . Str::random(8),
            'email' => Str::lower(Str::random(10)) . '@example.test',
            'password' => Hash::make('Password1!'),
        ];

        if (Schema::hasColumn('users', 'account_id')) {
            $payload['account_id'] = $overrides['account_id'] ?? null;
        }
        if (Schema::hasColumn('users', 'user_type')) {
            $payload['user_type'] = $overrides['user_type'] ?? 'external';
        }
        if (Schema::hasColumn('users', 'status')) {
            $payload['status'] = $overrides['status'] ?? 'active';
        }
        if (Schema::hasColumn('users', 'is_active')) {
            $payload['is_active'] = $overrides['is_active'] ?? true;
        }
        if (Schema::hasColumn('users', 'is_owner')) {
            $payload['is_owner'] = $overrides['is_owner'] ?? false;
        }
        if (Schema::hasColumn('users', 'locale')) {
            $payload['locale'] = $overrides['locale'] ?? 'en';
        }
        if (Schema::hasColumn('users', 'timezone')) {
            $payload['timezone'] = $overrides['timezone'] ?? 'UTC';
        }
        if (Schema::hasColumn('users', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('users', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $userId = $this->insertRowAndReturnId('users', $payload);

        /** @var User $user */
        $user = User::withoutGlobalScopes()->where('id', $userId)->firstOrFail();

        return $user;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertRowAndReturnId(string $table, array $payload): string|int
    {
        if (!array_key_exists('id', $payload) && !$this->isNumericId($table)) {
            $payload['id'] = (string) Str::uuid();
        }

        if ($this->isNumericId($table)) {
            unset($payload['id']);
            return DB::table($table)->insertGetId($payload);
        }

        DB::table($table)->insert($payload);

        return $payload['id'];
    }

    private function isNumericId(string $table): bool
    {
        if (!Schema::hasColumn($table, 'id')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType($table, 'id'));

        return in_array($type, [
            'integer', 'int', 'tinyint', 'smallint', 'mediumint', 'bigint',
            'biginteger', 'unsignedinteger', 'unsignedbiginteger',
        ], true);
    }

    private function upsertPermission(string $key, string $audience): Permission
    {
        $values = [
            'group' => explode('.', $key)[0],
            'display_name' => $key,
            'description' => $key,
        ];

        if (Schema::hasColumn('permissions', 'audience')) {
            $values['audience'] = $audience;
        }

        return Permission::query()->updateOrCreate(['key' => $key], $values);
    }
}
