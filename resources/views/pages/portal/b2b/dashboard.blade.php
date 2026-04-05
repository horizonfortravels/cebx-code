@extends('layouts.app')
@section('title', 'بوابة الأعمال | الرئيسية')

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
    $resolveShipmentStatus = static function (?string $status): string {
        if (! $status) {
            return 'غير محدد';
        }

        $key = 'portal_shipments.statuses.' . $status;
        $translated = __($key);

        return $translated === $key ? $status : $translated;
    };
    $shipmentTone = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\Shipment::STATUS_DELIVERED => 'success',
            \App\Models\Shipment::STATUS_IN_TRANSIT,
            \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY,
            \App\Models\Shipment::STATUS_READY_FOR_PICKUP,
            \App\Models\Shipment::STATUS_PICKED_UP,
            \App\Models\Shipment::STATUS_PURCHASED => 'info',
            \App\Models\Shipment::STATUS_EXCEPTION,
            \App\Models\Shipment::STATUS_FAILED,
            \App\Models\Shipment::STATUS_REQUIRES_ACTION,
            \App\Models\Shipment::STATUS_KYC_BLOCKED,
            \App\Models\Shipment::STATUS_RETURNED => 'danger',
            default => 'warning',
        };
    };
@endphp

@section('content')
<div class="b2b-dashboard">
    <section class="b2b-command-band">
        <div class="b2b-command-band__meta">
            <span class="b2b-command-band__eyebrow">بوابة الأعمال / الرئيسية</span>
            <h1 class="b2b-command-band__title">مركز تشغيل حساب المنظمة</h1>
            <p class="b2b-command-band__body">
                هذه الصفحة تجمع ما يحتاجه فريق <strong>{{ $account->name }}</strong> يومياً: الشحنات الجارية، الطلبات التي تحتاج قراراً،
                الجاهزية المالية، والفريق الذي يشغّل الحساب. الهدف هنا هو إعطاء قراءة تنفيذية واضحة قبل فتح كل مساحة تفصيلية.
            </p>
        </div>

        <div class="b2b-summary-pills">
            @foreach($summaryPills as $pill)
                <div class="b2b-summary-pill b2b-summary-pill--{{ $pill['tone'] }}">
                    <span class="b2b-summary-pill__label">{{ $pill['label'] }}</span>
                    <strong class="b2b-summary-pill__value">{{ $pill['value'] }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <div class="stats-grid b2b-metrics-grid">
        @foreach($dashboardStats as $stat)
            <x-stat-card
                :iconName="$stat['iconName']"
                :label="$stat['label']"
                :value="$stat['value']"
                :meta="$stat['meta']"
                :eyebrow="$stat['eyebrow']"
            />
        @endforeach
    </div>

    <section class="b2b-action-grid">
        @foreach($quickActions as $action)
            <a href="{{ $action['route'] }}" class="b2b-action-card b2b-action-card--{{ $action['tone'] }}">
                <span class="b2b-action-card__icon"><x-portal-icon :name="$action['iconName']" /></span>
                <span class="b2b-action-card__title">{{ $action['title'] }}</span>
                <span class="b2b-action-card__body">{{ $action['body'] }}</span>
            </a>
        @endforeach
    </section>

    <div class="b2b-workspace-grid">
        <section class="b2b-panel-stack">
            <x-card title="نبضة تنفيذية">
                <div class="b2b-analytics-grid">
                    <article class="b2b-trend-panel">
                        <div class="b2b-panel-kicker">اتجاه حديث</div>
                        <div class="b2b-panel-title">حركة الشحن خلال آخر سبعة أيام</div>
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
                        <div class="b2b-panel-kicker">توزيع الحالة</div>
                        <div class="b2b-panel-title">مزيج الشحنات الحالية</div>
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

            <x-card title="فريق المنظمة">
                <div class="b2b-inline-metrics">
                    @foreach($teamSnapshot as $item)
                        <div class="b2b-inline-metric b2b-inline-metric--{{ $item['tone'] }}">
                            <span class="b2b-inline-metric__label">{{ $item['label'] }}</span>
                            <strong class="b2b-inline-metric__value">{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="b2b-funnel">
                    @foreach($fulfillmentFunnel as $step)
                        <div class="b2b-funnel__step">
                            <div class="b2b-funnel__label">{{ $step['label'] }}</div>
                            <div class="b2b-funnel__value">{{ $step['value'] }}</div>
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card title="آخر الشحنات">
                <div class="b2b-stream-list">
                    @forelse($recentShipments as $shipment)
                        <a href="{{ route('b2b.shipments.show', ['id' => $shipment->id]) }}" class="b2b-stream-row">
                            <div class="b2b-stream-row__main">
                                <div class="b2b-stream-row__title">{{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}</div>
                                <div class="b2b-stream-row__meta">
                                    إلى {{ collect([$shipment->recipient_name, $shipment->recipient_city, $shipment->recipient_country])->filter()->implode(' / ') ?: 'وجهة غير محددة' }}
                                </div>
                            </div>
                            <div class="b2b-stream-row__side">
                                <span class="b2b-status-pill b2b-status-pill--{{ $shipmentTone($shipment->status) }}">{{ $resolveShipmentStatus($shipment->status) }}</span>
                                <span class="b2b-stream-row__date">{{ optional($shipment->updated_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</span>
                            </div>
                        </a>
                    @empty
                        <div class="b2b-empty-card">
                            <div class="b2b-empty-card__title">لا توجد شحنات بعد</div>
                            <p class="b2b-empty-card__body">ابدأ أول طلب شحنة لفريقك حتى يتحول هذا القسم إلى سجل تشغيلي حي يمكن الرجوع إليه بسرعة.</p>
                            <a href="{{ route('b2b.shipments.create') }}" class="btn btn-pr">بدء طلب شحنة لفريقك</a>
                        </div>
                    @endforelse
                </div>
            </x-card>
        </section>

        <aside class="b2b-rail">
            <x-card title="الطلبات التي وصلت حديثاً">
                <div class="b2b-stream-list">
                    @forelse($recentOrders as $order)
                        <div class="b2b-stream-row b2b-stream-row--compact">
                            <div class="b2b-stream-row__main">
                                <div class="b2b-stream-row__title">{{ $order->external_order_number ?? $order->external_order_id ?? $order->id }}</div>
                                <div class="b2b-stream-row__meta">{{ $order->store?->name ?? 'متجر غير محدد' }}</div>
                            </div>
                            <div class="b2b-stream-row__side">
                                <span class="b2b-status-pill b2b-status-pill--neutral">{{ $orderStatusLabel($order->status) }}</span>
                                <span class="b2b-stream-row__date">{{ number_format((float) ($order->total_amount ?? 0), 2) }} {{ $order->currency ?? 'SAR' }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="b2b-inline-empty">لا توجد طلبات حديثة حتى الآن. عند وصول طلبات من متاجرك ستظهر هنا لتسهيل الفرز السريع.</div>
                    @endforelse
                </div>
                <a href="{{ route('b2b.orders.index') }}" class="btn btn-s">فتح مساحة الطلبات</a>
            </x-card>

            <x-card title="المحفظة في لمحة">
                <div class="b2b-wallet-card {{ $wallet ? '' : 'b2b-wallet-card--empty' }}">
                    <div class="b2b-wallet-card__label">الرصيد القابل للاستخدام</div>
                    <div class="b2b-wallet-card__value">
                        {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }}
                        <span>{{ $wallet->currency ?? 'SAR' }}</span>
                    </div>
                    <div class="b2b-wallet-card__meta">
                        @if($wallet)
                            الرصيد المحجوز حالياً {{ number_format((float) ($wallet->reserved_balance ?? 0), 2) }} {{ $wallet->currency ?? 'SAR' }}
                        @else
                            لا توجد محفظة مفعلة لهذا الحساب بعد.
                        @endif
                    </div>
                </div>

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
                        <div class="b2b-inline-empty">لا توجد حجوزات مالية نشطة الآن. هذا مؤشر جيد إذا كان الفريق يعمل على شحنات منخفضة المخاطر المالية.</div>
                    @endforelse
                </div>
            </x-card>

            @if($developerSummary['visible'])
                @php($developerEntryRoute = $developerTools->first()['route'] ?? 'b2b.developer.index')
                <x-card title="أدوات المطور">
                    <div class="b2b-section-copy">
                        <div class="b2b-section-kicker">واجهة المطور</div>
                        <p>
                            هذه الأدوات مخصصة لتكامل المنظمة مع المنصة فقط. لا تعبّر عن ملكية الناقلين أو إعداد عقودهم، بل عن مفاتيح API والويبهوكات وتكاملات المنصة المتاحة لدورك الحالي.
                        </p>
                    </div>

                    <div class="b2b-inline-metrics">
                        @foreach($developerSummary['stats'] as $item)
                            <div class="b2b-inline-metric b2b-inline-metric--{{ $item['tone'] }}">
                                <span class="b2b-inline-metric__label">{{ $item['label'] }}</span>
                                <strong class="b2b-inline-metric__value">{{ $item['value'] }}</strong>
                            </div>
                        @endforeach
                    </div>

                    <div class="b2b-tool-list">
                        @foreach($developerSummary['tools'] as $tool)
                            <a href="{{ route($tool['route']) }}" class="b2b-tool-link">
                                <span>{{ $tool['label'] }}</span>
                                <small>{{ $tool['description'] }}</small>
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ route($developerEntryRoute) }}" class="btn btn-s">فتح واجهة المطور</a>
                </x-card>
            @endif
        </aside>
    </div>
</div>
@endsection
