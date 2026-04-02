<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Models\WalletHold;
use App\Services\InternalBillingActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalBillingActionsController extends Controller
{
    public function __construct(
        private readonly InternalBillingActionService $billingActionService,
    ) {}

    public function releaseStaleHold(Request $request, string $account, string $hold): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $accountModel = Account::query()->withoutGlobalScopes()->findOrFail($account);
        $holdModel = WalletHold::query()
            ->where('account_id', (string) $accountModel->id)
            ->findOrFail($hold);

        try {
            $this->billingActionService->releaseStaleHold(
                $accountModel,
                $holdModel,
                $this->currentUser($request),
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.billing.preflights.show', ['account' => $accountModel, 'hold' => $holdModel])
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.billing.preflights.show', ['account' => $accountModel, 'hold' => $holdModel])
            ->with('success', 'Stale reservation released and billing audit recorded.');
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
