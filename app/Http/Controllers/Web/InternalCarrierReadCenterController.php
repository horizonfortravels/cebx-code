<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CarrierSettingsService;
use App\Services\InternalCarrierIntegrationReadService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalCarrierReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalCarrierIntegrationReadService $readService,
        private readonly CarrierSettingsService $carrierSettingsService,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'state' => $this->normalizeFilter((string) $request->query('state', ''), array_keys($this->readService->stateOptions())),
            'health' => $this->normalizeFilter((string) $request->query('health', ''), array_keys($this->readService->healthOptions())),
        ];

        return view('pages.admin.carriers-index', [
            'carriers' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'stateOptions' => $this->readService->stateOptions(),
            'healthOptions' => $this->readService->healthOptions(),
        ]);
    }

    public function show(Request $request, string $carrier, InternalControlPlane $controlPlane): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $carrier);
        abort_if(! is_array($detail), 404);

        return view('pages.admin.carriers-show', [
            'detail' => $detail,
            'canViewCredentials' => $this->canViewCredentials($request->user(), $controlPlane),
            'canManageCarriers' => $this->canManageCarriers($request->user(), $controlPlane),
            'credentialFields' => $this->carrierSettingsService->editableFieldsForView($carrier),
            'rotationFields' => $this->carrierSettingsService->rotationFieldsForView($carrier),
            'supportsRotation' => $this->carrierSettingsService->supportsRotation($carrier),
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

    private function canViewCredentials(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_CARRIERS_DETAIL);
    }

    private function canManageCarriers(?User $user, InternalControlPlane $controlPlane): bool
    {
        return $user instanceof User
            && $user->hasPermission('integrations.manage')
            && $controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_CARRIERS_ACTIONS);
    }
}
