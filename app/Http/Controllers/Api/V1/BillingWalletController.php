<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BillingWallet;
use App\Models\Wallet;
use App\Models\WalletHold;
use App\Models\WalletTopup;
use App\Services\BillingWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWalletController extends Controller
{
    public function __construct(private BillingWalletService $service) {}

    public function create(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('manageBilling', $this->policyWallet($accountId));

        $data = $request->validate([
            'currency' => 'nullable|string|size:3',
            'organization_id' => 'nullable|uuid',
        ]);

        $wallet = $this->service->createWallet(
            $accountId,
            $data['currency'] ?? 'SAR',
            $data['organization_id'] ?? null
        );

        return response()->json(['status' => 'success', 'data' => $wallet], 201);
    }

    public function show(string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('view', $this->policyWallet((string) $wallet->account_id));

        return response()->json(['status' => 'success', 'data' => $this->service->getWallet((string) $wallet->id)]);
    }

    public function myWallet(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('view', $this->policyWallet($accountId));

        $wallet = $this->service->getWalletForAccount($accountId);
        if (!$wallet) {
            return response()->json(['status' => 'error', 'message' => 'ERR_WALLET_NOT_FOUND'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $wallet]);
    }

    public function initiateTopup(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('topup', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_gateway' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:200',
        ]);

        $topup = $this->service->initiateTopup(
            (string) $wallet->id,
            (float) $data['amount'],
            array_merge($data, ['initiated_by' => $request->user()->id])
        );

        return response()->json(['status' => 'success', 'data' => $topup], 201);
    }

    public function confirmTopup(Request $request, string $topupId): JsonResponse
    {
        $topup = $this->findTopupForCurrentTenant($topupId);

        $this->authorize('manageBilling', $this->policyWallet((string) $topup->account_id));

        $data = $request->validate(['payment_reference' => 'required|string', 'metadata' => 'nullable|array']);

        $confirmedTopup = $this->service->confirmTopup((string) $topup->id, $data['payment_reference'], $data['metadata'] ?? null);

        return response()->json(['status' => 'success', 'data' => $confirmedTopup]);
    }

    public function failTopup(Request $request, string $topupId): JsonResponse
    {
        $topup = $this->findTopupForCurrentTenant($topupId);

        $this->authorize('manageBilling', $this->policyWallet((string) $topup->account_id));

        $data = $request->validate(['reason' => 'required|string']);

        return response()->json(['status' => 'success', 'data' => $this->service->failTopup((string) $topup->id, $data['reason'])]);
    }

    public function balance(string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('view', $this->policyWallet((string) $wallet->account_id));

        return response()->json(['status' => 'success', 'data' => $this->service->getBalance((string) $wallet->id)]);
    }

    public function statement(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('viewLedger', $this->policyWallet((string) $wallet->account_id));

        $filters = $request->only(['from', 'to', 'type', 'direction']);

        return response()->json(['status' => 'success', 'data' => $this->service->getStatement((string) $wallet->id, $filters)]);
    }

    public function refund(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('manageBilling', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate([
            'shipment_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
        ]);

        $refund = $this->service->processRefund(
            (string) $wallet->id,
            $data['shipment_id'],
            (float) $data['amount'],
            $data['reason'],
            [
                'initiated_by_type' => 'user',
                'initiated_by_id' => $request->user()->id,
            ]
        );

        return response()->json(['status' => 'success', 'data' => $refund]);
    }

    public function createHold(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('manageBilling', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate([
            'shipment_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $hold = $this->service->createHold((string) $wallet->id, $data['shipment_id'], (float) $data['amount']);

        return response()->json(['status' => 'success', 'data' => $hold], 201);
    }

    public function captureHold(string $holdId): JsonResponse
    {
        $hold = $this->findHoldForCurrentTenant($holdId);

        $this->authorize('manageBilling', $this->policyWallet((string) $hold->wallet->account_id));

        return response()->json(['status' => 'success', 'data' => $this->service->captureHold((string) $hold->id)]);
    }

    public function releaseHold(string $holdId): JsonResponse
    {
        $hold = $this->findHoldForCurrentTenant($holdId);

        $this->authorize('manageBilling', $this->policyWallet((string) $hold->wallet->account_id));

        return response()->json(['status' => 'success', 'data' => $this->service->releaseHold((string) $hold->id)]);
    }

    public function charge(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('manageBilling', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate([
            'shipment_id' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $entry = $this->service->chargeForShipment((string) $wallet->id, $data['shipment_id'], (float) $data['amount'], $request->user()->id);

        return response()->json(['status' => 'success', 'data' => $entry]);
    }

    public function setThreshold(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('configure', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate(['threshold' => 'required|numeric|min:0']);

        return response()->json(['status' => 'success', 'data' => $this->service->setThreshold((string) $wallet->id, (float) $data['threshold'])]);
    }

    public function configureAutoTopup(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('configure', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate([
            'enabled' => 'required|boolean',
            'amount' => 'nullable|numeric|min:1',
            'trigger' => 'nullable|numeric|min:0',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $this->service->configureAutoTopup(
                (string) $wallet->id,
                (bool) $data['enabled'],
                isset($data['amount']) ? (float) $data['amount'] : null,
                isset($data['trigger']) ? (float) $data['trigger'] : null
            ),
        ]);
    }

    public function summary(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('view', $this->policyWallet((string) $wallet->account_id));

        $showDetails = $request->boolean('details', false);

        return response()->json(['status' => 'success', 'data' => $this->service->getWalletSummary((string) $wallet->id, $showDetails)]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('manageBilling', $this->policyWallet($accountId));

        $data = $request->validate(['date' => 'required|date', 'gateway' => 'required|string']);

        return response()->json(['status' => 'success', 'data' => $this->service->runReconciliation($data['date'], $data['gateway'])]);
    }

    public function reconciliationReports(): JsonResponse
    {
        $accountId = $this->currentAccountId();

        $this->authorize('manageBilling', $this->policyWallet($accountId));

        return response()->json(['status' => 'success', 'data' => $this->service->listReconciliationReports()]);
    }

    public function reversal(Request $request, string $walletId): JsonResponse
    {
        $wallet = $this->findBillingWalletForCurrentTenant($walletId);

        $this->authorize('manageBilling', $this->policyWallet((string) $wallet->account_id));

        $data = $request->validate(['entry_id' => 'required|string', 'reason' => 'required|string']);

        $entry = $this->service->createReversal((string) $wallet->id, $data['entry_id'], $data['reason'], $request->user()->id);

        return response()->json(['status' => 'success', 'data' => $entry]);
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

    private function findBillingWalletForCurrentTenant(string $walletId): BillingWallet
    {
        return BillingWallet::withoutGlobalScopes()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $walletId)
            ->firstOrFail();
    }

    private function findTopupForCurrentTenant(string $topupId): WalletTopup
    {
        return WalletTopup::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $topupId)
            ->firstOrFail();
    }

    private function findHoldForCurrentTenant(string $holdId): WalletHold
    {
        $accountId = $this->currentAccountId();

        return WalletHold::query()
            ->where('id', $holdId)
            ->whereHas('wallet', function ($query) use ($accountId): void {
                $query->withoutGlobalScopes()->where('account_id', $accountId);
            })
            ->with(['wallet' => function ($query): void {
                $query->withoutGlobalScopes();
            }])
            ->firstOrFail();
    }
}
