<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PaymentController — FR-PAY-001→011
 *
 * POST   /payments/topup               — FR-PAY-001/004: Wallet top-up
 * POST   /payments/charge-shipping      — FR-PAY-001/002: Charge shipment
 * GET    /payments/wallet               — FR-PAY-008: Wallet summary
 * GET    /payments/transactions          — FR-PAY-008: Transaction log
 * POST   /payments/refund               — FR-PAY-010: Refund
 * POST   /subscriptions/subscribe       — FR-PAY-003: Subscribe
 * POST   /subscriptions/cancel          — FR-PAY-003: Cancel
 * POST   /subscriptions/renew           — FR-PAY-003: Renew
 * GET    /subscriptions/status          — FR-PAY-009: Status
 * GET    /subscriptions/plans           — FR-PAY-003: List plans
 * GET    /payments/invoices             — FR-PAY-005: List invoices
 * GET    /payments/invoices/{id}        — FR-PAY-005: Get invoice
 * POST   /payments/promo/validate       — FR-PAY-007: Validate promo
 * POST   /payments/promo                — FR-PAY-007: Create promo
 * GET    /payments/gateways             — FR-PAY-004: List gateways
 * POST   /payments/balance-alerts       — FR-PAY-011: Set alert
 * GET    /payments/balance-alerts       — FR-PAY-011: List alerts
 * GET    /payments/tax-calculate        — FR-PAY-006: Tax calculation
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentService $service) {}

    // ═══════════════ FR-PAY-001/004: Wallet Top-up ═══════════

    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount'          => 'required|numeric|min:1',
            'gateway'         => 'required|string',
            'payment_method'  => 'required|string',
            'idempotency_key' => 'required|string|max:200',
        ]);

        $transaction = $this->service->topUpWallet(
            $request->user()->account,
            $request->user(),
            $data['amount'],
            $data['gateway'],
            $data['payment_method'],
            $data['idempotency_key']
        );

        return response()->json(['status' => 'success', 'data' => $transaction], 201);
    }

    // ═══════════════ FR-PAY-001/002: Charge Shipping ═════════

    public function chargeShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shipment_id'     => 'required|string',
            'amount'          => 'required|numeric|min:0.01',
            'idempotency_key' => 'required|string|max:200',
            'promo_code'      => 'nullable|string',
        ]);

        $transaction = $this->service->chargeShipping(
            $request->user()->account,
            $request->user(),
            $data['shipment_id'],
            $data['amount'],
            $data['idempotency_key'],
            $data['promo_code'] ?? null
        );

        return response()->json(['status' => 'success', 'data' => $transaction], 201);
    }

    // ═══════════════ FR-PAY-008: Wallet ══════════════════════

    public function walletSummary(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getWalletSummary($request->user()->account),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'type'      => 'nullable|string',
            'status'    => 'nullable|string',
            'from'      => 'nullable|date',
            'to'        => 'nullable|date',
            'direction' => 'nullable|in:credit,debit',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        $data = $this->service->getTransactions(
            $request->user()->account,
            $request->only('type', 'status', 'from', 'to', 'direction'),
            $request->input('per_page', 20)
        );

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    // ═══════════════ FR-PAY-010: Refund ══════════════════════

    public function refund(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id'  => 'required|uuid',
            'amount'          => 'required|numeric|min:0.01',
            'reason'          => 'required|string|max:500',
            'idempotency_key' => 'required|string|max:200',
        ]);

        $refund = $this->service->refund(
            $data['transaction_id'],
            $request->user(),
            $data['amount'],
            $data['reason'],
            $data['idempotency_key']
        );

        return response()->json(['status' => 'success', 'data' => $refund], 201);
    }

    // ═══════════════ FR-PAY-003: Subscriptions ═══════════════

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id'         => 'required|uuid',
            'billing_cycle'   => 'required|in:monthly,yearly',
            'idempotency_key' => 'required|string|max:200',
            'promo_code'      => 'nullable|string',
        ]);

        $sub = $this->service->subscribe(
            $request->user()->account,
            $request->user(),
            $data['plan_id'],
            $data['billing_cycle'],
            $data['idempotency_key'],
            $data['promo_code'] ?? null
        );

        return response()->json(['status' => 'success', 'data' => $sub], 201);
    }

    public function cancelSubscription(Request $request): JsonResponse
    {
        $sub = $this->service->cancelSubscription($request->user()->account);
        return response()->json(['status' => 'success', 'data' => $sub]);
    }

    public function renewSubscription(Request $request): JsonResponse
    {
        $data = $request->validate(['idempotency_key' => 'required|string|max:200']);
        $sub = $this->service->renewSubscription($request->user()->account, $request->user(), $data['idempotency_key']);
        return response()->json(['status' => 'success', 'data' => $sub]);
    }

    public function subscriptionStatus(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getSubscriptionStatus($request->user()->account),
        ]);
    }

    public function listPlans(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->listPlans()]);
    }

    // ═══════════════ FR-PAY-005: Invoices ════════════════════

    public function listInvoices(Request $request): JsonResponse
    {
        $data = $this->service->listInvoices($request->user()->account);
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function getInvoice(string $invoiceId): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getInvoice($invoiceId),
        ]);
    }

    // ═══════════════ FR-PAY-007: Promo Codes ═════════════════

    public function validatePromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'    => 'required|string',
            'amount'  => 'required|numeric',
            'context' => 'nullable|in:shipping,subscription,both',
        ]);

        $result = $this->service->validatePromoCode(
            $data['code'],
            $request->user()->account_id,
            $data['amount'],
            $data['context'] ?? 'shipping'
        );

        return response()->json(['status' => 'success', 'data' => $result]);
    }

    public function createPromo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'                 => 'required|string|max:50|unique:promo_codes,code',
            'discount_type'        => 'required|in:percentage,fixed',
            'discount_value'       => 'required|numeric|min:0',
            'min_order_amount'     => 'nullable|numeric|min:0',
            'max_discount_amount'  => 'nullable|numeric|min:0',
            'applies_to'           => 'nullable|in:shipping,subscription,both',
            'max_total_uses'       => 'nullable|integer|min:1',
            'max_uses_per_account' => 'nullable|integer|min:1',
            'starts_at'            => 'nullable|date',
            'expires_at'           => 'nullable|date|after:starts_at',
        ]);

        $promo = $this->service->createPromoCode($data);
        return response()->json(['status' => 'success', 'data' => $promo], 201);
    }

    // ═══════════════ FR-PAY-004: Gateways ════════════════════

    public function listGateways(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $this->service->listGateways()]);
    }

    // ═══════════════ FR-PAY-011: Balance Alerts ══════════════

    public function setBalanceAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold_amount' => 'required|numeric|min:0',
            'channels'         => 'nullable|array',
        ]);

        $alert = $this->service->setBalanceAlert(
            $request->user()->account,
            $request->user(),
            $data['threshold_amount'],
            $data['channels'] ?? ['email', 'in_app']
        );

        return response()->json(['status' => 'success', 'data' => $alert], 201);
    }

    public function getBalanceAlerts(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->service->getBalanceAlerts($request->user()->account),
        ]);
    }

    // ═══════════════ FR-PAY-006: Tax Calculator ══════════════

    public function calculateTax(Request $request): JsonResponse
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0']);
        $tax = $this->service->calculateTax($data['amount']);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'amount'    => $data['amount'],
                'tax_rate'  => 15.00,
                'tax_amount' => $tax,
                'total'     => round($data['amount'] + $tax, 2),
            ],
        ]);
    }
}
