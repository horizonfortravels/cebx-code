<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\WebTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthWebController extends Controller
{
    public function showLogin(): RedirectResponse|View
    {
        return $this->portalSelector();
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->postLoginRedirect($user);
    }

    public function portalSelector(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            return redirect()->route('dashboard');
        }

        return view('pages.auth.portal-selector');
    }

    public function showB2bLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ($user->account && $user->account->type === 'organization') {
                return redirect()->route('b2b.dashboard');
            }
        }

        return view('pages.auth.login-b2b');
    }

    public function loginB2b(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $user->account || $user->account->type !== 'organization') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => $this->portalMismatchMessage($user, 'organization'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->route('b2b.dashboard');
    }

    public function showB2cLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            if ($user->account && $user->account->type === 'individual') {
                return redirect()->route('b2c.dashboard');
            }
        }

        return view('pages.auth.login-b2c');
    }

    public function loginB2c(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $user->account || $user->account->type !== 'individual') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => $this->portalMismatchMessage($user, 'individual'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return redirect()->route('b2c.dashboard');
    }

    public function showAdminLogin(): RedirectResponse|View
    {
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();

            if ($this->isInternal($user)) {
                return redirect()->route($this->internalHomeRouteName($user));
            }

            return redirect()->route('dashboard');
        }

        return view('pages.auth.login-admin');
    }

    public function loginAdmin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'بيانات الدخول غير صحيحة. تأكد من البريد وكلمة المرور ثم حاول مرة أخرى.'])->onlyInput('email');
        }

        /** @var User $user */
        $user = Auth::user();
        if ($inactiveResponse = $this->rejectInactiveUser($request, $user)) {
            return $inactiveResponse;
        }

        if (! $this->isInternal($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'هذا الحساب لا يملك وصولًا داخليًا. استخدم بوابة الأفراد أو بوابة الأعمال بحسب نوع حسابك.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);

        return $this->postLoginRedirect($user);
    }

    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::user();
        $loginUrl = url('/login');

        if ($user) {
            $loginUrl = match (true) {
                $this->isInternal($user) => url('/admin/login'),
                optional($user->account)->type === 'individual' => url('/b2c/login'),
                optional($user->account)->type === 'organization' => url('/b2b/login'),
                default => url('/login'),
            };
        }

        Auth::logout();
        WebTenantContext::clear($request);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($loginUrl);
    }

    private function postLoginRedirect(User $user): RedirectResponse
    {
        if ($this->isInternal($user)) {
            if (! $user->hasPermission('admin.access')) {
                return redirect()->route('internal.home');
            }

            return redirect()->route('admin.index');
        }

        return redirect()->intended(url('/'));
    }

    private function internalHomeRouteName(User $user): string
    {
        return $user->hasPermission('admin.access') ? 'admin.index' : 'internal.home';
    }

    private function isInternal(User $user): bool
    {
        return ($user->user_type ?? null) === 'internal';
    }

    private function rejectInactiveUser(Request $request, User $user): ?RedirectResponse
    {
        $status = (string) ($user->status ?? '');
        if (! in_array($status, ['suspended', 'disabled'], true)) {
            return null;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()->withErrors([
            'email' => $status === 'suspended'
                ? 'تم تعليق هذا الحساب مؤقتًا. تواصل مع الدعم أو مدير الحساب لمراجعة حالة الوصول.'
                : 'تم إيقاف هذا الحساب حاليًا. تواصل مع الدعم أو مدير الحساب لإعادة التفعيل.',
        ])->onlyInput('email');
    }

    private function portalMismatchMessage(User $user, string $requiredType): string
    {
        if ($this->isInternal($user)) {
            return 'هذا الحساب داخلي. استخدم بوابة الإدارة أو المساحة الداخلية بدل بوابات العملاء.';
        }

        $actualType = optional($user->account)->type;

        if ($requiredType === 'organization') {
            return $actualType === 'individual'
                ? 'هذا الحساب فردي. استخدم بوابة الأفراد بدل بوابة الأعمال.'
                : 'هذا الحساب لا يتبع بوابة الأعمال. استخدم البوابة المناسبة لنوع الحساب.';
        }

        return $actualType === 'organization'
            ? 'هذا الحساب تابع لمنظمة. استخدم بوابة الأعمال بدل بوابة الأفراد.'
            : 'هذا الحساب لا يتبع بوابة الأفراد. استخدم البوابة المناسبة لنوع الحساب.';
    }
}
