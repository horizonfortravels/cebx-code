@extends('layouts.app')
@section('title', 'بوابة الأعمال | المستخدمون')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
            <span style="margin:0 6px">/</span>
            <span>المستخدمون</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">نظرة على فريق المنظمة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            راجع المستخدمين النشطين والأدوار الحالية على حساب المنظمة قبل الانتقال إلى إدارة المستخدمين الكاملة أو إرسال الدعوات.
        </p>
    </div>
    <a href="{{ route('users.index') }}" class="btn btn-pr">فتح إدارة المستخدمين</a>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    @foreach($stats as $stat)
        <x-stat-card :icon="$stat['icon']" :label="$stat['label']" :value="$stat['value']" />
    @endforeach
</div>

<x-card title="المستخدمون الحاليون">
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الاسم</th>
                <th>البريد</th>
                <th>الحالة</th>
                <th>الأدوار</th>
            </tr>
            </thead>
            <tbody>
            @forelse($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ $user->status ?? 'active' }}</td>
                    <td>{{ $user->roles->pluck('display_name')->filter()->implode('، ') ?: $user->roles->pluck('name')->implode('، ') ?: '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا يوجد مستخدمون في هذا الحساب حاليًا.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
