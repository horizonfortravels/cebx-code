@extends('layouts.app')
@section('title', 'مستخدمو الحساب')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('admin.index') }}" style="color:inherit;text-decoration:none">الإدارة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('admin.tenant-context') }}" style="color:inherit;text-decoration:none">اختيار الحساب</a>
            <span style="margin:0 6px">/</span>
            <span>مستخدمو الحساب</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مستخدمو الحساب</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            أنت تتصفح بيانات المستخدمين الخاصة بالحساب <strong>{{ $selectedAccount->name }}</strong>. استخدم هذه الصفحة لمراجعة الحالة الحالية قبل الانتقال إلى أي إجراء أعمق.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('admin.tenant-context') }}" class="btn btn-s">تبديل الحساب</a>
        <a href="{{ route('admin.users') }}" class="btn btn-pr">تحديث القائمة</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="👥" label="إجمالي المستخدمين" :value="number_format($users->total())" />
    <x-stat-card icon="✅" label="نشطون" :value="number_format($users->getCollection()->where('status', 'active')->count())" />
    <x-stat-card icon="⛔" label="معلّقون أو معطلون" :value="number_format($users->getCollection()->whereIn('status', ['suspended', 'disabled'])->count())" />
</div>

<div class="card">
    <div class="card-title">قائمة المستخدمين</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>آخر دخول</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ ($user->user_type ?? 'external') === 'external' ? 'خارجي' : ($user->user_type ?? 'external') }}</td>
                    <td>{{ ($user->status ?? 'active') === 'active' ? 'نشط' : ($user->status ?? 'active') }}</td>
                    <td>{{ optional($user->last_login_at)->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty-state">لا يوجد مستخدمون لهذا الحساب بعد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $users->links() }}</div>
</div>
@endsection
