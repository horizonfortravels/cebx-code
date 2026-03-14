<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\OrganizationMember;
use App\Models\OrganizationWallet;
use App\Models\PermissionCatalog;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit Tests — ORG Module (FR-ORG-001→010)
 *
 * 40 tests covering all 10 functional requirements.
 */
class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    private OrganizationService $service;
    private Account $account;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(OrganizationService::class);
        $this->account = Account::factory()->create();
        $role = Role::factory()->create(['account_id' => $this->account->id, 'slug' => 'owner']);
        $this->owner = $this->createUserWithRole((string) $this->account->id, (string) $role->id);
    }

    private function createOrg(): Organization
    {
        return $this->service->createOrganization($this->account, $this->owner, [
            'legal_name' => 'شركة تقنية المحدودة',
            'country_code' => 'SA',
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-001: Create Organization (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_organization_auto_creates_owner(): void
    {
        $org = $this->createOrg();
        $this->assertNotNull($org->id);

        $ownerMember = $org->members->firstWhere('user_id', $this->owner->id);
        $this->assertNotNull($ownerMember);
        $this->assertEquals('owner', $ownerMember->membership_role);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_create_organization_auto_creates_wallet(): void
    {
        $org = $this->createOrg();
        $this->assertNotNull($org->wallet);
        $this->assertEquals('SAR', $org->wallet->currency);
        $this->assertEquals(0, $org->wallet->balance);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_organization_default_unverified(): void
    {
        $org = $this->createOrg();
        $this->assertEquals(Organization::STATUS_UNVERIFIED, $org->verification_status);
        $this->assertFalse($org->isVerified());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_owner_has_financial_access(): void
    {
        $org = $this->createOrg();
        $ownerMember = $org->members->firstWhere('user_id', $this->owner->id);
        $this->assertTrue($ownerMember->can_view_financial);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-002: Manage Profile (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_update_org_profile(): void
    {
        $org = $this->createOrg();
        $updated = $this->service->updateProfile($org->id, ['legal_name' => 'New Name LLC']);
        $this->assertEquals('New Name LLC', $updated->legal_name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_get_organization_with_members(): void
    {
        $org = $this->createOrg();
        $loaded = $this->service->getOrganization($org->id);
        $this->assertCount(1, $loaded->members);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_orgs_for_account(): void
    {
        $this->createOrg();
        $orgs = $this->service->getOrganizationsForAccount($this->account);
        $this->assertCount(1, $orgs);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-003: Invite Members (6 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_invite_member(): void
    {
        $org = $this->createOrg();
        $invite = $this->service->inviteMember($org->id, $this->owner, ['email' => 'member@test.com']);
        $this->assertEquals(OrganizationInvite::STATUS_PENDING, $invite->status);
        $this->assertNotNull($invite->token);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_duplicate_invite_throws(): void
    {
        $org = $this->createOrg();
        $this->service->inviteMember($org->id, $this->owner, ['email' => 'dup@test.com']);

        $this->expectException(\RuntimeException::class);
        $this->service->inviteMember($org->id, $this->owner, ['email' => 'dup@test.com']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_accept_invite(): void
    {
        $org = $this->createOrg();
        $invite = $this->service->inviteMember($org->id, $this->owner, ['email' => 'new@test.com']);

        $newUserRole = Role::factory()->create(['account_id' => $this->account->id]);
        $newUser = $this->createUserWithRole((string) $this->account->id, (string) $newUserRole->id, [
            'email' => 'new@test.com',
        ]);
        $member = $this->service->acceptInvite($invite->token, $newUser);

        $this->assertEquals('active', $member->status);
        $this->assertEquals(OrganizationInvite::STATUS_ACCEPTED, $invite->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cancel_invite(): void
    {
        $org = $this->createOrg();
        $invite = $this->service->inviteMember($org->id, $this->owner, ['email' => 'cancel@test.com']);
        $this->service->cancelInvite($invite->id);
        $this->assertEquals(OrganizationInvite::STATUS_CANCELLED, $invite->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_resend_invite(): void
    {
        $org = $this->createOrg();
        $invite = $this->service->inviteMember($org->id, $this->owner, ['email' => 'resend@test.com']);
        $originalExpiry = $invite->expires_at;

        $resent = $this->service->resendInvite($invite->id);
        $this->assertEquals(1, $resent->resend_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_expired_invite_rejected(): void
    {
        $org = $this->createOrg();
        $invite = OrganizationInvite::factory()->expired()->create([
            'organization_id' => $org->id, 'invited_by' => $this->owner->id,
        ]);

        $newUserRole = Role::factory()->create(['account_id' => $this->account->id]);
        $newUser = $this->createUserWithRole((string) $this->account->id, (string) $newUserRole->id);

        $this->expectException(\RuntimeException::class);
        $this->service->acceptInvite($invite->token, $newUser);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-004: Permissions Catalog (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_list_permission_catalog(): void
    {
        PermissionCatalog::create(['key' => 'shipments.create', 'name' => 'Create Shipments', 'module' => 'SH', 'category' => 'operational']);
        PermissionCatalog::create(['key' => 'finance.view', 'name' => 'View Finance', 'module' => 'PAY', 'category' => 'financial']);

        $catalog = $this->service->listPermissionCatalog();
        $this->assertCount(2, $catalog);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_filter_by_module(): void
    {
        PermissionCatalog::create(['key' => 'sh.create', 'name' => 'SH', 'module' => 'SH', 'category' => 'operational']);
        PermissionCatalog::create(['key' => 'pay.view', 'name' => 'PAY', 'module' => 'PAY', 'category' => 'financial']);

        $catalog = $this->service->listPermissionCatalog('SH');
        $this->assertCount(1, $catalog);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_validate_permissions(): void
    {
        PermissionCatalog::create(['key' => 'valid.perm', 'name' => 'Valid', 'module' => 'SH', 'category' => 'operational']);

        $result = $this->service->validatePermissions(['valid.perm', 'unknown.perm']);
        $this->assertContains('valid.perm', $result['valid']);
        $this->assertContains('unknown.perm', $result['invalid']);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-005: Financial vs Operational (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_set_financial_access(): void
    {
        $org = $this->createOrg();
        $member = OrganizationMember::factory()->create(['organization_id' => $org->id, 'can_view_financial' => false]);

        $updated = $this->service->setFinancialAccess($member->id, true);
        $this->assertTrue($updated->can_view_financial);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_financial_permissions_list(): void
    {
        PermissionCatalog::create(['key' => 'profit.view', 'name' => 'View Profit', 'module' => 'RPT', 'category' => 'financial']);
        PermissionCatalog::create(['key' => 'shipments.view', 'name' => 'View Shipments', 'module' => 'SH', 'category' => 'operational']);

        $financial = $this->service->getFinancialPermissions();
        $this->assertCount(1, $financial);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_operational_permissions_list(): void
    {
        PermissionCatalog::create(['key' => 'ops.perm', 'name' => 'Ops', 'module' => 'SH', 'category' => 'operational']);
        $ops = $this->service->getOperationalPermissions();
        $this->assertCount(1, $ops);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-006: Unified Permission Check (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_owner_has_all_permissions(): void
    {
        $org = $this->createOrg();
        $this->assertTrue($this->service->checkPermission($org->id, $this->owner->id, 'any.permission'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_member_without_permission_denied(): void
    {
        $org = $this->createOrg();
        $memberRole = Role::factory()->create(['account_id' => $this->account->id]);
        $member = $this->createUserWithRole((string) $this->account->id, (string) $memberRole->id);
        OrganizationMember::factory()->create(['organization_id' => $org->id, 'user_id' => $member->id]);

        $this->assertFalse($this->service->checkPermission($org->id, $member->id, 'admin.only'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_non_member_denied(): void
    {
        $org = $this->createOrg();
        $strangerAccount = Account::factory()->create();
        $strangerRole = Role::factory()->create(['account_id' => $this->account->id]);
        $stranger = $this->createUserWithRole((string) $strangerAccount->id, (string) $strangerRole->id);
        $this->assertFalse($this->service->checkPermission($org->id, $stranger->id, 'any'));
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-007: Ownership & Member Management (5 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_transfer_ownership(): void
    {
        $org = $this->createOrg();
        $newOwnerRole = Role::factory()->create(['account_id' => $this->account->id]);
        $newOwner = $this->createUserWithRole((string) $this->account->id, (string) $newOwnerRole->id);
        OrganizationMember::factory()->create(['organization_id' => $org->id, 'user_id' => $newOwner->id]);

        $this->service->transferOwnership($org->id, $this->owner->id, $newOwner->id);

        $oldMember = OrganizationMember::where('organization_id', $org->id)->where('user_id', $this->owner->id)->first();
        $newMember = OrganizationMember::where('organization_id', $org->id)->where('user_id', $newOwner->id)->first();

        $this->assertEquals('admin', $oldMember->membership_role);
        $this->assertEquals('owner', $newMember->membership_role);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_suspend_member(): void
    {
        $org = $this->createOrg();
        $member = OrganizationMember::factory()->create(['organization_id' => $org->id]);

        $suspended = $this->service->suspendMember($member->id, 'Policy violation');
        $this->assertEquals('suspended', $suspended->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cannot_suspend_owner(): void
    {
        $org = $this->createOrg();
        $ownerMember = OrganizationMember::where('organization_id', $org->id)->where('membership_role', 'owner')->first();

        $this->expectException(\RuntimeException::class);
        $this->service->suspendMember($ownerMember->id, 'Test');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_remove_member(): void
    {
        $org = $this->createOrg();
        $member = OrganizationMember::factory()->create(['organization_id' => $org->id]);

        $this->service->removeMember($member->id);
        $this->assertEquals('removed', $member->fresh()->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_cannot_remove_owner(): void
    {
        $org = $this->createOrg();
        $ownerMember = OrganizationMember::where('organization_id', $org->id)->where('membership_role', 'owner')->first();

        $this->expectException(\RuntimeException::class);
        $this->service->removeMember($ownerMember->id);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-008: Verification Status (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_submit_for_verification(): void
    {
        $org = $this->createOrg();
        $submitted = $this->service->submitForVerification($org->id);
        $this->assertEquals(Organization::STATUS_PENDING_REVIEW, $submitted->verification_status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_verify_organization(): void
    {
        $org = $this->createOrg();
        $verified = $this->service->verifyOrganization($org->id);
        $this->assertTrue($verified->isVerified());
        $this->assertNotNull($verified->verified_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_reject_organization(): void
    {
        $org = $this->createOrg();
        $rejected = $this->service->rejectOrganization($org->id, 'Missing CR');
        $this->assertEquals(Organization::STATUS_REJECTED, $rejected->verification_status);
        $this->assertEquals('Missing CR', $rejected->rejection_reason);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-009: Per-Org Wallet (4 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_isolated_per_org(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->getWallet($org->id);
        $this->assertEquals(0, $wallet->balance);
        $this->assertEquals($org->id, $wallet->organization_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_topup_wallet(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->topUpWallet($org->id, 500);
        $this->assertEquals(500, $wallet->balance);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_reserve_and_release(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->topUpWallet($org->id, 1000);

        $wallet->reserve(300);
        $this->assertEquals(700, $wallet->getAvailableBalance());

        $wallet->releaseReservation(300);
        $this->assertEquals(1000, $wallet->getAvailableBalance());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_insufficient_funds_throws(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->getWallet($org->id);

        $this->expectException(\RuntimeException::class);
        $wallet->debit(500);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-010: Wallet Settings (3 tests)
    // ═══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_update_wallet_settings(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->updateWalletSettings($org->id, [
            'low_balance_threshold' => 200, 'auto_topup_enabled' => true,
            'auto_topup_amount' => 500, 'auto_topup_threshold' => 250,
        ]);
        $this->assertTrue($wallet->auto_topup_enabled);
        $this->assertEquals(200, $wallet->low_balance_threshold);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_low_balance_detection(): void
    {
        $org = $this->createOrg();
        $wallet = $this->service->getWallet($org->id);
        $this->assertTrue($wallet->isLowBalance()); // balance=0, threshold=100
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function test_wallet_summary(): void
    {
        $org = $this->createOrg();
        $this->service->topUpWallet($org->id, 500);
        $summary = $this->service->getWalletSummary($org->id);

        $this->assertEquals(500, $summary['balance']);
        $this->assertEquals(500, $summary['available_balance']);
        $this->assertEquals('SAR', $summary['currency']);
        $this->assertArrayHasKey('settings', $summary);
    }
}
