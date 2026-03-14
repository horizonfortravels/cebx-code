@extends('layouts.app')
@section('title', 'بوابة الأعمال | الأدوار')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
            <span style="margin:0 6px">/</span>
            <span>الأدوار</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">خريطة الأدوار والصلاحيات</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            اعرض الأدوار الموجودة على الحساب الحالي بسرعة، ثم افتح إدارة الأدوار الكاملة عندما تحتاج إلى تعديل التوزيع أو مراجعة الصلاحيات بالتفصيل.
        </p>
    </div>
    <a href="{{ route('roles.index') }}" class="btn btn-pr">فتح إدارة الأدوار</a>
</div>

<x-card title="الأدوار الحالية">
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الاسم</th>
                <th>Slug</th>
                <th>المستخدمون</th>
                <th>الصلاحيات</th>
            </tr>
            </thead>
            <tbody>
            @forelse($roles as $role)
                <tr>
                    <td>{{ $role->display_name ?: $role->name }}</td>
                    <td class="td-mono">{{ $role->slug ?: '—' }}</td>
                    <td>{{ $role->users_count }}</td>
                    <td>{{ $role->permissions_count }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا توجد أدوار مخصصة بعد لهذا الحساب.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
