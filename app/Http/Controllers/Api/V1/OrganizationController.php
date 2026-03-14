<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OrganizationController — FR-ORG-001→010
 */
class OrganizationController extends Controller
{
    public function __construct(private OrganizationService $service) {}

    // ═══════════════ FR-ORG-001: Create Org ══════════════════

    public function create(Request $request): JsonResponse
    {
        $this->ensureOrganizationAccount($request);

        $data = $request->validate([
            'legal_name'   => 'required|string|max:300',
            'trade_name'   => 'nullable|string|max:300',
            'registration_number' => 'nullable|string|max:100',
            'tax_number'   => 'nullable|string|max:100',
            'country_code' => 'nullable|string|size:2',
            'billing_address' => 'nullable|string',
            'billing_email' => 'nullable|email',
            'phone'        => 'nullable|string|max:20',
            'default_currency' => 'nullable|string|size:3',
        ]);

        $org = $this->service->createOrganization($request->user()->account, $request->user(), $data);
        return response()->json(['status' => 'success', 'data' => $org], 201);
    }

    // ═══════════════ FR-ORG-002: Profile ═════════════════════

    public function show(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->getOrganization($orgId)]);
    }

    public function update(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);

        $data = $request->validate([
            'legal_name'   => 'nullable|string|max:300',
            'trade_name'   => 'nullable|string|max:300',
            'billing_address' => 'nullable|string',
            'billing_email' => 'nullable|email',
            'tax_number'   => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
        ]);
        return response()->json(['status' => 'success', 'data' => $this->service->updateProfile($orgId, $data)]);
    }

    public function listForAccount(Request $request): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->getOrganizationsForAccount($request->user()->account)]);
    }

    // ═══════════════ FR-ORG-003: Invites ═════════════════════

    public function invite(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);

        $data = $request->validate([
            'email'           => 'required|email',
            'phone'           => 'nullable|string|max:20',
            'role_id'         => 'nullable|uuid',
            'membership_role' => 'nullable|in:admin,member',
        ]);
        $invite = $this->service->inviteMember($orgId, $request->user(), $data);
        return response()->json(['status' => 'success', 'data' => $invite], 201);
    }

    public function acceptInvite(Request $request): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['token' => 'required|string|size:64']);
        $member = $this->service->acceptInvite($data['token'], $request->user());
        return response()->json(['status' => 'success', 'data' => $member]);
    }

    public function cancelInvite(Request $request, string $inviteId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $this->service->cancelInvite($inviteId);
        return response()->json(['status' => 'success']);
    }

    public function resendInvite(Request $request, string $inviteId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->resendInvite($inviteId)]);
    }

    public function listInvites(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->listInvites($orgId)]);
    }

    // ═══════════════ FR-ORG-004: Permissions Catalog ═════════

    public function permissionCatalog(Request $request): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->listPermissionCatalog($request->input('module'))]);
    }

    // ═══════════════ FR-ORG-005: Financial Access ════════════

    public function setFinancialAccess(Request $request, string $memberId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['can_view_financial' => 'required|boolean']);
        return response()->json(['status' => 'success', 'data' => $this->service->setFinancialAccess($memberId, $data['can_view_financial'])]);
    }

    // ═══════════════ FR-ORG-006: Permission Check ════════════

    public function checkPermission(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['permission' => 'required|string']);
        $allowed = $this->service->checkPermission($orgId, $request->user()->id, $data['permission']);
        return response()->json(['status' => 'success', 'data' => ['allowed' => $allowed]]);
    }

    // ═══════════════ FR-ORG-007: Members & Ownership ═════════

    public function listMembers(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->listMembers($orgId)]);
    }

    public function transferOwnership(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['new_owner_id' => 'required|uuid']);
        $this->service->transferOwnership($orgId, $request->user()->id, $data['new_owner_id']);
        return response()->json(['status' => 'success']);
    }

    public function suspendMember(Request $request, string $memberId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['reason' => 'required|string']);
        return response()->json(['status' => 'success', 'data' => $this->service->suspendMember($memberId, $data['reason'])]);
    }

    public function removeMember(Request $request, string $memberId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $this->service->removeMember($memberId);
        return response()->json(['status' => 'success']);
    }

    public function updateMemberRole(Request $request, string $memberId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['role_id' => 'nullable|uuid', 'membership_role' => 'nullable|in:admin,member']);
        return response()->json(['status' => 'success', 'data' => $this->service->updateMemberRole($memberId, $data['role_id'] ?? null, $data['membership_role'] ?? null)]);
    }

    // ═══════════════ FR-ORG-008: Verification ════════════════

    public function submitVerification(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->submitForVerification($orgId)]);
    }

    // ═══════════════ FR-ORG-009/010: Wallet ══════════════════

    public function walletSummary(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        return response()->json(['status' => 'success', 'data' => $this->service->getWalletSummary($orgId)]);
    }

    public function topUpWallet(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate(['amount' => 'required|numeric|min:1']);
        return response()->json(['status' => 'success', 'data' => $this->service->topUpWallet($orgId, $data['amount'])]);
    }

    public function updateWalletSettings(Request $request, string $orgId): JsonResponse
    {
        $this->ensureOrganizationAccount($request);
        $data = $request->validate([
            'low_balance_threshold' => 'nullable|numeric|min:0',
            'auto_topup_enabled'    => 'nullable|boolean',
            'auto_topup_amount'     => 'nullable|numeric|min:1',
            'auto_topup_threshold'  => 'nullable|numeric|min:0',
            'allow_negative'        => 'nullable|boolean',
        ]);
        return response()->json(['status' => 'success', 'data' => $this->service->updateWalletSettings($orgId, $data)]);
    }

    private function ensureOrganizationAccount(Request $request): void
    {
        $account = $request->user()?->account;

        if (!$account || !$account->isOrganization()) {
            throw BusinessException::accountUpgradeRequired();
        }
    }
}
