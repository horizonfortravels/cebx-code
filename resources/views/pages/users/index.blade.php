@extends('layouts.app')
@section('title', 'إدارة المستخدمين')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        @php
            $dashboardUrl = optional(auth()->user()->account)->type === 'organization'
                ? route('b2b.dashboard')
                : route('dashboard');
        @endphp
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ $dashboardUrl }}" style="color:inherit;text-decoration:none">لوحة التحكم</a>
            <span style="margin:0 6px">/</span>
            <span>إدارة المستخدمين</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">إدارة المستخدمين</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            راجع أعضاء الحساب الحالي، حالاتهم، وآخر نشاط لهم. هذه الصفحة مخصصة للمتابعة السريعة قبل إرسال دعوة جديدة أو تعديل الأدوار من المسارات الأعمق.
        </p>
    </div>

    @if(Route::has('invitations.index'))
        <a href="{{ route('invitations.index') }}" class="btn btn-pr">دعوة مستخدم جديد</a>
    @endif
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="👥" label="إجمالي المستخدمين" :value="$users->total()" />
    <x-stat-card icon="✅" label="نشطون" :value="$activeCount" />
    <x-stat-card icon="⛔" label="معلّقون أو معطّلون" :value="$inactiveCount" />
</div>

<x-card title="قائمة المستخدمين">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الأدوار</th>
                    <th>الحالة</th>
                    <th>آخر دخول</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $status = (string) ($user->status ?? 'active');
                        $userTypeLabel = match ((string) ($user->user_type ?? 'external')) {
                            'external' => 'خارجي',
                            'internal' => 'داخلي',
                            default => 'غير معروف',
                        };
                        $statusLabel = match ($status) {
                            'active' => 'نشط',
                            'suspended' => 'معلّق',
                            'disabled' => 'معطّل',
                            default => 'غير معروف',
                        };
                        $statusColor = match ($status) {
                            'active' => 'var(--ac)',
                            'suspended' => '#f59e0b',
                            'disabled' => 'var(--dg)',
                            default => 'var(--tm)',
                        };
                        $roleNames = $user->roles->pluck('display_name')->filter()->values();
                        if ($roleNames->isEmpty()) {
                            $roleNames = $user->roles->pluck('name')->filter()->values();
                        }
                        $roleNames = $roleNames->map(static function (string $roleName): string {
                            $normalized = trim(strtolower($roleName));

                            return match ($normalized) {
                                'admin', 'administrator' => 'مدير النظام',
                                'super admin', 'superadmin' => 'مدير النظام الأعلى',
                                'manager' => 'مدير',
                                'operator', 'operations', 'ops' => 'مشغّل',
                                'support', 'customer support' => 'الدعم',
                                'viewer', 'read only', 'readonly' => 'مُطلع',
                                'member' => 'عضو',
                                'owner' => 'مالك',
                                default => preg_match('/[A-Za-z]/', $roleName) ? 'دور غير معروف' : $roleName,
                            };
                        });
                    @endphp
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="user-avatar" style="background:rgba(37,99,235,.12);color:#2563eb">{{ mb_substr($user->name, 0, 1) }}</div>
                                <div>
                                    <div style="font-weight:700;color:var(--tx)">{{ $user->name }}</div>
                                    <div style="font-size:12px;color:var(--tm)">{{ $userTypeLabel }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:13px;color:var(--td)">{{ $user->email }}</td>
                        <td>
                            @if($roleNames->isNotEmpty())
                                <div style="display:flex;flex-wrap:wrap;gap:6px">
                                    @foreach($roleNames as $roleName)
                                        <span class="badge badge-in">{{ $roleName }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color:var(--tm)">بدون دور مخصص</span>
                            @endif
                        </td>
                        <td>
                            <span style="color:{{ $statusColor }};font-weight:700">{{ $statusLabel }}</span>
                        </td>
                        <td style="font-size:12px;color:var(--tm)">{{ $user->last_login_at?->format('Y-m-d H:i') ?? 'لم يسجل دخول بعد' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="empty-state">لا يوجد مستخدمون في هذا الحساب حتى الآن.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
        <div style="margin-top:14px">{{ $users->links() }}</div>
    @endif
</x-card>
@endsection
