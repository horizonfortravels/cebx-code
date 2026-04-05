@extends('layouts.app')
@section('title', 'بوابة الأعمال | الطلبات')

@section('content')
@php
    $orderStatusLabel = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\Order::STATUS_PENDING => 'جديد',
            \App\Models\Order::STATUS_READY => 'جاهز',
            \App\Models\Order::STATUS_PROCESSING => 'قيد المعالجة',
            \App\Models\Order::STATUS_SHIPPED => 'تم شحنه',
            \App\Models\Order::STATUS_DELIVERED => 'تم تسليمه',
            \App\Models\Order::STATUS_CANCELLED => 'ملغي',
            \App\Models\Order::STATUS_ON_HOLD => 'موقوف',
            \App\Models\Order::STATUS_FAILED => 'فشل',
            default => (string) ($status ?: 'غير محدد'),
        };
    };
    $orderStatusTone = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\Order::STATUS_DELIVERED,
            \App\Models\Order::STATUS_SHIPPED => 'success',
            \App\Models\Order::STATUS_READY,
            \App\Models\Order::STATUS_PROCESSING => 'info',
            \App\Models\Order::STATUS_ON_HOLD,
            \App\Models\Order::STATUS_FAILED,
            \App\Models\Order::STATUS_CANCELLED => 'danger',
            default => 'warning',
        };
    };
@endphp

<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / الطلبات"
        title="مركز طلبات المنظمة"
        subtitle="قراءة عملية للطلبات الواردة من متاجرك قبل أن تتحول إلى شحنات، مع إظهار ما يحتاج قراراً سريعاً من الفريق."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.shipments.create') }}" class="btn btn-pr">بدء طلب شحنة لفريقك</a>
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

    <div class="b2b-inline-metrics b2b-inline-metrics--spaced">
        @foreach($summaryGroups as $group)
            <div class="b2b-inline-metric b2b-inline-metric--{{ $group['tone'] }}">
                <span class="b2b-inline-metric__label">{{ $group['label'] }}</span>
                <strong class="b2b-inline-metric__value">{{ $group['value'] }}</strong>
            </div>
        @endforeach
    </div>

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="آخر الطلبات">
                <div class="b2b-table-shell">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>رقم الطلب</th>
                            <th>المتجر</th>
                            <th>الحالة</th>
                            <th>المبلغ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td class="td-mono">{{ $order->external_order_number ?? $order->external_order_id ?? $order->id }}</td>
                                <td>{{ $order->store?->name ?? 'متجر غير محدد' }}</td>
                                <td><span class="b2b-status-pill b2b-status-pill--{{ $orderStatusTone($order->status) }}">{{ $orderStatusLabel($order->status) }}</span></td>
                                <td>{{ number_format((float) ($order->total_amount ?? 0), 2) }} {{ $order->currency ?? 'SAR' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">لا توجد طلبات حديثة. عند مزامنة المتاجر أو استقبال طلبات جديدة ستظهر هنا مع حالة واضحة لكل عنصر.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="قنوات الطلبات">
                <div class="b2b-mix-list">
                    @forelse($sourceMix as $item)
                        <div class="b2b-mix-item">
                            <div class="b2b-mix-item__head">
                                <span>{{ $item['label'] }}</span>
                                <strong>{{ $item['value'] }}</strong>
                            </div>
                            <div class="b2b-mix-item__meter">
                                <span class="b2b-mix-item__meter-fill b2b-mix-item__meter-fill--info" style="width: {{ max(6, $item['percentage']) }}%"></span>
                            </div>
                            <div class="b2b-mix-item__meta">{{ $item['percentage'] }}%</div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد قنوات أو مصادر كافية لعرض توزيع الطلبات بعد.</div>
                    @endforelse
                </div>
            </x-card>

            <x-card title="إرشاد تشغيلي">
                <div class="b2b-guidance-list">
                    <div class="b2b-guidance-card">
                        <strong>ابدأ بما يحتاج قراراً</strong>
                        <p>الطلبات الجديدة أو الموقوفة أول ما يجب أن يراه الفريق قبل فتح مساحة الشحنات الكاملة.</p>
                    </div>
                    <div class="b2b-guidance-card">
                        <strong>تأكد من المتجر المصدر</strong>
                        <p>ربط الطلب بالمتجر الصحيح يسهّل تفسير الويبهوكات والتقارير لاحقاً.</p>
                    </div>
                    <div class="b2b-guidance-card">
                        <strong>انتقل إلى الشحن عند الجاهزية</strong>
                        <p>عندما يصبح الطلب جاهزاً للتنفيذ، استخدم رحلة الشحن الموحدة بدل التعامل مع الطلب ككيان منفصل.</p>
                    </div>
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
