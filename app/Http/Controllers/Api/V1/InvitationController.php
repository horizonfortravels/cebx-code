<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvitationRequest;
use App\Http\Requests\AcceptInvitationRequest;
use App\Http\Requests\ListInvitationsRequest;
use App\Http\Resources\InvitationResource;
use App\Http\Resources\InvitationPreviewResource;
use App\Http\Resources\UserResource;
use App\Models\Invitation;
use App\Services\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(
        protected InvitationService $invitationService
    ) {}

    // ─── Authenticated Endpoints (Owner/Admin) ───────────────────

    /**
     * POST /api/v1/invitations
     * Create a new invitation.
     */
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        $this->authorize('create', Invitation::class);

        $invitation = $this->invitationService->createInvitation(
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الدعوة بنجاح.',
            'data'    => new InvitationResource($invitation),
        ], 201);
    }

    /**
     * GET /api/v1/invitations
     * List all invitations for the current account.
     */
    public function index(ListInvitationsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invitation::class);

        $invitations = $this->invitationService->listInvitations(
            $this->currentAccountId($request),
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data'    => InvitationResource::collection($invitations),
            'meta'    => [
                'current_page' => $invitations->currentPage(),
                'last_page'    => $invitations->lastPage(),
                'per_page'     => $invitations->perPage(),
                'total'        => $invitations->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/invitations/{id}
     * Show a single invitation.
     */
    public function show(Request $request, string $invitationId): JsonResponse
    {
        $invitation = $this->findInvitationForCurrentTenant($request, $invitationId);
        $this->authorize('view', $invitation);

        $invitation = $this->invitationService->getInvitation(
            $invitationId,
            $this->currentAccountId($request)
        );

        return response()->json([
            'success' => true,
            'data'    => new InvitationResource($invitation),
        ]);
    }

    /**
     * PATCH /api/v1/invitations/{id}/cancel
     * Cancel a pending invitation.
     */
    public function cancel(Request $request, string $invitationId): JsonResponse
    {
        $invitation = $this->findInvitationForCurrentTenant($request, $invitationId);
        $this->authorize('cancel', $invitation);

        $invitation = $this->invitationService->cancelInvitation(
            $invitationId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الدعوة بنجاح.',
            'data'    => new InvitationResource($invitation),
        ]);
    }

    /**
     * POST /api/v1/invitations/{id}/resend
     * Resend a pending invitation (new token + reset TTL).
     */
    public function resend(Request $request, string $invitationId): JsonResponse
    {
        $invitation = $this->findInvitationForCurrentTenant($request, $invitationId);
        $this->authorize('resend', $invitation);

        $invitation = $this->invitationService->resendInvitation(
            $invitationId,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة إرسال الدعوة بنجاح.',
            'data'    => new InvitationResource($invitation),
        ]);
    }

    // ─── Public Endpoints (Invitee — no auth required) ───────────

    /**
     * GET /api/v1/invitations/preview/{token}
     * Preview invitation details before accepting (public).
     */
    public function preview(string $token): JsonResponse
    {
        $invitation = $this->invitationService->getInvitationByToken($token);

        return response()->json([
            'success' => true,
            'data'    => new InvitationPreviewResource($invitation),
        ]);
    }

    /**
     * POST /api/v1/invitations/accept/{token}
     * Accept an invitation and create the user account (public).
     */
    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        $result = $this->invitationService->acceptInvitation(
            $token,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الدعوة وإنشاء حسابك بنجاح.',
            'data'    => [
                'user'       => new UserResource($result['user']),
                'invitation' => new InvitationResource($result['invitation']),
            ],
        ], 201);
    }

    private function currentAccountId(Request $request): string
    {
        $currentAccountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($currentAccountId !== '') {
            return $currentAccountId;
        }

        return trim((string) $request->user()->account_id);
    }

    private function findInvitationForCurrentTenant(Request $request, string $id): Invitation
    {
        return Invitation::withoutGlobalScopes()
            ->where('account_id', $this->currentAccountId($request))
            ->where('id', $id)
            ->firstOrFail();
    }
}
