<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\InternalWebhookActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalWebhookActionsController extends Controller
{
    public function __construct(
        private readonly InternalWebhookActionService $actionService,
    ) {}

    public function retry(Request $request, string $endpoint, string $event): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->actionService->retryStoreEvent(
                $endpoint,
                $event,
                $this->currentUser($request),
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.webhooks.show', $endpoint)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.webhooks.show', $endpoint)
            ->with('success', 'Webhook delivery retried safely and the internal audit trail was recorded.');
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
