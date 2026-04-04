<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalIntegrationReadService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalIntegrationReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalIntegrationReadService $readService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $this->normalizeFilter((string) $request->query('type', ''), array_keys($this->readService->typeOptions())),
            'state' => $this->normalizeFilter((string) $request->query('state', ''), array_keys($this->readService->stateOptions())),
            'health' => $this->normalizeFilter((string) $request->query('health', ''), array_keys($this->readService->healthOptions())),
        ];

        return view('pages.admin.integrations-index', [
            'integrations' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'typeOptions' => $this->readService->typeOptions(),
            'stateOptions' => $this->readService->stateOptions(),
            'healthOptions' => $this->readService->healthOptions(),
        ]);
    }

    public function show(Request $request, string $integration, InternalControlPlane $controlPlane): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $integration);
        abort_if(! is_array($detail), 404);

        $user = $request->user();
        $canViewAccount = $this->canViewAccount($user, $controlPlane);
        $roleName = $controlPlane->primaryCanonicalRole($user);

        return view('pages.admin.integrations-show', [
            'detail' => $detail,
            'canViewAccount' => $canViewAccount,
            'canViewCredentials' => $roleName !== InternalControlPlane::ROLE_CARRIER_MANAGER,
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
}
