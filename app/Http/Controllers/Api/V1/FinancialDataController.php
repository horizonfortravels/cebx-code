<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DataMaskingService;
use App\Services\AuditService;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FinancialDataController
 *
 * FR-IAM-012: Financial data masking & field-level authorization
 *
 * Provides endpoints for:
 * - Checking what the current user can see (visibility map)
 * - Masking card data on demand
 * - Accessing financial data with automatic field filtering
 */
class FinancialDataController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * GET /api/v1/financial/visibility
     *
     * Returns the current user's financial data visibility map.
     * Used by frontend to know which columns/fields to render.
     */
    public function visibility(Request $request): JsonResponse
    {
        $user = $request->user();

        $map = DataMaskingService::visibilityMap($user);

        return response()->json([
            'success' => true,
            'data'    => $map,
        ]);
    }

    /**
     * POST /api/v1/financial/mask-card
     *
     * Utility endpoint to mask a card number.
     * Logs the access attempt.
     */
    public function maskCard(Request $request): JsonResponse
    {
        $request->validate([
            'card_number' => 'required|string|min:8|max:25',
        ]);

        $masked = DataMaskingService::maskCardNumber($request->card_number);
        $last4  = DataMaskingService::lastFourDigits($request->card_number);

        // Audit log
        $this->auditService->info(
            $request->user()->account_id,
            $request->user()->id,
            'financial.card_masked',
            AuditLog::CATEGORY_FINANCIAL,
            null, null, null, null,
            ['last4' => $last4]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'masked'     => $masked,
                'last4'      => $last4,
            ],
        ]);
    }

    /**
     * GET /api/v1/financial/sensitive-fields
     *
     * Returns the list of all sensitive financial fields and their required permissions.
     * Helpful for frontend developers to know which fields need masking treatment.
     */
    public function sensitiveFields(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'profit_sensitive' => [
                    'permission' => 'financial.profit.view',
                    'fields'     => DataMaskingService::profitSensitiveFields(),
                ],
                'general_financial' => [
                    'permission' => 'financial.view',
                    'fields'     => DataMaskingService::generalFinancialFields(),
                ],
                'card_sensitive' => [
                    'permission' => 'financial.cards.view',
                    'fields'     => DataMaskingService::cardSensitiveFields(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/v1/financial/filter
     *
     * Demonstration/utility endpoint: filters a data payload based on user permissions.
     * In production, each module calls DataMaskingService::filterFinancialData() internally.
     */
    public function filterData(Request $request): JsonResponse
    {
        $request->validate([
            'data'   => 'required|array',
            'data.*' => 'nullable',
        ]);

        $user = $request->user();
        $filtered = DataMaskingService::filterFinancialData($request->data, $user);

        // Log financial data access
        $this->auditService->info(
            $user->account_id,
            $user->id,
            'financial.view_attempted',
            AuditLog::CATEGORY_FINANCIAL,
            null, null, null, null,
            [
                'fields_requested' => array_keys($request->data),
                'fields_masked'    => DataMaskingService::getMaskedFieldsForUser($user),
            ]
        );

        return response()->json([
            'success' => true,
            'data'    => $filtered,
            'meta'    => [
                'masked_fields'  => DataMaskingService::getMaskedFieldsForUser($user),
                'visible_fields' => DataMaskingService::getVisibleFieldsForUser($user),
            ],
        ]);
    }
}
