<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountSettingsRequest;
use App\Services\AccountSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AccountSettingsController — FR-IAM-008
 *
 * Endpoints:
 * - GET  /settings         → Get all settings
 * - PUT  /settings         → Update settings (partial)
 * - POST /settings/reset   → Reset to defaults
 * - GET  /settings/options → Supported options (languages, currencies, etc.)
 */
class AccountSettingsController extends Controller
{
    public function __construct(
        protected AccountSettingsService $service
    ) {}

    /**
     * GET /api/v1/account/settings
     */
    public function show(Request $request): JsonResponse
    {
        $settings = $this->service->getSettings($request->user()->account_id);

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * PUT /api/v1/account/settings
     * Partial update — only changed fields required.
     */
    public function update(UpdateAccountSettingsRequest $request): JsonResponse
    {
        $settings = $this->service->updateSettings(
            $request->user()->account_id,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث إعدادات الحساب بنجاح.',
            'data'    => $settings,
        ]);
    }

    /**
     * POST /api/v1/account/settings/reset
     * Reset all settings to default values.
     */
    public function reset(Request $request): JsonResponse
    {
        $settings = $this->service->resetToDefaults(
            $request->user()->account_id,
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة ضبط الإعدادات إلى القيم الافتراضية.',
            'data'    => $settings,
        ]);
    }

    /**
     * GET /api/v1/account/settings/options
     * Returns all supported values for dropdowns (languages, currencies, etc.)
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $this->service->getSupportedOptions(),
        ]);
    }
}
