<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalFeatureFlagActionService;
use App\Services\InternalFeatureFlagReadService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalFeatureFlagCenterController extends Controller
{
    public function __construct(
        private readonly InternalFeatureFlagReadService $readService,
        private readonly InternalFeatureFlagActionService $actionService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'state' => $this->normalizeFilter((string) $request->query('state', ''), array_keys($this->readService->stateOptions())),
            'source' => $this->normalizeFilter((string) $request->query('source', ''), array_keys($this->readService->sourceOptions())),
        ];

        return view('pages.admin.feature-flags-index', [
            'flags' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'stateOptions' => $this->readService->stateOptions(),
            'sourceOptions' => $this->readService->sourceOptions(),
            'canManageFlags' => $this->canManageFlags($this->currentUser($request)),
        ]);
    }

    public function show(Request $request, string $featureFlag): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $featureFlag);
        abort_if(! is_array($detail), 404);

        return view('pages.admin.feature-flags-show', [
            'detail' => $detail,
            'canManageFlags' => $this->canManageFlags($this->currentUser($request)),
        ]);
    }

    public function toggle(Request $request, string $featureFlag): RedirectResponse
    {
        $payload = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $flag = $this->actionService->toggle(
                $featureFlag,
                $this->currentUser($request),
                (bool) $payload['is_enabled'],
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.feature-flags.show', $featureFlag)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.feature-flags.show', (string) $flag->id)
            ->with('success', sprintf(
                'Feature flag "%s" is now %s and the change was recorded in the internal audit trail.',
                (string) $flag->name,
                $flag->is_enabled ? 'enabled' : 'disabled'
            ));
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizeFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function canManageFlags(User $user): bool
    {
        return $user->hasPermission('feature_flags.manage')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_FEATURE_FLAGS_ACTIONS);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
