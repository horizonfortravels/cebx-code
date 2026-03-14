<?php

namespace App\Http\Controllers\Web;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserWebController extends WebController
{
    public function index()
    {
        $accountId = (string) auth()->user()->account_id;

        $users = User::query()
            ->where('account_id', $accountId)
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        $activeCount = User::query()
            ->where('account_id', $accountId)
            ->where('status', 'active')
            ->count();

        $inactiveCount = User::query()
            ->where('account_id', $accountId)
            ->whereIn('status', ['suspended', 'disabled'])
            ->count();

        return view('pages.users.index', [
            'users' => $users,
            'roles' => Role::query()->where('account_id', $accountId)->orderBy('name')->get(),
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => 'nullable|exists:roles,id',
        ]);

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'account_id' => auth()->user()->account_id,
                    'status' => 'active',
                    'user_type' => 'external',
                ]);

                if (! empty($data['role'])) {
                    $user->roles()->attach($data['role']);
                }

                return $user;
            });

            return back()->with('success', 'تمت إضافة المستخدم ' . $user->name . ' بنجاح.');
        } catch (\Throwable $exception) {
            Log::error('UserWebController::store failed', [
                'account_id' => auth()->user()->account_id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'تعذر إنشاء المستخدم الآن. حاول مرة أخرى أو تواصل مع الدعم.');
        }
    }

    public function toggle(User $user)
    {
        if ((string) $user->account_id !== (string) auth()->user()->account_id) {
            abort(403, 'غير مسموح لك بإدارة هذا المستخدم.');
        }

        if ((string) $user->id === (string) auth()->id()) {
            return back()->with('error', 'لا يمكنك تعطيل حسابك الحالي من هذه الصفحة.');
        }

        if ((bool) $user->is_owner) {
            return back()->with('error', 'لا يمكن تعطيل مالك الحساب من هذه الصفحة.');
        }

        $newStatus = ($user->status ?? 'active') === 'active' ? 'suspended' : 'active';

        DB::transaction(function () use ($user, $newStatus): void {
            $user->update(['status' => $newStatus]);

            if ($newStatus === 'suspended') {
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                $user->update(['remember_token' => Str::random(60)]);
            }
        });

        $message = $newStatus === 'suspended'
            ? 'تم تعليق المستخدم ' . $user->name . ' وإغلاق جلساته النشطة.'
            : 'تمت إعادة تفعيل المستخدم ' . $user->name . '.';

        return back()->with('success', $message);
    }

    public function destroy(User $user)
    {
        if ((string) $user->account_id !== (string) auth()->user()->account_id) {
            abort(403, 'غير مسموح لك بإدارة هذا المستخدم.');
        }

        if ((string) $user->id === (string) auth()->id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك الحالي من هذه الصفحة.');
        }

        if ((bool) $user->is_owner) {
            return back()->with('error', 'لا يمكن حذف مالك الحساب من هذه الصفحة.');
        }

        try {
            DB::transaction(function () use ($user): void {
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                $user->update(['remember_token' => Str::random(60)]);

                if (method_exists($user, 'trashed')) {
                    $user->delete();
                } else {
                    $user->update(['status' => 'disabled']);
                }
            });

            return back()->with('success', 'تمت إزالة المستخدم ' . $user->name . ' من الحساب.');
        } catch (\Throwable $exception) {
            Log::error('UserWebController::destroy failed', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'تعذر إزالة المستخدم الآن. حاول مرة أخرى أو تواصل مع الدعم.');
        }
    }
}
