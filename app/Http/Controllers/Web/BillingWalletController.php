<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BillingWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BillingWalletController — FR-BW-001→010
 */
class BillingWalletController extends Controller
{
    public function __construct(private BillingWalletService $service) {}

    /**
     * Verify the wallet belongs to the authenticated user's account.
     */
    private function verifyWalletOwnership(string $walletId, Request $request): void
    {
        $wallet = \App\Models\BillingWallet::where('id', $walletId)
            ->where('account_id', $request->user()->account_id)
            ->firstOrFail();
    }

    // ═══════════ FR-BW-001: Wallet CRUD ═══════════

    public function create(Request $request): JsonResponse
    {
        $data = $request->validate(['currency' => 'nullable|string|size:3', 'organization_id' => 'nullable|uuid']);
        $wallet = $this->service->createWallet($request->user()->account_id, $data['currency'] ?? 'SAR', $data['organization_id'] ?? null);
        return response()->json(['status' => 'success', 'data' => $wallet], 201);
    }

    public function show(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        return response()->json(['status' => 'success', 'data' => $this->service->getWallet($walletId)]);
    }

    public function myWallet(Request $request): JsonResponse
    {
        $wallet = $this->service->getWalletForAccount($request->user()->account_id);
        if (!$wallet) return response()->json(['status' => 'error', 'message' => 'ERR_WALLET_NOT_FOUND'], 404);
        return response()->json(['status' => 'success', 'data' => $wallet]);
    }

    // ═══════════ FR-BW-002: Initiate Top-up ═══════════

    public function initiateTopup(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate([
            'amount'          => 'required|numeric|min:1',
            'payment_gateway' => 'nullable|string',
            'payment_method'  => 'nullable|string',
            'idempotency_key' => 'nullable|string|max:200',
        ]);
        $topup = $this->service->initiateTopup($walletId, $data['amount'], array_merge($data, ['initiated_by' => $request->user()->id]));
        return response()->json(['status' => 'success', 'data' => $topup], 201);
    }

    // ═══════════ FR-BW-003: Confirm/Fail Top-up ═══════════

    public function confirmTopup(Request $request, string $topupId): JsonResponse
    {
        $data = $request->validate(['payment_reference' => 'required|string', 'metadata' => 'nullable|array']);
        $topup = $this->service->confirmTopup($topupId, $data['payment_reference'], $data['metadata'] ?? null);
        return response()->json(['status' => 'success', 'data' => $topup]);
    }

    public function failTopup(Request $request, string $topupId): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string']);
        return response()->json(['status' => 'success', 'data' => $this->service->failTopup($topupId, $data['reason'])]);
    }

    // ═══════════ FR-BW-005: Balance & Statement ═══════════

    public function balance(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        return response()->json(['status' => 'success', 'data' => $this->service->getBalance($walletId)]);
    }

    public function statement(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $filters = $request->only(['from', 'to', 'type', 'direction']);
        return response()->json(['status' => 'success', 'data' => $this->service->getStatement($walletId, $filters)]);
    }

    // ═══════════ FR-BW-006: Refund ═══════════

    public function refund(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate([
            'shipment_id' => 'required|string', 'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string',
        ]);
        $refund = $this->service->processRefund($walletId, $data['shipment_id'], $data['amount'], $data['reason'], [
            'initiated_by_type' => 'user', 'initiated_by_id' => $request->user()->id,
        ]);
        return response()->json(['status' => 'success', 'data' => $refund]);
    }

    // ═══════════ FR-BW-007: Hold/Capture/Release ═══════════

    public function createHold(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate(['shipment_id' => 'required|string', 'amount' => 'required|numeric|min:0.01']);
        $hold = $this->service->createHold($walletId, $data['shipment_id'], $data['amount']);
        return response()->json(['status' => 'success', 'data' => $hold], 201);
    }

    public function captureHold(string $holdId): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->captureHold($holdId)]);
    }

    public function releaseHold(string $holdId): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->releaseHold($holdId)]);
    }

    // ═══════════ FR-BW-007: Direct Charge ═══════════

    public function charge(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate(['shipment_id' => 'required|string', 'amount' => 'required|numeric|min:0.01']);
        $entry = $this->service->chargeForShipment($walletId, $data['shipment_id'], $data['amount'], $request->user()->id);
        return response()->json(['status' => 'success', 'data' => $entry]);
    }

    // ═══════════ FR-BW-008: Threshold ═══════════

    public function setThreshold(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate(['threshold' => 'required|numeric|min:0']);
        return response()->json(['status' => 'success', 'data' => $this->service->setThreshold($walletId, $data['threshold'])]);
    }

    public function configureAutoTopup(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate([
            'enabled' => 'required|boolean', 'amount' => 'nullable|numeric|min:1', 'trigger' => 'nullable|numeric|min:0',
        ]);
        return response()->json(['status' => 'success', 'data' => $this->service->configureAutoTopup(
            $walletId, $data['enabled'], $data['amount'] ?? null, $data['trigger'] ?? null
        )]);
    }

    // ═══════════ FR-BW-009: Summary ═══════════

    public function summary(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $showDetails = $request->boolean('details', false);
        return response()->json(['status' => 'success', 'data' => $this->service->getWalletSummary($walletId, $showDetails)]);
    }

    // ═══════════ FR-BW-010: Reconciliation ═══════════

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => 'required|date', 'gateway' => 'required|string']);
        return response()->json(['status' => 'success', 'data' => $this->service->runReconciliation($data['date'], $data['gateway'])]);
    }

    public function reconciliationReports(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->listReconciliationReports()]);
    }

    // ═══════════ FR-BW-004: Reversal ═══════════

    public function reversal(Request $request, string $walletId): JsonResponse
    {
        $this->verifyWalletOwnership($walletId, $request);
        $data = $request->validate(['entry_id' => 'required|string', 'reason' => 'required|string']);
        $entry = $this->service->createReversal($walletId, $data['entry_id'], $data['reason'], $request->user()->id);
        return response()->json(['status' => 'success', 'data' => $entry]);
    }
}
