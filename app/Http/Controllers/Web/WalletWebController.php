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
            ->with('warning', 'ÔÍä ÇáÑÕíÏ ãÊÇÍ ÚÈÑ ãÓÇÑ ÇáÝæÊÑÉ ÇáÍÇáí ÝÞØ.');
    }

    public function hold(Request $r): RedirectResponse
    {
        return $this->redirectToWalletWorkspace()
            ->with('warning', 'ÍÌÒ ÇáÑÕíÏ íÊã ÚÈÑ ãÓÇÑ ÇáÔÍä æÇáÝæÊÑÉ ÇáÍÇáí ÝÞØ.');
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