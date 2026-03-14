<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\KycVerification;
use App\Models\OrganizationProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountTypeApiTest extends TestCase
{
    use RefreshDatabase;

    // ═══════════════════════════════════════════════════════════════
    //  Registration — Organization
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function registering_organization_account_creates_org_profile_and_kyc(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'account_name' => 'شركة التوصيل السريع',
            'account_type' => 'organization',
            'name'         => 'أحمد',
            'email'        => 'owner@fastship.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $response->assertStatus(201);

        $accountId = $response->json('data.account.id');
        $account = Account::find($accountId);

        $this->assertEquals('organization', $account->type);
        $this->assertNotNull($account->organizationProfile);
        $this->assertEquals('شركة التوصيل السريع', $account->organizationProfile->legal_name);
        $this->assertEquals('unverified', $account->kyc_status);
        $this->assertNotNull($account->kycVerification);
        $this->assertEquals('organization', $account->kycVerification->verification_type);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function registering_individual_account_does_not_create_org_profile(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'account_name' => 'حساب فردي',
            'account_type' => 'individual',
            'name'         => 'محمد',
            'email'        => 'mohammed@test.com',
            'password'     => 'Str0ng!Pass',
        ]);

        $response->assertStatus(201);

        $accountId = $response->json('data.account.id');
        $account = Account::find($accountId);

        $this->assertEquals('individual', $account->type);
        $this->assertNull($account->organizationProfile);
        $this->assertEquals('individual', $account->kycVerification->verification_type);
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /api/v1/account/type
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_get_account_type_info_for_organization(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/type');

        $response->assertOk()
                 ->assertJsonPath('data.type', 'organization')
                 ->assertJsonPath('data.kyc_status', 'unverified')
                 ->assertJsonStructure(['data' => ['organization', 'kyc']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_get_account_type_info_for_individual(): void
    {
        [$account, $owner] = $this->createIndividualAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/type');

        $response->assertOk()
                 ->assertJsonPath('data.type', 'individual')
                 ->assertJsonMissing(['organization']);
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET/PUT /api/v1/account/organization
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_get_organization_profile(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/organization');

        $response->assertOk()
                 ->assertJsonStructure(['data' => [
                     'legal_name', 'trade_name', 'registration_number',
                     'tax_id', 'country', 'billing_currency',
                 ]]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function individual_account_cannot_access_org_profile(): void
    {
        [$account, $owner] = $this->createIndividualAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/organization');

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_NOT_ORGANIZATION');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function owner_can_update_organization_profile(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->putJson('/api/v1/account/organization', [
            'registration_number' => 'CR-1234567890',
            'tax_id'              => 'TAX-9876543210',
            'country'             => 'SA',
            'city'                => 'الرياض',
            'industry'            => 'logistics',
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.registration_number', 'CR-1234567890')
                 ->assertJsonPath('data.country', 'SA');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function org_profile_update_is_audit_logged(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $this->putJson('/api/v1/account/organization', [
            'city' => 'جدة',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'account_id' => $account->id,
            'action'     => 'organization_profile.updated',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /api/v1/account/type-change
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_change_type_from_individual_to_organization(): void
    {
        [$account, $owner] = $this->createIndividualAccount();
        $this->actAs($owner, $account);

        $response = $this->postJson('/api/v1/account/type-change', [
            'new_type' => 'organization',
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.type', 'organization');

        $account->refresh();
        $this->assertNotNull($account->organizationProfile);
        $this->assertEquals('unverified', $account->kyc_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_change_type_after_adding_users(): void
    {
        [$account, $owner] = $this->createIndividualAccount();

        // Add another user (simulate active usage)
        User::withoutGlobalScopes()->create([
            'account_id' => $account->id,
            'name'       => 'موظف',
            'email'      => 'emp@test.com',
            'password'   => 'pass',
            'is_owner'   => false,
            'status'     => 'active',
        ]);

        $this->actAs($owner, $account);

        $response = $this->postJson('/api/v1/account/type-change', [
            'new_type' => 'organization',
        ]);

        $response->assertStatus(409)
                 ->assertJsonPath('error_code', 'ERR_TYPE_CHANGE_NOT_ALLOWED');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_change_to_same_type(): void
    {
        [$account, $owner] = $this->createIndividualAccount();
        $this->actAs($owner, $account);

        $response = $this->postJson('/api/v1/account/type-change', [
            'new_type' => 'individual',
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_SAME_TYPE');
    }

    // ═══════════════════════════════════════════════════════════════
    //  KYC — GET /api/v1/account/kyc + POST /api/v1/account/kyc/submit
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function can_get_kyc_status(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/kyc');

        $response->assertOk()
                 ->assertJsonPath('data.status', 'unverified')
                 ->assertJsonPath('data.verification_type', 'organization')
                 ->assertJsonStructure(['data' => ['required_documents']]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submitting_all_required_kyc_documents_sets_pending(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->postJson('/api/v1/account/kyc/submit', [
            'documents' => [
                'commercial_registration' => '/docs/cr.pdf',
                'tax_certificate'         => '/docs/tax.pdf',
                'national_address'        => '/docs/addr.pdf',
                'authorization_letter'    => '/docs/auth.pdf',
            ],
        ]);

        $response->assertOk()
                 ->assertJsonPath('data.status', 'pending');

        $this->assertEquals('pending', $account->fresh()->kyc_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function submitting_incomplete_kyc_documents_returns_error(): void
    {
        [$account, $owner] = $this->createOrgAccount();
        $this->actAs($owner, $account);

        $response = $this->postJson('/api/v1/account/kyc/submit', [
            'documents' => [
                'commercial_registration' => '/docs/cr.pdf',
                // Missing: tax_certificate, national_address, authorization_letter
            ],
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('error_code', 'ERR_MISSING_DOCUMENTS');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function individual_kyc_requires_different_documents(): void
    {
        [$account, $owner] = $this->createIndividualAccount();
        $this->actAs($owner, $account);

        $response = $this->getJson('/api/v1/account/kyc');

        $required = $response->json('data.required_documents');
        $this->assertArrayHasKey('national_id', $required);
        $this->assertArrayHasKey('address_proof', $required);
        $this->assertArrayNotHasKey('commercial_registration', $required);
    }

    // ═══════════════════════════════════════════════════════════════
    //  Tenant Isolation
    // ═══════════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function cannot_access_other_accounts_org_profile(): void
    {
        [$account1, $owner1] = $this->createOrgAccount('org1@test.com');
        [$account2, $owner2] = $this->createOrgAccount('org2@test.com');

        // Act as owner2 but try to see account1's profile
        $this->actAs($owner2, $account2);

        $response = $this->getJson('/api/v1/account/organization');

        // Should see only their own profile
        $this->assertEquals($account2->id, $response->json('data.account_id'));
    }

    // ═══════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════

    private function createOrgAccount(string $email = 'org-owner@test.com'): array
    {
        $account = Account::factory()->create([
            'type'   => 'organization',
            'status' => 'active',
        ]);

        $owner = User::factory()->owner()->create([
            'account_id' => $account->id,
            'email'      => $email,
        ]);

        OrganizationProfile::factory()->create([
            'account_id' => $account->id,
            'legal_name' => $account->name,
        ]);

        KycVerification::create([
            'account_id'         => $account->id,
            'status'             => 'unverified',
            'verification_type'  => 'organization',
            'required_documents' => KycVerification::requiredDocumentsFor('organization'),
        ]);

        $account->update(['kyc_status' => 'unverified']);

        return [$account, $owner];
    }

    private function createIndividualAccount(string $email = 'ind-owner@test.com'): array
    {
        $account = Account::factory()->create([
            'type'   => 'individual',
            'status' => 'active',
        ]);

        $owner = User::factory()->owner()->create([
            'account_id' => $account->id,
            'email'      => $email,
        ]);

        KycVerification::create([
            'account_id'         => $account->id,
            'status'             => 'unverified',
            'verification_type'  => 'individual',
            'required_documents' => KycVerification::requiredDocumentsFor('individual'),
        ]);

        $account->update(['kyc_status' => 'unverified']);

        return [$account, $owner];
    }

    private function actAs(User $user, Account $account): void
    {
        Sanctum::actingAs($user);
        app()->instance('current_account_id', $account->id);
    }
}
