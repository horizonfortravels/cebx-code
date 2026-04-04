<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalTicketReadService;
use App\Services\InternalTicketWorkflowService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalTicketReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalTicketReadService $readService,
        private readonly InternalTicketWorkflowService $workflowService,
    ) {}

    public function index(Request $request, InternalControlPlane $controlPlane): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $this->normalizeFilter((string) $request->query('status', ''), array_keys($this->readService->statusOptions())),
            'priority' => $this->normalizeFilter((string) $request->query('priority', ''), array_keys($this->readService->priorityOptions())),
            'category' => $this->normalizeFilter((string) $request->query('category', ''), array_keys($this->readService->categoryOptions())),
            'account_id' => trim((string) $request->query('account_id', '')),
            'shipment_scope' => $this->normalizeFilter((string) $request->query('shipment_scope', ''), array_keys($this->readService->shipmentScopeOptions())),
            'assignee_id' => trim((string) $request->query('assignee_id', '')),
        ];

        return view('pages.admin.tickets-index', [
            'tickets' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'statusOptions' => $this->readService->statusOptions(),
            'priorityOptions' => $this->readService->priorityOptions(),
            'categoryOptions' => $this->readService->categoryOptions(),
            'shipmentScopeOptions' => $this->readService->shipmentScopeOptions(),
            'accountFilterOptions' => $this->readService->accountFilterOptions($request->user()),
            'assigneeFilterOptions' => $this->readService->assigneeFilterOptions($request->user()),
            'assigneeFilterUnassigned' => InternalTicketReadService::ASSIGNEE_FILTER_UNASSIGNED,
            'canCreateTickets' => $this->canCreateTickets($request->user(), $controlPlane),
        ]);
    }

    public function show(
        Request $request,
        string $ticket,
        InternalControlPlane $controlPlane,
    ): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $ticket);
        abort_if(! is_array($detail), 404);

        return view('pages.admin.tickets-show', [
            'detail' => $detail,
            'canViewAccount' => $this->canViewAccount($request->user(), $controlPlane),
            'canViewShipment' => $this->canViewShipment($request->user(), $controlPlane),
            'canManageThread' => $this->canManageThread($request->user(), $controlPlane),
            'canManageTicketActions' => $this->canManageTicketActions($request->user(), $controlPlane),
            'manualStatusOptions' => $this->workflowService->manualStatusOptions(),
            'triagePriorityOptions' => $this->workflowService->priorityOptions(),
            'triageCategoryOptions' => $this->workflowService->categoryOptions(),
            'assignableUsers' => $this->workflowService->assignableUsers(),
        ]);
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizeFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function canViewAccount(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('accounts.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function canViewShipment(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('shipments.read')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_SHIPMENTS_DETAIL);
    }

    private function canCreateTickets(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('tickets.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_CREATE);
    }

    private function canManageThread(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('tickets.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_THREAD_ACTIONS);
    }

    private function canManageTicketActions(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('tickets.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_TICKETS_ACTIONS);
    }
}
