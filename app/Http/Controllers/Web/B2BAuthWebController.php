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
 * ط¨ظˆط§ط¨ط© ط¯ط®ظˆظ„ B2B â€” طھطھط·ظ„ط¨ account_slug ظ„ط­ظ„ ظ…ط´ظƒظ„ط© طھظƒط±ط§ط± ط§ظ„ط¨ط±ظٹط¯.
 *
 * ط§ظ„ظ…ط¯ط®ظ„ط§طھ: account_slug + email + password
 * ط§ظ„ظ…ظ†ط·ظ‚:
 *   1. ط§ط¨ط­ط« ط¹ظ† ط§ظ„ط­ط³ط§ط¨ ط¨ط§ظ„ظ€ slug
 *   2. طھط£ظƒط¯ ط£ظ† ظ†ظˆط¹ظ‡ organization
 *   3. ط§ط¨ط­ط« ط¹ظ† ط§ظ„ظ…ط³طھط®ط¯ظ… ط¯ط§ط®ظ„ ظ‡ط°ط§ ط§ظ„ط­ط³ط§ط¨ ط¨ط§ظ„ط¨ط±ظٹط¯
 *   4. طھط­ظ‚ظ‚ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ظˆط³ط¬ظ‘ظ„ ط§ظ„ط¯ط®ظˆظ„
 */
class B2BAuthWebController extends Controller
{
    /**
     * ط¹ط±ط¶ ظ†ظ…ظˆط°ط¬ طھط³ط¬ظٹظ„ ط¯ط®ظˆظ„ B2B.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->account && $user->account->type === 'organization') {
                return redirect()->route('b2b.dashboard');
            }
            // ظ…ط³ط¬ظ„ ط¯ط®ظˆظ„ ظ„ظƒظ† ظ„ظٹط³ organization â€” طھط³ط¬ظٹظ„ ط®ط±ظˆط¬ ظˆط¥ط¹ط§ط¯ط© طھظˆط¬ظٹظ‡
            Auth::logout();
        }

        return view('b2b.b2b-login');
    }

    /**
     * ظ…ط¹ط§ظ„ط¬ط© طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„ ظ„ظ€ B2B.
     */
    public function login(Request $request)
    {
        $request->validate([
            'account_slug' => 'required|string|max:100',
            'email'        => 'required|email',
            'password'     => 'required|string',
        ]);

        // 1. ط§ظ„ط¨ط­ط« ط¹ظ† ط§ظ„ط­ط³ط§ط¨ ط¨ط§ظ„ظ€ slug
        $account = Account::where('slug', $request->account_slug)->first();

        if (!$account) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['account_slug' => 'ظ„ظ… ظٹطھظ… ط§ظ„ط¹ط«ظˆط± ط¹ظ„ظ‰ ط§ظ„ط­ط³ط§ط¨. طھط£ظƒط¯ ظ…ظ† ظ…ط¹ط±ظ‘ظپ ط§ظ„ظ…ظ†ط¸ظ…ط©.']);
        }

        // 2. ط§ظ„طھط£ظƒط¯ ط£ظ† ظ†ظˆط¹ ط§ظ„ط­ط³ط§ط¨ organization
        if ($account->type !== 'organization') {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['account_slug' => 'ظ‡ط°ط§ ط§ظ„ط­ط³ط§ط¨ ظ„ظٹط³ ط­ط³ط§ط¨ ظ…ظ†ط¸ظ…ط©. ط§ط³طھط®ط¯ظ… ط¨ظˆط§ط¨ط© B2C.']);
        }

        // 3. ط§ظ„ط¨ط­ط« ط¹ظ† ط§ظ„ظ…ط³طھط®ط¯ظ… ط¯ط§ط®ظ„ ظ‡ط°ط§ ط§ظ„ط­ط³ط§ط¨
        $user = User::where('account_id', $account->id)
            ->where('email', $request->email)
            ->first();

        if (!$user) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['email' => 'ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ ط؛ظٹط± ظ…ط³ط¬ظ„ ظپظٹ ظ‡ط°ط§ ط§ظ„ط­ط³ط§ط¨.']);
        }

        // 4. ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±
        if (!Hash::check($request->password, $user->password)) {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['password' => 'ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط؛ظٹط± طµط­ظٹط­ط©.']);
        }

        // 5. ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط­ط§ظ„ط© ط§ظ„ظ…ط³طھط®ط¯ظ…
        if (isset($user->status) && $user->status === 'suspended') {
            return back()
                ->withInput($request->only('account_slug', 'email'))
                ->withErrors(['email' => 'طھظ… طھط¹ظ„ظٹظ‚ ط­ط³ط§ط¨ظƒ. طھظˆط§طµظ„ ظ…ط¹ ط§ظ„ط¥ط¯ط§ط±ط©.']);
        }

        // 6. طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('b2b.dashboard');
    }

    /**
     * طھط³ط¬ظٹظ„ ط§ظ„ط®ط±ظˆط¬.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2b.login');
    }
}
