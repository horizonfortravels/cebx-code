@extends('layouts.app')
@section('title', 'بوابة الأعمال | الويبهوكات')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / واجهة المطور / الويبهوكات"
        title="مركز الويبهوكات"
        subtitle="راجع نقاط الاستقبال الثابتة وسجل الأحداث الواردة للحساب. هذه الصفحة تشرح أين يصل الربط فعلياً إلى المنصة، لا كيف تُدار تكاملات الناقلين."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.developer.integrations') }}" class="btn btn-s">العودة إلى التكاملات</a>
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
            <x-card title="نقاط الاستقبال العامة">
                <div class="b2b-endpoint-grid">
                    <div class="b2b-endpoint-card">
                        <strong>أحداث المتجر</strong>
                        <code class="b2b-code-block">{{ $baseWebhookUrl }}/{platform}/{storeId}</code>
                    </div>
                    <div class="b2b-endpoint-card">
                        <strong>تتبع DHL</strong>
                        <code class="b2b-code-block">{{ $baseWebhookUrl }}/dhl/tracking</code>
                    </div>
                    <div class="b2b-endpoint-card">
                        <strong>مسار التتبع الاحتياطي</strong>
                        <code class="b2b-code-block">{{ $baseWebhookUrl }}/track/{trackingNumber}</code>
                    </div>
                </div>
            </x-card>

            <x-card title="سجل الأحداث الأخيرة">
                <div class="b2b-table-shell">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>المنصة</th>
                            <th>نوع الحدث</th>
                            <th>المتجر</th>
                            <th>الحالة</th>
                            <th>التوقيت</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($recentWebhookEvents as $event)
                            <tr>
                                <td>{{ $event->platform }}</td>
                                <td>{{ $event->event_type }}</td>
                                <td>{{ $event->store?->name ?? '—' }}</td>
                                <td><span class="b2b-status-pill b2b-status-pill--{{ $event->status === \App\Models\WebhookEvent::STATUS_FAILED ? 'danger' : 'info' }}">{{ $event->status }}</span></td>
                                <td>{{ optional($event->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">لا توجد أحداث ويبهوك واردة لهذا الحساب حتى الآن. عند تفعيل الربط ستظهر هنا الحالة الفعلية للرسائل الواردة.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="المتاجر القابلة للربط">
                <div class="b2b-mini-stack">
                    @forelse($stores as $store)
                        <div class="b2b-mini-stack__item">
                            <div>
                                <div class="b2b-mini-stack__title">{{ $store->name }}</div>
                                <div class="b2b-mini-stack__meta">يمكن ربط هذا المتجر مع المنصة عبر المسار البرمجي المناسب.</div>
                            </div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد متاجر مرتبطة بهذا الحساب حتى الآن.</div>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
