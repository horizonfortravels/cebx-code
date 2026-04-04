<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalWebhookReadService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalWebhookReadCenterController extends Controller
{
    public function __construct(
        private readonly InternalWebhookReadService $readService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => $this->normalizeFilter((string) $request->query('type', ''), array_keys($this->readService->typeOptions())),
            'state' => $this->normalizeFilter((string) $request->query('state', ''), array_keys($this->readService->stateOptions())),
        ];

        return view('pages.admin.webhooks-index', [
            'endpoints' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'typeOptions' => $this->readService->typeOptions(),
            'stateOptions' => $this->readService->stateOptions(),
        ]);
    }

    public function show(Request $request, string $endpoint): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $endpoint);
        abort_if(! is_array($detail), 404);

        $user = $this->currentUser($request);
        $canRetryEvents = $user->hasPermission('webhooks.manage')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_WEBHOOKS_ACTIONS);

        return view('pages.admin.webhooks-show', [
            'detail' => $detail,
            'canRetryEvents' => $canRetryEvents,
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

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
