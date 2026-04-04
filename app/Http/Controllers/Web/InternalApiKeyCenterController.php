<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalApiKeyActionService;
use App\Services\InternalApiKeyReadService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InternalApiKeyCenterController extends Controller
{
    public function __construct(
        private readonly InternalApiKeyReadService $readService,
        private readonly InternalApiKeyActionService $actionService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'state' => $this->normalizeFilter((string) $request->query('state', ''), array_keys($this->readService->stateOptions())),
            'scope' => $this->normalizeFilter((string) $request->query('scope', ''), array_keys($this->readService->scopeOptions())),
            'account' => trim((string) $request->query('account', '')),
        ];

        return view('pages.admin.api-keys-index', [
            'keys' => $this->readService->paginate($request->user(), $filters),
            'stats' => $this->readService->stats($request->user()),
            'filters' => $filters,
            'stateOptions' => $this->readService->stateOptions(),
            'scopeOptions' => $this->readService->scopeOptions(),
            'accountOptions' => $this->readService->accountOptions(),
            'canManageKeys' => $this->canManageKeys($this->currentUser($request)),
        ]);
    }

    public function show(Request $request, string $apiKey): View
    {
        $detail = $this->readService->findVisibleDetail($request->user(), $apiKey);
        abort_if(! is_array($detail), 404);

        $user = $this->currentUser($request);
        $plaintextKey = session('internal_api_key_plaintext_for') === $detail['id']
            ? session('internal_api_key_plaintext')
            : null;

        return view('pages.admin.api-keys-show', [
            'detail' => $detail,
            'plaintextKey' => is_string($plaintextKey) ? $plaintextKey : null,
            'canManageKeys' => $this->canManageKeys($user),
            'canViewAccount' => $this->canViewAccount($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'account_id' => ['required', 'string', 'exists:accounts,id'],
            'name' => ['required', 'string', 'max:200'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'in:' . implode(',', array_keys($this->actionService->scopeOptions()))],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $created = $this->actionService->createKey(
                (string) $payload['account_id'],
                $this->currentUser($request),
                (string) $payload['name'],
                (array) $payload['scopes'],
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.api-keys.index')
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.api-keys.show', (string) $created['api_key']->id)
            ->with('success', 'Internal API key created. Store the plaintext secret now because it will not be shown again.')
            ->with('internal_api_key_plaintext', (string) $created['raw_key'])
            ->with('internal_api_key_plaintext_for', (string) $created['api_key']->id);
    }

    public function rotate(Request $request, string $apiKey): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $rotated = $this->actionService->rotateKey(
                $apiKey,
                $this->currentUser($request),
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.api-keys.show', $apiKey)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.api-keys.show', (string) $rotated['api_key']->id)
            ->with('success', 'API key rotated. Store the new plaintext secret now because it will not be shown again.')
            ->with('internal_api_key_plaintext', (string) $rotated['raw_key'])
            ->with('internal_api_key_plaintext_for', (string) $rotated['api_key']->id);
    }

    public function revoke(Request $request, string $apiKey): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $key = $this->actionService->revokeKey(
                $apiKey,
                $this->currentUser($request),
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.api-keys.show', $apiKey)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.api-keys.show', (string) $key->id)
            ->with('success', 'API key revoked and internal audit recorded.');
    }

    /**
     * @param array<int, string> $allowed
     */
    private function normalizeFilter(string $value, array $allowed): string
    {
        $value = strtolower(trim($value));

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function canManageKeys(User $user): bool
    {
        return $user->hasPermission('api_keys.manage')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_INTERNAL_API_KEYS_ACTIONS);
    }

    private function canViewAccount(User $user): bool
    {
        return $user->hasPermission('accounts.read')
            && $this->controlPlane->canSeeSurface($user, InternalControlPlane::SURFACE_EXTERNAL_ACCOUNTS_DETAIL);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
