<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrganizationProfileRequest;
use App\Http\Resources\OrganizationProfileResource;
use App\Services\AccountTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTypeController extends Controller
{
    public function __construct(
        private readonly AccountTypeService $service
    ) {}

    /**
     * GET /api/v1/account/type
     * Get account type info including org profile and KYC status.
     */
    public function show(Request $request): JsonResponse
    {
        $info = $this->service->getAccountTypeInfo($request->user()->account_id);

        return response()->json([
            'success' => true,
            'data'    => $info,
        ]);
    }

    /**
     * GET /api/v1/account/organization
     * Get organization profile details.
     */
    public function organizationProfile(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if (!$account->isOrganization()) {
            return response()->json([
                'success'    => false,
                'error_code' => 'ERR_NOT_ORGANIZATION',
                'message'    => 'هذا الحساب ليس حساب منظمة.',
            ], 422);
        }

        $profile = $account->organizationProfile;

        if (!$profile) {
            return response()->json([
                'success'    => false,
                'error_code' => 'ERR_PROFILE_NOT_FOUND',
                'message'    => 'ملف المنظمة غير موجود.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new OrganizationProfileResource($profile),
        ]);
    }

    /**
     * PUT /api/v1/account/organization
     * Update organization profile.
     */
    public function updateOrganizationProfile(UpdateOrganizationProfileRequest $request): JsonResponse
    {
        $profile = $this->service->updateOrganizationProfile(
            $request->user()->account_id,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث ملف المنظمة بنجاح.',
            'data'    => new OrganizationProfileResource($profile),
        ]);
    }

    /**
     * POST /api/v1/account/type-change
     * Request to change account type (only if no active usage).
     */
    public function requestTypeChange(Request $request): JsonResponse
    {
        $request->validate([
            'new_type' => ['required', 'in:individual,organization'],
        ]);

        $account = $this->service->requestTypeChange(
            $request->user()->account_id,
            $request->input('new_type'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير نوع الحساب بنجاح.',
            'data'    => [
                'type'       => $account->type,
                'kyc_status' => $account->kyc_status,
            ],
        ]);
    }

    /**
     * POST /api/v1/account/kyc/submit
     * Submit KYC documents for verification.
     */
    public function submitKyc(Request $request): JsonResponse
    {
        $request->validate([
            'documents'   => ['required', 'array', 'min:1'],
            'documents.*' => ['string', 'max:500'],
        ]);

        $kyc = $this->service->submitKycDocuments(
            $request->user()->account_id,
            $request->input('documents'),
            $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الوثائق للمراجعة.',
            'data'    => [
                'status'       => $kyc->status,
                'submitted_at' => $kyc->submitted_at?->toISOString(),
            ],
        ]);
    }

    /**
     * GET /api/v1/account/kyc
     * Get current KYC status and details.
     */
    public function kycStatus(Request $request): JsonResponse
    {
        $account = $request->user()->account;
        $kyc = $account->kycVerification;

        if (!$kyc) {
            return response()->json([
                'success' => true,
                'data'    => [
                    'status'             => 'unverified',
                    'verification_type'  => $account->type,
                    'required_documents' => \App\Models\KycVerification::requiredDocumentsFor($account->type),
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'status'              => $kyc->status,
                'verification_type'   => $kyc->verification_type,
                'required_documents'  => $kyc->required_documents,
                'submitted_documents' => $kyc->submitted_documents,
                'rejection_reason'    => $kyc->rejection_reason,
                'submitted_at'        => $kyc->submitted_at?->toISOString(),
                'reviewed_at'         => $kyc->reviewed_at?->toISOString(),
            ],
        ]);
    }
}
