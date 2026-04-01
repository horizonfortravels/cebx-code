<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Services\InternalExternalAccountSupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalAccountSupportActionsController extends Controller
{
    public function __construct(
        private readonly InternalExternalAccountSupportService $supportService,
    ) {}

    public function passwordReset(Request $request, string $account): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);

        try {
            $target = $this->supportService->sendPasswordReset($accountModel, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', sprintf('تم إرسال رابط إعادة تعيين كلمة المرور إلى %s.', $target->email));
    }

    public function resendInvitation(Request $request, string $account, string $invitation): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);

        try {
            $this->supportService->resendInvitation($accountModel, $invitation, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()->withErrors(['account' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', 'تمت إعادة إرسال دعوة العضو بنجاح.');
    }

    private function findAccountOrFail(string $account): Account
    {
        return Account::query()
            ->withoutGlobalScopes()
            ->findOrFail($account);
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
