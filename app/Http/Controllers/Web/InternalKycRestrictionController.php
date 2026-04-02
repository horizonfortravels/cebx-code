<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Services\InternalKycRestrictionAdminService;
use App\Support\Kyc\AccountKycStatusMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalKycRestrictionController extends Controller
{
    public function __construct(
        private readonly InternalKycRestrictionAdminService $restrictionAdminService,
    ) {}

    public function sync(Request $request, string $account, string $feature): RedirectResponse
    {
        $payload = $request->validate([
            'mode' => ['required', Rule::in(['enable', 'disable', 'set', 'clear'])],
            'quota_value' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $accountModel = Account::query()->withoutGlobalScopes()->findOrFail($account);
        $status = $accountModel->kycVerification?->status
            ?? AccountKycStatusMapper::toVerificationStatus((string) ($accountModel->kyc_status ?? ''));

        try {
            $this->restrictionAdminService->syncRestriction(
                (string) $accountModel->id,
                (string) $status,
                $feature,
                $payload,
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['kyc' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.kyc.show', $accountModel)
            ->with('success', $this->successMessage($feature, (string) $payload['mode']));
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function successMessage(string $feature, string $mode): string
    {
        $label = match ($feature) {
            InternalKycRestrictionAdminService::FEATURE_INTERNATIONAL_SHIPPING => 'تعليق الشحن الدولي',
            InternalKycRestrictionAdminService::FEATURE_SHIPPING_LIMIT => 'حد الشحن الكلي',
            InternalKycRestrictionAdminService::FEATURE_DAILY_SHIPMENT_LIMIT => 'حد الشحن اليومي',
            default => 'قيد التحقق',
        };

        return in_array($mode, ['disable', 'clear'], true)
            ? 'تم تعطيل ' . $label . ' وتسجيل التغيير في السجل التدقيقي.'
            : 'تم تحديث ' . $label . ' وتسجيل التغيير في السجل التدقيقي.';
    }
}
