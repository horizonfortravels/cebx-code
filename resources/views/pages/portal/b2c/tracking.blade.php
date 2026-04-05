@extends('layouts.app')
@section('title', 'بوابة الأفراد | التتبع')

@section('content')
@php
    $matchedTimeline = $matchedTimeline ?? null;
    $timelineItems = collect($matchedTimeline['events'] ?? [])
        ->map(static function (array $event): array {
            return [
                'title' => (string) ($event['event_type_label'] ?? $event['description'] ?? 'تحديث شحنة'),
                'date' => ! empty($event['event_time'])
                    ? \Illuminate\Support\Carbon::parse($event['event_time'])->format('Y-m-d H:i')
                    : 'غير محدد',
                'location' => (string) ($event['location_label'] ?? $event['location'] ?? ''),
                'desc' => (string) ($event['description'] ?? ''),
            ];
        })
        ->all();
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
            \App\Models\Shipment::STATUS_REQUIRES_ACTION => 'danger',
            \App\Models\Shipment::STATUS_IN_TRANSIT,
            \App\Models\Shipment::STATUS_OUT_FOR_DELIVERY,
            \App\Models\Shipment::STATUS_READY_FOR_PICKUP,
            \App\Models\Shipment::STATUS_PICKED_UP,
            \App\Models\Shipment::STATUS_PURCHASED => 'info',
            default => 'warning',
        };
    };
@endphp

<x-page-header
    eyebrow="بوابة الأفراد"
    title="تتبع شحناتك بسهولة"
    subtitle="ابحث برقم التتبع أو المرجع، ثم راجع آخر حالة ومسار الرحلة الزمني بدون الحاجة إلى التنقل بين صفحات كثيرة."
    meta="هذا البحث يعرض فقط الشحنات المرتبطة بحسابك الفردي الحالي."
>
    <a href="{{ route('b2c.shipments.index') }}" class="btn btn-s">عرض الشحنات</a>
    <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">إنشاء شحنة</a>
</x-page-header>

<x-card title="ابحث الآن">
    <form method="GET" action="{{ route('b2c.tracking.index') }}" class="b2c-tracking-search">
        <div class="b2c-tracking-search__field">
            <label for="tracking-query" class="b2c-form-label">رقم التتبع أو المرجع</label>
            <input
                id="tracking-query"
                type="text"
                name="q"
                value="{{ $searchQuery }}"
                placeholder="مثال: SHP-20260001 أو رقم تتبع الناقل"
                class="form-input"
            >
        </div>
        <button type="submit" class="btn btn-pr">بحث</button>
    </form>

    @if($trackedShipments->isNotEmpty())
        <div class="b2c-search-suggestions">
            @foreach($trackedShipments->take(4) as $shipment)
                <a
                    href="{{ route('b2c.tracking.index', ['q' => $shipment->tracking_number ?: $shipment->reference_number]) }}"
                    class="b2c-search-suggestions__item"
                >
                    {{ $shipment->tracking_number ?: $shipment->reference_number ?: $shipment->id }}
                </a>
            @endforeach
        </div>
    @endif
</x-card>

@if($matchedShipment)
    <div class="b2c-tracking-result-grid">
        <x-card title="نتيجة التتبع">
            <div class="b2c-result-summary">
                <div class="b2c-result-summary__row">
                    <span>رقم التتبع</span>
                    <strong class="td-mono">{{ $matchedShipment->tracking_number ?? $matchedShipment->carrier_tracking_number ?? 'غير متاح بعد' }}</strong>
                </div>
                <div class="b2c-result-summary__row">
                    <span>المرجع</span>
                    <strong>{{ $matchedShipment->reference_number ?? $matchedShipment->id }}</strong>
                </div>
                <div class="b2c-result-summary__row">
                    <span>الحالة الحالية</span>
                    <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($matchedShipment->status) }}">{{ $statusLabel($matchedShipment->status) }}</span>
                </div>
                <div class="b2c-result-summary__row">
                    <span>الوجهة</span>
                    <strong>{{ collect([$matchedShipment->recipient_city, $matchedShipment->recipient_country])->filter()->implode(' / ') ?: 'غير محددة' }}</strong>
                </div>
                <div class="b2c-result-summary__row">
                    <span>آخر تحديث</span>
                    <strong>{{ optional($matchedShipment->updated_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</strong>
                </div>
            </div>

            <div class="b2c-inline-actions">
                <a href="{{ route('b2c.shipments.show', ['id' => $matchedShipment->id]) }}" class="btn btn-pr">فتح صفحة الشحنة</a>
                <a href="{{ route('b2c.shipments.index') }}" class="btn btn-s">العودة إلى السجل</a>
            </div>
        </x-card>

        <x-card title="ملخص الرحلة">
            <div class="b2c-result-insight">
                <div class="b2c-result-insight__value">{{ number_format((int) ($matchedTimeline['total_events'] ?? 0)) }}</div>
                <div class="b2c-result-insight__label">محطات مسجلة</div>
                <p class="b2c-result-insight__body">
                    @if(!empty($matchedTimeline['last_updated']))
                        آخر تحديث وصل في {{ \Illuminate\Support\Carbon::parse($matchedTimeline['last_updated'])->format('Y-m-d H:i') }}.
                    @else
                        سنعرض آخر تحديث هنا عندما تصل أول إشارة تتبع من النظام أو من الناقل.
                    @endif
                </p>
            </div>
        </x-card>
    </div>

    <x-card title="المسار الزمني للشحنة">
        @if($timelineItems !== [])
            <x-timeline :items="$timelineItems" teal />
        @else
            <div class="b2c-empty-card b2c-empty-card--soft">
                <div class="b2c-empty-card__title">لا توجد محطات تتبع مفصلة بعد</div>
                <p class="b2c-empty-card__body">عندما تصل أول أحداث التتبع من النظام أو من الناقل سنعرضها هنا بخط زمني واضح، مع المكان والوصف والوقت.</p>
            </div>
        @endif
    </x-card>
@elseif($searchQuery !== '')
    <div class="b2c-empty-card">
        <div class="b2c-empty-card__title">لم نعثر على شحنة مطابقة</div>
        <p class="b2c-empty-card__body">تحقق من رقم التتبع أو المرجع، أو افتح سجل الشحنات لاختيار الشحنة الصحيحة من القائمة الحالية المرتبطة بحسابك.</p>
        <div class="b2c-inline-actions">
            <a href="{{ route('b2c.shipments.index') }}" class="btn btn-pr">فتح سجل الشحنات</a>
            <a href="{{ route('b2c.tracking.index') }}" class="btn btn-s">إعادة البحث</a>
        </div>
    </div>
@else
    <div class="b2c-empty-card b2c-empty-card--soft">
        <div class="b2c-empty-card__title">ابدأ البحث من رقم التتبع أو المرجع</div>
        <p class="b2c-empty-card__body">إذا كنت تتابع شحنة جارية، استخدم رقم التتبع أو المرجع كما وصلك عند الإصدار. سنعرض لك النتيجة مع الحالة الحالية والمسار الزمني مباشرة.</p>
    </div>
@endif

<x-card title="شحناتك الجاهزة للتتبع">
    <div class="b2c-activity-list">
        @forelse($trackedShipments as $shipment)
            <div class="b2c-activity-list__item">
                <div>
                    <div class="b2c-activity-list__title">{{ $shipment->tracking_number ?? $shipment->reference_number ?? $shipment->id }}</div>
                    <div class="b2c-activity-list__meta">{{ $shipment->reference_number ?: 'مرجع غير متاح' }}</div>
                </div>
                <div class="b2c-activity-list__side">
                    <span class="b2c-status-pill b2c-status-pill--{{ $statusTone($shipment->status) }}">{{ $statusLabel($shipment->status) }}</span>
                    <a href="{{ route('b2c.tracking.index', ['q' => $shipment->tracking_number ?: $shipment->reference_number]) }}" class="btn btn-s">عرض</a>
                </div>
            </div>
        @empty
            <div class="b2c-empty-card b2c-empty-card--soft">
                <div class="b2c-empty-card__title">لا توجد شحنات قابلة للتتبع بعد</div>
                <p class="b2c-empty-card__body">عند إصدار أول شحنة وصدور رقم التتبع ستبدأ هذه القائمة بالامتلاء تلقائيًا لتسهيل الوصول السريع.</p>
            </div>
        @endforelse
    </div>
</x-card>
@endsection
