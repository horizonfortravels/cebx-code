<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Organization;
use App\Models\OrganizationInvite;
use App\Models\OrganizationMember;
use App\Models\OrganizationWallet;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * OrganizationService — FR-ORG-001→010 (10 requirements)
 *
 * FR-ORG-001: Auto-create org on company registration / personal on individual
 * FR-ORG-002: Manage org profile (legal name, tax number, logo, billing)
 * FR-ORG-003: Invite members with TTL, accept/cancel/resend
 * FR-ORG-004: Custom roles + default templates (Accountant, Warehouse)
 * FR-ORG-005: Separate financial vs operational permissions
 * FR-ORG-006: Unified permission enforcement (UI/API/Export)
 * FR-ORG-007: Transfer ownership & member management
 * FR-ORG-008: Default unverified status, KYB requirements
 * FR-ORG-009: Per-org wallet (isolated balance)
 * FR-ORG-010: Wallet settings (threshold, auto-topup, freeze)
 */
class OrganizationService
{
    // ═══════════════════════════════════════════════════════════
    // FR-ORG-001: Create Organization / Personal Account
    // ═══════════════════════════════════════════════════════════

    /**
     * Create organization for company registration.
     */
    public function createOrganization(Account $account, User $owner, array $data): Organization
    {
        return DB::transaction(function () use ($account, $owner, $data) {
            $org = Organization::create(array_merge($data, [
                'account_id'          => $account->id,
                'verification_status' => Organization::STATUS_UNVERIFIED,
            ]));

            // Auto-create owner membership
            OrganizationMember::create([
                'organization_id' => $org->id,
                'user_id'         => $owner->id,
                'membership_role' => 'owner',
                'status'          => 'active',
                'can_view_financial' => true,
                'joined_at'       => now(),
            ]);

            // Auto-create wallet (FR-ORG-009)
            OrganizationWallet::create([
                'organization_id' => $org->id,
                'currency'        => $data['default_currency'] ?? 'SAR',
            ]);

            return $org->load('members', 'wallet');
        });
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-002: Manage Organization Profile
    // ═══════════════════════════════════════════════════════════

    public function updateProfile(string $orgId, array $data): Organization
    {
        $org = Organization::findOrFail($orgId);
        $org->update($data);
        return $org->fresh();
    }

    public function getOrganization(string $orgId): Organization
    {
        return Organization::with('members.user', 'wallet')->findOrFail($orgId);
    }

    public function getOrganizationsForAccount(Account $account): Collection
    {
        return Organization::where('account_id', $account->id)->with('wallet')->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-003: Invite Members
    // ═══════════════════════════════════════════════════════════

    public function inviteMember(string $orgId, User $inviter, array $data): OrganizationInvite
    {
        // Check no active invite/member exists
        $existing = OrganizationInvite::where('organization_id', $orgId)
            ->where('email', $data['email'])
            ->where('status', OrganizationInvite::STATUS_PENDING)
            ->first();

        if ($existing) {
            throw new \RuntimeException('An active invite already exists for this email');
        }

        $existingMember = OrganizationMember::where('organization_id', $orgId)
            ->whereHas('user', fn($q) => $q->where('email', $data['email']))
            ->where('status', 'active')
            ->first();

        if ($existingMember) {
            throw new \RuntimeException('User is already a member of this organization');
        }

        return OrganizationInvite::create([
            'organization_id' => $orgId,
            'invited_by'      => $inviter->id,
            'email'           => $data['email'],
            'phone'           => $data['phone'] ?? null,
            'token'           => OrganizationInvite::generateToken(),
            'role_id'         => $data['role_id'] ?? null,
            'membership_role' => $data['membership_role'] ?? 'member',
            'status'          => OrganizationInvite::STATUS_PENDING,
            'expires_at'      => now()->addHours(OrganizationInvite::TTL_HOURS),
        ]);
    }

    public function acceptInvite(string $token, User $user): OrganizationMember
    {
        $invite = OrganizationInvite::where('token', $token)->firstOrFail();

        if ($invite->isExpired()) {
            $invite->update(['status' => OrganizationInvite::STATUS_EXPIRED]);
            throw new \RuntimeException('Invite has expired');
        }

        $invite->accept($user->id);

        return OrganizationMember::where('organization_id', $invite->organization_id)
            ->where('user_id', $user->id)->firstOrFail();
    }

    public function cancelInvite(string $inviteId): void
    {
        $invite = OrganizationInvite::findOrFail($inviteId);
        $invite->cancel();
    }

    public function resendInvite(string $inviteId): OrganizationInvite
    {
        $invite = OrganizationInvite::findOrFail($inviteId);
        $invite->resend();
        return $invite->fresh();
    }

    public function listInvites(string $orgId): Collection
    {
        return OrganizationInvite::where('organization_id', $orgId)
            ->with('inviter')
            ->orderByDesc('created_at')
            ->get();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-004: Custom Roles + Templates
    // ═══════════════════════════════════════════════════════════

    public function listPermissionCatalog(?string $module = null): Collection
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return collect();
        }

        $query = Permission::query();
        if ($module) {
            $query->where('group', $module);
        }

        return $query->orderBy('key')->get();
    }

    public function getFinancialPermissions(): Collection
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return collect();
        }

        return Permission::query()->where('group', 'financial')->orderBy('key')->get();
    }

    public function getOperationalPermissions(): Collection
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return collect();
        }

        return Permission::query()->where('group', '!=', 'financial')->orderBy('key')->get();
    }

    public function validatePermissions(array $keys): array
    {
        if (!DB::getSchemaBuilder()->hasTable('permissions')) {
            return ['valid' => [], 'invalid' => array_values(array_unique($keys))];
        }

        $normalized = array_values(array_unique(array_map(
            static fn (string $key): string => str_replace(':', '.', trim($key)),
            $keys
        )));

        $valid = Permission::query()->whereIn('key', $normalized)->pluck('key')->toArray();
        $invalid = array_values(array_diff($normalized, $valid));

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-005: Financial vs Operational Permissions
    // ═══════════════════════════════════════════════════════════

    public function setFinancialAccess(string $memberId, bool $canView): OrganizationMember
    {
        $member = OrganizationMember::findOrFail($memberId);
        $member->update(['can_view_financial' => $canView]);
        return $member->fresh();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-006: Unified Permission Check
    // ═══════════════════════════════════════════════════════════

    public function checkPermission(string $orgId, string $userId, string $permissionKey): bool
    {
        $member = OrganizationMember::where('organization_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();

        if (!$member) return false;

        // Financial permission gate (FR-ORG-005)
        $normalizedPermissionKey = str_replace(':', '.', $permissionKey);
        if (str_starts_with($normalizedPermissionKey, 'financial.') && !$member->can_view_financial) {
            return false;
        }

        return $member->hasPermission($normalizedPermissionKey);
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-007: Transfer Ownership & Member Management
    // ═══════════════════════════════════════════════════════════

    public function transferOwnership(string $orgId, string $currentOwnerId, string $newOwnerId): void
    {
        DB::transaction(function () use ($orgId, $currentOwnerId, $newOwnerId) {
            $current = OrganizationMember::where('organization_id', $orgId)
                ->where('user_id', $currentOwnerId)
                ->where('membership_role', 'owner')
                ->firstOrFail();

            $new = OrganizationMember::where('organization_id', $orgId)
                ->where('user_id', $newOwnerId)
                ->where('status', 'active')
                ->firstOrFail();

            $current->update(['membership_role' => 'admin']);
            $new->update(['membership_role' => 'owner', 'can_view_financial' => true]);
        });
    }

    public function suspendMember(string $memberId, string $reason): OrganizationMember
    {
        $member = OrganizationMember::findOrFail($memberId);
        if ($member->isOwner()) throw new \RuntimeException('Cannot suspend the owner');
        $member->suspend($reason);
        return $member->fresh();
    }

    public function removeMember(string $memberId): void
    {
        $member = OrganizationMember::findOrFail($memberId);
        if ($member->isOwner()) throw new \RuntimeException('Cannot remove the owner. Transfer ownership first.');
        $member->remove();
    }

    public function listMembers(string $orgId): Collection
    {
        return OrganizationMember::where('organization_id', $orgId)
            ->with('user', 'role')
            ->orderByRaw("FIELD(membership_role, 'owner', 'admin', 'member')")
            ->get();
    }

    public function updateMemberRole(string $memberId, ?string $roleId, ?string $membershipRole = null): OrganizationMember
    {
        $member = OrganizationMember::findOrFail($memberId);
        $updates = [];
        if ($roleId !== null) $updates['role_id'] = $roleId;
        if ($membershipRole) $updates['membership_role'] = $membershipRole;
        $member->update($updates);
        return $member->fresh();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-008: Verification Status
    // ═══════════════════════════════════════════════════════════

    public function submitForVerification(string $orgId): Organization
    {
        $org = Organization::findOrFail($orgId);
        $org->update(['verification_status' => Organization::STATUS_PENDING_REVIEW]);
        return $org->fresh();
    }

    public function verifyOrganization(string $orgId): Organization
    {
        $org = Organization::findOrFail($orgId);
        $org->verify();
        return $org->fresh();
    }

    public function rejectOrganization(string $orgId, string $reason): Organization
    {
        $org = Organization::findOrFail($orgId);
        $org->reject($reason);
        return $org->fresh();
    }

    // ═══════════════════════════════════════════════════════════
    // FR-ORG-009/010: Organization Wallet
    // ═══════════════════════════════════════════════════════════

    public function getWallet(string $orgId): OrganizationWallet
    {
        return OrganizationWallet::where('organization_id', $orgId)->firstOrFail();
    }

    public function topUpWallet(string $orgId, float $amount): OrganizationWallet
    {
        $wallet = $this->getWallet($orgId);
        $wallet->credit($amount);
        return $wallet->fresh();
    }

    public function updateWalletSettings(string $orgId, array $settings): OrganizationWallet
    {
        $wallet = $this->getWallet($orgId);
        $wallet->update($settings);
        return $wallet->fresh();
    }

    public function getWalletSummary(string $orgId): array
    {
        $wallet = $this->getWallet($orgId);
        return [
            'balance'            => $wallet->balance,
            'reserved_balance'   => $wallet->reserved_balance,
            'available_balance'  => $wallet->getAvailableBalance(),
            'is_low_balance'     => $wallet->isLowBalance(),
            'currency'           => $wallet->currency,
            'needs_auto_topup'   => $wallet->needsAutoTopup(),
            'settings' => [
                'low_balance_threshold' => $wallet->low_balance_threshold,
                'auto_topup_enabled'    => $wallet->auto_topup_enabled,
                'auto_topup_amount'     => $wallet->auto_topup_amount,
                'allow_negative'        => $wallet->allow_negative,
            ],
        ];
    }
}
