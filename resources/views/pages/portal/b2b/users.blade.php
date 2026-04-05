@extends('layouts.app')
@section('title', 'بوابة الأعمال | المستخدمون')

@section('content')
@php
    $statusLabel = static function (?string $status): string {
        return match ((string) $status) {
            'active' => 'نشط',
            'pending' => 'قيد التفعيل',
            'suspended' => 'معلّق',
            'disabled' => 'معطّل',
            default => (string) ($status ?: 'غير محدد'),
        };
    };
    $statusTone = static function (?string $status): string {
        return match ((string) $status) {
            'active' => 'success',
            'pending' => 'warning',
            'suspended', 'disabled' => 'danger',
            default => 'neutral',
        };
    };
@endphp

<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / المستخدمون"
        title="فريق المنظمة"
        subtitle="نظرة واضحة على أعضاء الفريق والأدوار الحالية حتى يبقى توزيع العمل والصلاحيات مفهوماً قبل أي توسع تشغيلي."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        @if(auth()->user()?->hasPermission('roles.read'))
            <a href="{{ route('b2b.roles.index') }}" class="btn btn-s">مراجعة الأدوار</a>
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

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="أعضاء الفريق الحاليون">
                <div class="b2b-table-shell">
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
                                <td><span class="b2b-status-pill b2b-status-pill--{{ $statusTone($user->status) }}">{{ $statusLabel($user->status) }}</span></td>
                                <td>{{ $user->roles->pluck('display_name')->filter()->implode('، ') ?: $user->roles->pluck('name')->implode('، ') ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">لا يوجد أعضاء ظاهرون في هذا الحساب حالياً. عند إضافة أعضاء جدد أو دعوات مفعلة سيظهرون هنا مع أدوارهم.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="تغطية الأدوار">
                <div class="b2b-mini-stack">
                    @forelse($roleCoverage as $role)
                        <div class="b2b-mini-stack__item">
                            <div>
                                <div class="b2b-mini-stack__title">{{ $role->display_name ?: $role->name }}</div>
                                <div class="b2b-mini-stack__meta">{{ $role->permissions_count ?? $role->permissions?->count() ?? 0 }} صلاحية تقريبية</div>
                            </div>
                            <div class="b2b-mini-stack__value">{{ number_format((int) $role->users_count) }}</div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد تغطية أدوار كافية لعرضها بعد.</div>
                    @endforelse
                </div>
            </x-card>

            <x-card title="ملاحظات سريعة">
                <div class="b2b-guidance-list">
                    <div class="b2b-guidance-card">
                        <strong>راجع المعلّقين أولاً</strong>
                        <p>الحسابات المعلّقة أو المعطلة قد تؤثر على استمرارية الفريق في أوقات الذروة.</p>
                    </div>
                    <div class="b2b-guidance-card">
                        <strong>وازن بين الأدوار</strong>
                        <p>عندما يتركز المستخدمون في دور واحد فقط، يصبح عبء التشغيل أقل مرونة عند الغياب أو التوسع.</p>
                    </div>
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
