<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/login
    // ═══════════════════════════════════════════════════════════════
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:100'],
            'remember'    => ['sometimes', 'boolean'],
        ]);

        // Find user (across all accounts — no tenant scope for login)
        $user = User::withoutGlobalScopes()
            ->where('email', $request->email)
            ->first();

        // Validate credentials
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['ERR_INVALID_CREDENTIALS: بيانات الدخول غير صحيحة.'],
            ]);
        }

        // Check account status
        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['ERR_ACCOUNT_DISABLED: الحساب معطل. تواصل مع الدعم.'],
            ]);
        }

        // Check if account itself is active
        $account = $user->account()->withoutGlobalScopes()->first();
        if ($account && $account->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['ERR_ACCOUNT_SUSPENDED: الحساب موقوف. تواصل مع الإدارة.'],
            ]);
        }

        // Create Sanctum token
        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        // Update last login
        $user->withoutGlobalScopes(function () use ($user, $request) {
            User::withoutGlobalScopes()
                ->where('id', $user->id)
                ->update([
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                ]);
        });

        // Audit log
        AuditLog::withoutGlobalScopes()->create([
            'account_id'  => $user->account_id,
            'user_id'     => $user->id,
            'action'      => 'user.login',
            'entity_type' => 'User',
            'entity_id'   => $user->id,
            'new_values'  => [
                'device'    => $deviceName,
                'method'    => 'email_password',
            ],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data'    => [
                'user' => [
                    'id'         => $user->id,
                    'name'       => $user->name,
                    'email'      => $user->email,
                    'phone'      => $user->phone,
                    'is_owner'   => $user->is_owner,
                    'status'     => $user->status,
                    'locale'     => $user->locale ?? 'ar',
                    'role'       => $user->roles()->first()?->name ?? 'user',
                    'account'    => [
                        'id'   => $account?->id,
                        'name' => $account?->name,
                        'type' => $account?->type,
                    ],
                ],
                'token'      => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/logout
    // ═══════════════════════════════════════════════════════════════
    public function logout(Request $request): JsonResponse
    {
        // Audit log before revoking
        AuditLog::withoutGlobalScopes()->create([
            'account_id'  => $request->user()->account_id,
            'user_id'     => $request->user()->id,
            'action'      => 'user.logout',
            'entity_type' => 'User',
            'entity_id'   => $request->user()->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/logout-all  — Revoke all tokens
    // ═══════════════════════════════════════════════════════════════
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج من جميع الأجهزة.',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/me  — Current authenticated user
    // ═══════════════════════════════════════════════════════════════
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account()->withoutGlobalScopes()->first();

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'phone'               => $user->phone,
                'is_owner'            => $user->is_owner,
                'status'              => $user->status,
                'locale'              => $user->locale ?? 'ar',
                'timezone'            => $user->timezone ?? 'Asia/Riyadh',
                'two_factor_enabled'  => $user->two_factor_secret !== null,
                'last_login_at'       => $user->last_login_at,
                'role'                => $user->roles()->first()?->name ?? 'user',
                'permissions'         => $user->getAllPermissions(),
                'account' => [
                    'id'     => $account?->id,
                    'name'   => $account?->name,
                    'type'   => $account?->type,
                    'status' => $account?->status,
                ],
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/forgot-password
    // ═══════════════════════════════════════════════════════════════
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'success' => $status === Password::RESET_LINK_SENT,
            'message' => $status === Password::RESET_LINK_SENT
                ? 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك.'
                : 'لم نتمكن من إرسال رابط الإعادة. تأكد من البريد.',
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    // ═══════════════════════════════════════════════════════════════
    // POST /api/v1/reset-password
    // ═══════════════════════════════════════════════════════════════
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing tokens for security
                $user->tokens()->delete();
            }
        );

        return response()->json([
            'success' => $status === Password::PASSWORD_RESET,
            'message' => $status === Password::PASSWORD_RESET
                ? 'تم إعادة تعيين كلمة المرور بنجاح. يمكنك تسجيل الدخول الآن.'
                : 'فشل في إعادة تعيين كلمة المرور. تأكد من صحة الرابط.',
        ], $status === Password::PASSWORD_RESET ? 200 : 422);
    }

    // ═══════════════════════════════════════════════════════════════
    // PUT /api/v1/change-password
    // ═══════════════════════════════════════════════════════════════
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password'         => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        User::withoutGlobalScopes()
            ->where('id', $user->id)
            ->update(['password' => Hash::make($request->password)]);

        // Revoke all other tokens (keep current session)
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        AuditLog::withoutGlobalScopes()->create([
            'account_id'  => $user->account_id,
            'user_id'     => $user->id,
            'action'      => 'user.password_changed',
            'entity_type' => 'User',
            'entity_id'   => $user->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.',
        ]);
    }
}
