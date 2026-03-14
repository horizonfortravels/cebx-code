<?php

namespace App\Http\Controllers\Web;

use App\Models\Wallet;
use App\Models\WalletLedgerEntry;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class WalletWebController extends WebController
{
    public function index()
    {
        $accountId = auth()->user()->account_id;
        $wallet = Wallet::where('account_id', $accountId)->first();

        // إنشاء المحفظة إذا لم تكن موجودة
        if (!$wallet) {
            $wallet = Wallet::create([
                'account_id'        => $accountId,
                'currency'          => 'SAR',
                'available_balance' => 0,
                'locked_balance'    => 0,
            ]);
        }

        return view('pages.wallet.index', [
            'wallet'         => $wallet,
            'transactions'   => WalletLedgerEntry::where('wallet_id', $wallet->id)->latest('created_at')->paginate(20),
            'paymentMethods' => PaymentMethod::where('account_id', $accountId)->get(),
        ]);
    }

    public function topup(Request $r)
    {
        $r->validate(['amount' => 'required|numeric|min:10|max:50000']);

        $accountId = auth()->user()->account_id;

        // الحصول على أو إنشاء المحفظة
        $wallet = Wallet::where('account_id', $accountId)->first();
        if (!$wallet) {
            $wallet = Wallet::create([
                'account_id'        => $accountId,
                'currency'          => 'SAR',
                'available_balance' => 0,
                'locked_balance'    => 0,
            ]);
        }

        // إضافة الرصيد
        $wallet->increment('available_balance', $r->amount);
        $wallet->refresh();

        // تسجيل في دفتر القيود (WalletLedgerEntry — ليس WalletTransaction)
        WalletLedgerEntry::create([
            'wallet_id'       => $wallet->id,
            'type'            => 'topup',
            'amount'          => $r->amount,
            'running_balance' => $wallet->available_balance,
            'reference_type'  => 'topup',
            'reference_id'    => 'TXN-' . str_pad(WalletLedgerEntry::count() + 1, 5, '0', STR_PAD_LEFT),
            'actor_user_id'   => auth()->id(),
            'description'     => 'شحن رصيد المحفظة',
            'created_at'      => now(),
        ]);

        return back()->with('success', 'تم شحن ' . number_format($r->amount, 2) . ' ر.س بنجاح');
    }

    public function hold(Request $r)
    {
        $r->validate(['amount' => 'required|numeric|min:1']);

        $wallet = Wallet::where('account_id', auth()->user()->account_id)->first();
        if ($wallet) {
            $amount = (float) $r->amount;
            if ((float) $wallet->available_balance >= $amount) {
                $wallet->decrement('available_balance', $amount);
                $wallet->increment('locked_balance', $amount);
            }
        }

        return back()->with('warning', 'تم حجز ' . $r->amount . ' ر.س');
    }
}
