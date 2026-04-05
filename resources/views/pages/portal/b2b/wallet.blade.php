@extends('layouts.app')
@section('title', 'بوابة الأعمال | المحفظة')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / المحفظة"
        title="المركز المالي للمنظمة"
        subtitle="قراءة واضحة للسيولة، الحجوزات، والحركة المالية الأخيرة حتى يعرف الفريق وضعه قبل أي إصدار جديد."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        @if(auth()->user()?->hasPermission('reports.read') && auth()->user()?->hasPermission('analytics.read'))
            <a href="{{ route('b2b.reports.index') }}" class="btn btn-s">فتح تقارير التنفيذ</a>
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
            <x-card title="المشهد المالي الحالي">
                <div class="b2b-wallet-card {{ $wallet ? '' : 'b2b-wallet-card--empty' }}">
                    <div class="b2b-wallet-card__label">الرصيد المتاح للاستخدام</div>
                    <div class="b2b-wallet-card__value">
                        {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }}
                        <span>{{ $wallet->currency ?? 'SAR' }}</span>
                    </div>
                    <div class="b2b-wallet-card__meta">
                        @if($wallet)
                            الرصيد الصافي بعد الحجز: {{ number_format($wallet->getEffectiveBalance(), 2) }} {{ $wallet->currency ?? 'SAR' }}
                        @else
                            لا توجد محفظة مفعلة لهذا الحساب حتى الآن.
                        @endif
                    </div>
                </div>

                <div class="b2b-guidance-list">
                    @if($wallet && $wallet->isFrozen())
                        <div class="b2b-guidance-card b2b-guidance-card--danger">
                            <strong>المحفظة موقوفة مؤقتاً</strong>
                            <p>قد تتوقف بعض خطوات الحجز المالي إلى أن تعود حالة المحفظة إلى الوضع النشط.</p>
                        </div>
                    @elseif($wallet && $wallet->isLowBalance())
                        <div class="b2b-guidance-card b2b-guidance-card--warning">
                            <strong>الرصيد يقترب من الحد الأدنى</strong>
                            <p>راجع الرصيد قبل إصدار شحنات جديدة حتى لا تتعطل الخطوات النهائية في رحلة التنفيذ.</p>
                        </div>
                    @else
                        <div class="b2b-guidance-card">
                            <strong>الصورة المالية مستقرة</strong>
                            <p>استمر في متابعة الحجوزات والحركات الأخيرة للحفاظ على انسيابية التنفيذ عند ارتفاع عدد الشحنات.</p>
                        </div>
                    @endif
                </div>
            </x-card>

            <x-card title="آخر الحركات">
                <div class="b2b-table-shell">
                    <table class="table">
                        <thead>
                        <tr>
                            <th>الوصف</th>
                            <th>النوع</th>
                            <th>المبلغ</th>
                            <th>التاريخ</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($transactions as $entry)
                            <tr>
                                <td>{{ $entry->description ?? $entry->typeLabel() }}</td>
                                <td>{{ $entry->typeLabel() }}</td>
                                <td>{{ number_format((float) $entry->amount, 2) }} {{ $wallet->currency ?? 'SAR' }}</td>
                                <td>{{ optional($entry->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">لا توجد حركات حديثة. ستظهر هنا عمليات الشحن والخصم والاسترداد والحجوزات عند بدء الاستخدام المالي الفعلي.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="الحجوزات الحالية">
                <div class="b2b-mini-stack">
                    @forelse($activeHolds as $hold)
                        <div class="b2b-mini-stack__item">
                            <div>
                                <div class="b2b-mini-stack__title">{{ $hold->shipment?->reference_number ?? $hold->shipment?->tracking_number ?? 'حجز تشغيلي' }}</div>
                                <div class="b2b-mini-stack__meta">{{ optional($hold->created_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</div>
                            </div>
                            <div class="b2b-mini-stack__value">{{ number_format((float) $hold->amount, 2) }} {{ $hold->currency ?? ($wallet->currency ?? 'SAR') }}</div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد حجوزات نشطة الآن، وهو ما يعني أن المساحة المالية أقل ازدحاماً في الوقت الحالي.</div>
                    @endforelse
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
