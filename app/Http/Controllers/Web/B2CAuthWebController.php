<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * FIX P2-2: B2CAuthWebController
 *
 * ط¨ظˆط§ط¨ط© ط¯ط®ظˆظ„ B2C â€” ظ„ظ„ط­ط³ط§ط¨ط§طھ ط§ظ„ظپط±ط¯ظٹط©.
 *
 * ط§ظ„ظ…ط¯ط®ظ„ط§طھ: email + password
 * ط§ظ„ظ…ظ†ط·ظ‚:
 *   1. ط§ط¨ط­ط« ط¹ظ† ط§ظ„ظ…ط³طھط®ط¯ظ… ط¨ط§ظ„ط¨ط±ظٹط¯ ط­ظٹط« ط­ط³ط§ط¨ظ‡ individual
 *   2. ط¥ط°ط§ ظˆظڈط¬ط¯ ط£ظƒط«ط± ظ…ظ† ظˆط§ط­ط¯: ط£ظˆظ‚ظپ ط§ظ„ط¹ظ…ظ„ظٹط©
 *   3. طھط­ظ‚ظ‚ ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط«ظ… ط³ط¬ظ‘ظ„ ط§ظ„ط¯ط®ظˆظ„
 */
class B2CAuthWebController extends Controller
{
    /**
     * ط¹ط±ط¶ ظ†ظ…ظˆط°ط¬ طھط³ط¬ظٹظ„ ط¯ط®ظˆظ„ B2C.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->account && $user->account->type === 'individual') {
                return redirect()->route('b2c.dashboard');
            }
            Auth::logout();
        }

        return view('b2c.b2c-login');
    }

    /**
     * ظ…ط¹ط§ظ„ط¬ط© طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„ ظ„ظ€ B2C.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // 1. ط§ظ„ط¨ط­ط« ط¹ظ† ظ…ط³طھط®ط¯ظ…ظٹظ† ط¨ظ‡ط°ط§ ط§ظ„ط¨ط±ظٹط¯ ظپظٹ ط­ط³ط§ط¨ط§طھ ظپط±ط¯ظٹط©
        $users = User::where('email', $request->email)
            ->whereHas('account', function ($query) {
                $query->where('type', 'individual');
            })
            ->with('account')
            ->get();

        // 2. ظ„ط§ ظٹظˆط¬ط¯ ظ…ط³طھط®ط¯ظ…
        if ($users->isEmpty()) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ ط؛ظٹط± ظ…ط³ط¬ظ„.']);
        }

        // 3. ط£ظƒط«ط± ظ…ظ† ظ…ط³طھط®ط¯ظ… ط¨ظ†ظپط³ ط§ظ„ط¨ط±ظٹط¯ (ظ†ط§ط¯ط± ظ„ظƒظ† ظ…ظ…ظƒظ† ط¨ط³ط¨ط¨ طھطµظ…ظٹظ… DB)
        if ($users->count() > 1) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'ظ‡ط°ط§ ط§ظ„ط¨ط±ظٹط¯ ظ…ط±طھط¨ط· ط¨ط£ظƒط«ط± ظ…ظ† ط­ط³ط§ط¨. طھظˆط§طµظ„ ظ…ط¹ ط§ظ„ط¯ط¹ظ… ط§ظ„ظپظ†ظٹ ط£ظˆ ط§ط³طھط®ط¯ظ… ط¨ظˆط§ط¨ط© B2B ظ…ط¹ ظ…ط¹ط±ظ‘ظپ ط§ظ„ظ…ظ†ط¸ظ…ط©.',
                ]);
        }

        $user = $users->first();

        // 4. ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط±
        if (!Hash::check($request->password, $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['password' => 'ظƒظ„ظ…ط© ط§ظ„ظ…ط±ظˆط± ط؛ظٹط± طµط­ظٹط­ط©.']);
        }

        // 5. ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ط­ط§ظ„ط© ط§ظ„ظ…ط³طھط®ط¯ظ…
        if (isset($user->status) && $user->status === 'suspended') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'طھظ… طھط¹ظ„ظٹظ‚ ط­ط³ط§ط¨ظƒ. طھظˆط§طµظ„ ظ…ط¹ ط§ظ„ط¯ط¹ظ….']);
        }

        // 6. طھط³ط¬ظٹظ„ ط§ظ„ط¯ط®ظˆظ„
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('b2c.dashboard'));
    }

    /**
     * طھط³ط¬ظٹظ„ ط§ظ„ط®ط±ظˆط¬.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('b2c.login');
    }
}
