<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\KycVerification;
use App\Models\User;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InternalKycDecisionController extends Controller
{
    public function __construct(
        private readonly KycService $kycService,
    ) {}

    public function approve(Request $request, string $account): RedirectResponse
    {
        $payload = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
            'verification_level' => ['nullable', Rule::in([
                KycVerification::LEVEL_BASIC,
                KycVerification::LEVEL_ENHANCED,
                KycVerification::LEVEL_FULL,
            ])],
        ]);

        try {
            $this->kycService->approveKyc(
                $account,
                $this->currentUser($request),
                $payload['notes'] ?? null,
                $payload['verification_level'] ?? null,
            );
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['kyc' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.kyc.show', $account)
            ->with('success', 'تم اعتماد حالة التحقق وتسجيل قرار المراجعة.');
    }

    public function reject(Request $request, string $account): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->kycService->rejectKyc(
                $account,
                $this->currentUser($request),
                $payload['reason'],
                $payload['notes'] ?? null,
            );
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['kyc' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.kyc.show', $account)
            ->with('success', 'تم رفض حالة التحقق وتسجيل سبب القرار.');
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
