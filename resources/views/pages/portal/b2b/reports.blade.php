@extends('layouts.app')
@section('title', 'بوابة الأعمال | التقارير')

@section('content')
<div class="b2b-workspace-page">
    <x-page-header
        eyebrow="بوابة الأعمال / التقارير"
        title="التقارير التنفيذية للمنظمة"
        subtitle="مساحة قراءة سريعة تجمع مؤشرات الشحن والمالية والفريق في شكل يمكن استيعابه بسرعة قبل الانتقال إلى القراءة الأعمق."
        :meta="'الحساب الحالي: ' . ($account->name ?? 'حساب المنظمة')"
    >
        <a href="{{ route('b2b.dashboard') }}" class="btn btn-s">العودة إلى الرئيسية</a>
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
            <x-card title="بطاقات القراءة التنفيذية">
                <div class="b2b-report-grid">
                    @foreach($reportHighlights as $card)
                        <article class="b2b-report-card b2b-report-card--{{ $card['tone'] }}">
                            <div class="b2b-report-card__title">{{ $card['title'] }}</div>
                            <p class="b2b-report-card__body">{{ $card['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </x-card>

            <x-card title="اتجاه التنفيذ">
                <div class="b2b-analytics-grid">
                    <article class="b2b-trend-panel">
                        <div class="b2b-panel-kicker">آخر سبعة أيام</div>
                        <div class="b2b-trend-bars">
                            @foreach($shipmentTrend as $point)
                                <div class="b2b-trend-bar">
                                    <span class="b2b-trend-bar__fill" style="height: {{ max(12, $point['height']) }}%"></span>
                                    <span class="b2b-trend-bar__label">{{ $point['label'] }}</span>
                                    <span class="b2b-trend-bar__value">{{ $point['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="b2b-mix-panel">
                        <div class="b2b-panel-kicker">التوزيع الحالي</div>
                        <div class="b2b-mix-list">
                            @foreach($statusMix as $item)
                                <div class="b2b-mix-item">
                                    <div class="b2b-mix-item__head">
                                        <span>{{ $item['label'] }}</span>
                                        <strong>{{ $item['value'] }}</strong>
                                    </div>
                                    <div class="b2b-mix-item__meter">
                                        <span class="b2b-mix-item__meter-fill b2b-mix-item__meter-fill--{{ $item['tone'] }}" style="width: {{ max(6, $item['percentage']) }}%"></span>
                                    </div>
                                    <div class="b2b-mix-item__meta">{{ $item['percentage'] }}%</div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="صورة الفريق">
                <div class="b2b-inline-metrics">
                    @foreach($teamSnapshot as $item)
                        <div class="b2b-inline-metric b2b-inline-metric--{{ $item['tone'] }}">
                            <span class="b2b-inline-metric__label">{{ $item['label'] }}</span>
                            <strong class="b2b-inline-metric__value">{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </x-card>
        </aside>
    </div>
</div>
@endsection
