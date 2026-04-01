<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use App\Services\InternalExternalAccountMemberAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalAccountMembersController extends Controller
{
    public function __construct(
        private readonly InternalExternalAccountMemberAdminService $memberAdminService,
    ) {}

    public function invite(Request $request, string $account): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'string'],
        ]);

        try {
            $this->memberAdminService->inviteMember($accountModel, $data, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()
                ->withErrors(['account_member' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', 'تم إرسال دعوة العضو بنجاح.');
    }

    public function deactivate(Request $request, string $account, string $member): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);

        try {
            $this->memberAdminService->deactivateMember($accountModel, $member, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()->withErrors(['account_member' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', 'تم تعطيل عضو المنظمة.');
    }

    public function reactivate(Request $request, string $account, string $member): RedirectResponse
    {
        $accountModel = $this->findAccountOrFail($account);

        try {
            $this->memberAdminService->reactivateMember($accountModel, $member, $this->currentUser($request));
        } catch (BusinessException $exception) {
            return back()->withErrors(['account_member' => $exception->getMessage()]);
        }

        return redirect()
            ->route('internal.accounts.show', $accountModel->id)
            ->with('success', 'تمت إعادة تفعيل عضو المنظمة.');
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
