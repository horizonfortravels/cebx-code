@extends('layouts.app')
@section('title', 'بوابة الأعمال | الأدوار')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / الأدوار"
        title="خريطة الأدوار والصلاحيات"
        subtitle="ملف سريع يوضح كيف توزَّع الصلاحيات على فريق المنظمة قبل الانتقال إلى أي إدارة أعمق للأدوار."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        @if(auth()->user()?->hasPermission('users.read'))
            <a href="{{ route('b2b.users.index') }}" class="btn btn-s">فتح فريق المنظمة</a>
        @endif
    </x-page-header>

    <div class="stats-grid b2b-metrics-grid">
        @foreach($workspaceStats as $stat)
            <x-stat-card
                :iconName="$stat['iconName']"
                :label="$stat['label']"
                :value="$stat['value']"
                :meta="$stat['meta']"
                :eyebrow="$stat['eyebrow']"
            />
        @endforeach
    </div>

    <x-card title="الأدوار الحالية">
        <div class="b2b-table-shell">
            <table class="table">
                <thead>
                <tr>
                    <th>الاسم</th>
                    <th>المعرّف</th>
                    <th>المستخدمون</th>
                    <th>الصلاحيات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($roles as $role)
                    <tr>
                        <td>{{ $role->display_name ?: $role->name }}</td>
                        <td class="td-mono">{{ $role->slug ?: '—' }}</td>
                        <td>{{ number_format((int) $role->users_count) }}</td>
                        <td>{{ number_format((int) $role->permissions_count) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="empty-state">لا توجد أدوار مخصصة بعد لهذا الحساب. عند ضبط الأدوار ستظهر هنا مع تغطية الفريق والصلاحيات.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
@endsection
