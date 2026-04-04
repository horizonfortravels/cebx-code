@extends('layouts.app')
@section('title', 'أدوار الحساب')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('admin.index') }}" style="color:inherit;text-decoration:none">الإدارة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('admin.tenant-context') }}" style="color:inherit;text-decoration:none">اختيار الحساب</a>
            <span style="margin:0 6px">/</span>
            <span>أدوار الحساب</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">أدوار الحساب</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            هذه الصفحة تعرض صورة سريعة للأدوار المرتبطة بالحساب <strong>{{ $selectedAccount->name }}</strong> وعدد المستخدمين والصلاحيات داخل كل دور.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('admin.tenant-context') }}" class="btn btn-s">تبديل الحساب</a>
        <a href="{{ route('admin.roles') }}" class="btn btn-pr">تحديث الأدوار</a>
    </div>
</div>

<div class="card">
    <div class="card-title">جدول الأدوار</div>
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الاسم</th>
                <th>المعرّف المختصر</th>
                <th>عدد المستخدمين</th>
                <th>عدد الصلاحيات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
                <tr>
                    <td>{{ $role->display_name ?: $role->name }}</td>
                    <td>{{ $role->slug ?: '—' }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ $role->permissions_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا توجد أدوار لهذا الحساب بعد.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $roles->links() }}</div>
</div>
@endsection
