<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * FIX P2-2: B2BAuthWebController
 *
 * بوابة دخول B2B وتتطلب account_slug لحل مشكلة تكرار البريد.
 *
 * المدخلات: account_slug + email + password
 * المنطق:
 *   1. ابحث عن الحساب بالمعرّف المختصر.
 *   2. تأكد أن نوعه organization.
 *   3. ابحث عن المستخدم داخل هذا الحساب بالبريد.
 *   4. تحقّق من كلمة المرور ثم سجّل الدخول.
 */
class B2BAuthWebController extends Controller
{
    /**
     * عرض نموذج تسجيل دخول B2B.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->account && $user->account->type === 'organization') {
                return redirect()->route('b2b.dashboard');
            }
            // هناك جلسة دخول لكن الحساب ليس organization، لذا يتم تسجيل الخروج وإعادة التوجيه.
            Auth::logout();
        }

        return view('b2b.b2b-login');
    }

    /**
     * معالجة تسجيل الدخول لبوابة B2B.
     */
    public function login(Request $request)
    {
        $request->validate([
            'account_slug' => 'required|string|max:100',
            'email'        => 'required|email',
            'password'     => 'required|string',
        ]);

        // 1. البحث عن الحساب بالمعرّف المختصر.
        $account = Account::where('slug', $request->account_slug)->first();

        if (!$account) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['account_slug' => 'لم يتم العثور على الحساب. تأكد من معرّف المنظمة.']);
        }

        // 2. التأكد أن نوع الحساب organization.
        if ($account->type !== 'organization') {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['account_slug' => 'هذا الحساب ليس حساب منظمة. استخدم بوابة B2C.']);
        }

        // 3. البحث عن المستخدم داخل هذا الحساب.
        $user = User::where('account_id', $account->id)
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['email' => 'البريد الإلكتروني غير مسجل في هذا الحساب.']);
        }

        // 4. التحقق من كلمة المرور.
        if (!Hash::check($request->password, $user->password)) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['password' => 'كلمة المرور غير صحيحة.']);
        }

        // 5. التحقق من حالة المستخدم.
        if (isset($user->status) && $user->status === 'suspended') {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['email' => 'تم تعليق حسابك. تواصل مع الإدارة.']);
        }

        // 6. تسجيل الدخول.
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('b2b.dashboard');
    }

    /**
     * تسجيل الخروج.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2b.login');
    }
}
