@extends('layouts.app')
@section('title', 'بوابة الأفراد | الرئيسية')

@php
    $displayName = trim((string) ($currentUser->name ?? $currentUser->email ?? 'عميلنا'));
    $statusLabel = static function (?string $status): string {
        $resolved = trim((string) $status);
        if ($resolved === '') {
            return 'غير محدد';
        }

        $key = 'portal_shipments.statuses.' . $resolved;
        $translated = __($key);

        return $translated === $key ? $resolved : $translated;
    };
    $statusTone = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\Shipment::STATUS_DELIVERED => 'success',
            \App\Models\Shipment::STATUS_EXCEPTION,
            \App\Models\Shipment::STATUS_FAILED,
            \App\Models\Shipment::STATUS_REQUIRES_ACTION,
            \App\Models\Shipment::STATUS_KYC_BLOCKED => 'danger',
            \App\Models\Shipment::STATUS_IN_TRANSIT,
            \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY,
            \App\Models\Shipment::STATUS_READY_FOR_PICKUP,
            \App\Models\Shipment::STATUS_PICKED_UP,
            \App\Models\Shipment::STATUS_PURCHASED => 'info',
            default => 'warning',
        };
    };
    $metricIcons = [
        'total' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M4.75 8.5 12 4.75l7.25 3.75v7L12 19.25l-7.25-3.75z"></path>
    <path d="M12 10.25 4.75 6.5"></path>
    <path d="M12 10.25 19.25 6.5"></path>
    <path d="M12 10.25v9"></path>
</svg>
SVG,
        'active' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 5.5v6.5l4 2.5"></path>
    <circle cx="12" cy="12" r="8.25"></circle>
</svg>
SVG,
        'delivered' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5.25 12.5 9.5 16.75 18.75 7.5"></path>
</svg>
SVG,
        'attention' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="m12 4.75 7 12.5a1 1 0 0 1-.87 1.5H5.87a1 1 0 0 1-.87-1.5z"></path>
    <path d="M12 9v4.5"></path>
    <path d="M12 16.5h.01"></path>
</svg>
SVG,
        'wallet' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M5 8.5A2.75 2.75 0 0 1 7.75 5.75h8.5A2.75 2.75 0 0 1 19 8.5v7A2.75 2.75 0 0 1 16.25 18.25h-8.5A2.75 2.75 0 0 1 5 15.5z"></path>
    <path d="M15.75 11.25h3.5v2.5h-3.5a1.25 1.25 0 1 1 0-2.5Z"></path>
    <path d="M8 8.5h6.75"></path>
</svg>
SVG,
        'create' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 5.5v13"></path>
    <path d="M5.5 12h13"></path>
</svg>
SVG,
        'continue' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="m8.5 7.5 7 4.5-7 4.5z"></path>
</svg>
SVG,
        'tracking' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="11" cy="11" r="6.25"></circle>
    <path d="M15.5 15.5 19.25 19.25"></path>
    <path d="M11 8.25v3.25l2.25 1.5"></path>
</svg>
SVG,
        'addresses' => <<<'SVG'
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
    <path d="M12 20.25s6-5.52 6-10a6 6 0 1 0-12 0c0 4.48 6 10 6 10Z"></path>
    <circle cx="12" cy="10.25" r="2.25"></circle>
</svg>
SVG,
    ];
@endphp

@section('content')
<x-page-header
    eyebrow="بوابة الأفراد"
    title="منزلك الشخصي للشحن"
    subtitle="ابدأ شحنة جديدة، أكمل الشحنات غير المكتملة، وراجع الرصيد والتتبع من مساحة واحدة أخف وأوضح للحساب الفردي."
    :meta="'الحساب الحالي: ' . ($account->name ?? 'الحساب الفردي')"
>
    <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">إنشاء شحنة</a>
    <a href="{{ route('b2c.tracking.index') }}" class="btn btn-s">تتبع شحنة</a>
</x-page-header>

<div class="stats-grid b2c-metrics-grid">
    @foreach($stats as $stat)
        <x-stat-card
            :iconSvg="$metricIcons[$stat['key']] ?? null"
            :label="$stat['label']"
            :value="$stat['value']"
            :meta="$stat['meta']"
            :eyebrow="$stat['eyebrow']"
        />
    @endforeach
</div>

<div class="b2c-home-grid">
    <section class="b2c-home-hero">
        <div class="b2c-home-hero__eyebrow">مرحبًا بك</div>
        <h2 class="b2c-home-hero__title">{{ $displayName }}</h2>
        <p class="b2c-home-hero__body">
            هذه الصفحة هي نقطة البداية اليومية لك: منها تنشئ شحنة جديدة، أو تعود إلى آخر شحنة تحتاج منك خطوة، أو تراجع المحفظة والتتبع بدون ضجيج إضافي.
        </p>

        <div class="b2c-summary-pills">
            @foreach($summaryPills as $pill)
                <div class="b2c-summary-pill b2c-summary-pill--{{ $pill['tone'] }}">
                    <span class="b2c-summary-pill__label">{{ $pill['label'] }}</span>
                    <strong class="b2c-summary-pill__value">{{ $pill['value'] }}</strong>
                </div>
            @endforeach
            <div class="b2c-summary-pill b2c-summary-pill--neutral">
                <span class="b2c-summary-pill__label">العناوين الجاهزة</span>
                <strong class="b2c-summary-pill__value">{{ number_format($addressCount) }}</strong>
            </div>
        </div>

        <div class="b2c-quick-actions">
            <a href="{{ route('b2c.shipments.create') }}" class="b2c-action-card">
                <span class="b2c-action-card__icon">{!! $metricIcons['create'] !!}</span>
                <span class="b2c-action-card__title">إنشاء شحنة جديدة</span>
                <span class="b2c-action-card__body">ابدأ الطلب من الصفر مع نفس لغة التحقق والعروض التي تراها في بقية البوابة.</span>
            </a>

            @if($continueShipment && $continueAction)
                <a href="{{ $continueAction['url'] }}" class="b2c-action-card b2c-action-card--accent">
                    <span class="b2c-action-card__icon">{!! $metricIcons['continue'] !!}</span>
                    <span class="b2c-action-card__title">استئناف آخر شحنة</span>
                    <span class="b2c-action-card__body">
                        {{ $continueAction['helper'] }}
                        <strong>{{ $continueShipment->reference_number ?? $continueShipment->tracking_number ?? $continueShipment->id }}</strong>
                    </span>
                </a>
            @else
                <div class="b2c-action-card b2c-action-card--muted">
                    <span class="b2c-action-card__icon">{!! $metricIcons['continue'] !!}</span>
                    <span class="b2c-action-card__title">استئناف شحنة جارية</span>
                    <span class="b2c-action-card__body">لا توجد شحنة غير مكتملة الآن. عندما تبدأ شحنة وتغادرها قبل الإنهاء ستظهر هنا مباشرة.</span>
                </div>
            @endif

            <a href="{{ route('b2c.tracking.index') }}" class="b2c-action-card">
                <span class="b2c-action-card__icon">{!! $metricIcons['tracking'] !!}</span>
                <span class="b2c-action-card__title">تتبع شحنة</span>
                <span class="b2c-action-card__body">ابحث برقم التتبع أو المرجع وراجع آخر محطات الرحلة بدون الحاجة لفتح كل السجل.</span>
            </a>

            <a href="{{ route('b2c.wallet.index') }}" class="b2c-action-card">
                <span class="b2c-action-card__icon">{!! $metricIcons['wallet'] !!}</span>
                <span class="b2c-action-card__title">فتح المحفظة</span>
                <span class="b2c-action-card__body">راجع الرصيد المتاح والمبالغ المحجوزة قبل الوصول إلى الحجز المالي أو إصدار شحنة جديدة.</span>
            </a>
        </div>
    </section>

    <div class="b2c-home-side">
        @foreach($dashboardNotices as $notice)
            <article class="b2c-notice b2c-notice--{{ $notice['tone'] }}">
                <div class="b2c-notice__title">{{ $notice['title'] }}</div>
                <p class="b2c-notice__body">{{ $notice['body'] }}</p>
            </article>
        @endforeach

        <x-card title="المحفظة في لمحة">
            <div class="b2c-wallet-spotlight {{ $wallet ? '' : 'b2c-wallet-spotlight--empty' }}">
                <div class="b2c-wallet-spotlight__label">الرصيد المتاح الآن</div>
                <div class="b2c-wallet-spotlight__value">
                    {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }}
                    <span>{{ $wallet->currency ?? 'SAR' }}</span>
                </div>
                <div class="b2c-wallet-spotlight__meta">
                    @if($wallet)
                        المبلغ المحجوز حاليًا: {{ number_format((float) ($wallet->reserved_balance ?? 0), 2) }} {{ $wallet->currency ?? 'SAR' }}
                    @else
                        لا توجد محفظة مفعلة بعد لهذا الحساب.
                    @endif
                </div>
            </div>

            <div class="b2c-mini-list">
                @forelse($transactions->take(3) as $transaction)
                    <div class="b2c-mini-list__item">
                        <div>
                            <div class="b2c-mini-list__title">{{ $transaction->description ?? $transaction->typeLabel() }}</div>
                            <div class="b2c-mini-list__meta">{{ optional($transaction->created_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</div>
                        </div>
                        <div class="b2c-mini-list__amount {{ $transaction->isCredit() ? 'is-positive' : 'is-negative' }}">
                            {{ number_format((float) $transaction->amount, 2) }} {{ $wallet->currency ?? 'SAR' }}
                        </div>
                    </div>
                @empty
                    <div class="b2c-inline-empty">
                        لم تُسجل أي حركة مالية بعد. ستظهر عمليات الشحن والخصم والاسترداد هنا عندما تبدأ باستخدام المحفظة.
                    </div>
                @endforelse
            </div>

            <a href="{{ route('b2c.wallet.index') }}" class="btn btn-s">فتح صفحة المحفظة</a>
        </x-card>
    </div>
</div>

<div class="b2c-home-lower">
    <x-card title="آخر الشحنات">
        <div class="b2c-shipment-list">
            @forelse($recentShipments as $shipment)
                <a href="{{ route('b2c.shipments.show', ['id' => $shipment->id]) }}" class="b2c-shipment-row">
                    <div class="b2c-shipment-row__main">
                        <div class="b2c-shipment-row__title">
                            {{ $shipment->reference_number ?? $shipment->tracking_number ?? $shipment->id }}
                        </div>
                        <div class="b2c-shipment-row__meta">
                            إلى {{ collect([$shipment->recipient_city, $shipment->recipient_country])->filter()->implode(' / ') ?: 'وجهة غير محددة' }}
                        </div>
                    </div>
                    <div class="b2c-shipment-row__side">
                        <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($shipment->status) }}">{{ $statusLabel($shipment->status) }}</span>
                        <span class="b2c-shipment-row__date">{{ optional($shipment->updated_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</span>
                    </div>
                </a>
            @empty
                <div class="b2c-empty-card">
                    <div class="b2c-empty-card__title">لا توجد شحنات بعد</div>
                    <p class="b2c-empty-card__body">ابدأ أول شحنة من هذا المنزل الشخصي، وسنحوّلها لاحقًا إلى سجل منظم وتتبّع زمني واضح داخل نفس المساحة.</p>
                    <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">ابدأ أول شحنة</a>
                </div>
            @endforelse
        </div>
    </x-card>

    <x-card title="أقرب خطوة لك">
        @if($continueShipment && $continueAction)
            <div class="b2c-focus-card">
                <div class="b2c-focus-card__eyebrow">الشحنة الأقرب للإكمال</div>
                <div class="b2c-focus-card__title">{{ $continueShipment->reference_number ?? $continueShipment->tracking_number ?? $continueShipment->id }}</div>
                <p class="b2c-focus-card__body">{{ $continueAction['helper'] }}</p>
                <div class="b2c-focus-card__meta">
                    الحالة الحالية: <strong>{{ $statusLabel($continueShipment->status) }}</strong>
                </div>
                <div class="b2c-focus-card__actions">
                    <a href="{{ $continueAction['url'] }}" class="btn btn-pr">{{ $continueAction['label'] }}</a>
                    <a href="{{ route('b2c.shipments.show', ['id' => $continueShipment->id]) }}" class="btn btn-s">عرض الشحنة</a>
                </div>
            </div>
        @else
            <div class="b2c-empty-card b2c-empty-card--soft">
                <div class="b2c-empty-card__title">لا توجد خطوة معلقة الآن</div>
                <p class="b2c-empty-card__body">عندما تبدأ شحنة وتغادرها قبل الإنهاء أو يصل التتبع إلى نقطة تحتاج منك قرارًا، سنضع الخطوة التالية هنا بشكل واضح ومباشر.</p>
                <div class="b2c-inline-actions">
                    <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">إنشاء شحنة</a>
                    <a href="{{ route('b2c.shipments.index') }}" class="btn btn-s">عرض الشحنات</a>
                </div>
            </div>
        @endif
    </x-card>
</div>
@endsection
