<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WalletWebController extends WebController
{
    public function index(): RedirectResponse
    {
        return $this->redirectToWalletWorkspace();
    }

    public function topup(Request $r): RedirectResponse
    {
        return $this->redirectToWalletWorkspace()
            ->with('warning', 'شحن الرصيد غير متاح من هذه الواجهة حاليًا.');
    }

    public function hold(Request $r): RedirectResponse
    {
        return $this->redirectToWalletWorkspace()
            ->with('warning', 'حجز الرصيد غير متاح من هذه الواجهة حاليًا.');
    }

    private function redirectToWalletWorkspace(): RedirectResponse
    {
        $account = auth()->user()?->account;

        if ($account && $account->isIndividual()) {
            return redirect()->route('b2c.wallet.index');
        }

        return redirect()->route('b2b.wallet.index');
    }
}
