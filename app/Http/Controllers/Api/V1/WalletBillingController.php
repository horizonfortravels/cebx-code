<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Wallet;
use App\Services\WalletBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletBillingController extends Controller
{
    public function __construct(
        protected WalletBillingService $service
    ) {}

    public function wallet(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('view', $this->policyWallet($accountId));

        $wallet = $this->service->getWallet($accountId, $request->user());

        return response()->json(['success' => true, 'data' => $wallet]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('viewLedger', $this->policyWallet($accountId));

        $filters = $request->only(['type', 'from', 'to', 'limit']);

        $data = $this->service->getLedger($accountId, $request->user(), $filters);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function topUp(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('topup', $this->policyWallet($accountId));

        $request->validate([
            'amount' => 'required|numeric|min:1|max:999999',
            'reference_id' => 'required|string|max:100',
            'description' => 'sometimes|string|max:500',
        ]);

        $entry = $this->service->recordTopUp(
            $accountId,
            (float) $request->amount,
            $request->reference_id,
            $request->user(),
            $request->description
        );

        return response()->json([
            'success' => true,
            'message' => 'Wallet topped up successfully.',
            'data' => [
                'entry_id' => $entry->id,
                'amount' => $entry->amount,
                'running_balance' => $entry->running_balance,
            ],
        ], 201);
    }

    public function configureThreshold(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('configure', $this->policyWallet($accountId));

        $request->validate([
            'threshold' => 'nullable|numeric|min:0|max:999999',
        ]);

        $wallet = $this->service->configureThreshold(
            $accountId,
            $request->threshold !== null ? (float) $request->threshold : null,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Wallet threshold updated.',
            'data' => $wallet,
        ]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('viewPaymentMethods', $this->policyWallet($accountId));

        $methods = $this->service->listPaymentMethods($accountId, $request->user());

        return response()->json([
            'success' => true,
            'data' => $methods,
            'meta' => ['count' => count($methods)],
        ]);
    }

    public function addPaymentMethod(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('managePaymentMethods', $this->policyWallet($accountId));

        $payload = $request->validate([
            'type' => 'sometimes|in:card,bank_transfer,wallet_gateway',
            'label' => 'sometimes|string|max:100',
            'provider' => 'sometimes|string|max:50',
            'last_four' => 'sometimes|string|size:4',
            'expiry_month' => 'sometimes|string|size:2',
            'expiry_year' => 'sometimes|string|size:4',
            'cardholder_name' => 'sometimes|string|max:150',
            'gateway_token' => 'sometimes|string|max:500',
            'gateway_customer_id' => 'sometimes|string|max:255',
        ]);

        $method = $this->service->addPaymentMethod(
            $accountId,
            $payload,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'Payment method added.',
            'data' => $method->toSafeArray(),
        ], 201);
    }

    public function removePaymentMethod(Request $request, string $id): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $method = PaymentMethod::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('managePaymentMethods', $this->policyWallet((string) $method->account_id));

        $this->service->removePaymentMethod($accountId, $id, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Payment method removed.',
        ]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => WalletBillingService::walletPermissions(),
        ]);
    }

    private function currentAccountId(): string
    {
        $accountId = app()->bound('current_account_id')
            ? trim((string) app('current_account_id'))
            : '';

        if ($accountId !== '') {
            return $accountId;
        }

        return trim((string) request()->user()?->account_id);
    }

    private function policyWallet(string $accountId): Wallet
    {
        $wallet = new Wallet();
        $wallet->forceFill(['account_id' => $accountId]);

        return $wallet;
    }
}
