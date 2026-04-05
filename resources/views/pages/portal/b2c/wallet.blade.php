@extends('layouts.app')
@section('title', 'بوابة الأفراد | المحفظة')

@section('content')
@php
    $activeHolds = $activeHolds ?? collect();
    $movementTone = static function (\App\Models\WalletLedgerEntry $entry): string {
        return $entry->isCredit() ? 'success' : 'danger';
    };
    $movementLabel = static function (\App\Models\WalletLedgerEntry $entry): string {
        if (filled($entry->description)) {
            return (string) $entry->description;
        }

        if (filled($entry->type)) {
            return $entry->typeLabel();
        }

        return 'حركة على المحفظة';
    };
    $holdTone = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\WalletHold::STATUS_ACTIVE => 'warning',
            \App\Models\WalletHold::STATUS_CAPTURED => 'info',
            default => 'neutral',
        };
    };
    $holdLabel = static function (?string $status): string {
        return match ((string) $status) {
            \App\Models\WalletHold::STATUS_ACTIVE => 'مبلغ محجوز',
            \App\Models\WalletHold::STATUS_CAPTURED => 'تم استخدامه للإصدار',
            \App\Models\WalletHold::STATUS_RELEASED => 'تم الإفراج',
            \App\Models\WalletHold::STATUS_EXPIRED => 'منتهي',
            default => 'غير محدد',
        };
    };
@endphp

<x-page-header
    eyebrow="بوابة الأفراد"
    title="محفظتك الشخصية"
    subtitle="راجع الرصيد المتاح، والحجوزات المالية، وآخر الحركات المرتبطة بالشحنات من شاشة واحدة مفهومة وواضحة."
    meta="حالة المحفظة: {{ $wallet ? ($wallet->isFrozen() ? 'متوقفة مؤقتًا' : 'نشطة') : 'غير مفعلة' }}"
>
    <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">إنشاء شحنة</a>
    <a href="{{ route('b2c.shipments.index') }}" class="btn btn-s">سجل الشحنات</a>
</x-page-header>

<section class="b2c-wallet-hero {{ $wallet ? '' : 'b2c-wallet-hero--empty' }}">
    <div class="b2c-wallet-hero__copy">
        <div class="b2c-wallet-hero__eyebrow">الرصيد المتاح الآن</div>
        <div class="b2c-wallet-hero__value">
            {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }}
            <span>{{ $wallet->currency ?? 'SAR' }}</span>
        </div>
        <p class="b2c-wallet-hero__body">
            @if($wallet)
                استخدم هذه الصفحة لفهم المبلغ الجاهز للحجز، والمبالغ المعلقة مؤقتًا على شحنات جارية، وآخر الحركات التي أثرت على الرصيد.
            @else
                لا توجد محفظة مفعلة لهذا الحساب بعد. سنعرض الرصيد والحركات والحجوزات هنا بمجرد تفعيل المحفظة.
            @endif
        </p>
    </div>

    <div class="b2c-wallet-hero__meta">
        <div class="b2c-wallet-hero__meta-item">
            <span>المبلغ المحجوز</span>
            <strong>{{ $wallet ? number_format((float) ($wallet->reserved_balance ?? 0), 2) : '0.00' }} {{ $wallet->currency ?? 'SAR' }}</strong>
        </div>
        <div class="b2c-wallet-hero__meta-item">
            <span>إجمالي الرصيد الممول</span>
            <strong>{{ $wallet ? number_format((float) ($wallet->total_credited ?? 0), 2) : '0.00' }} {{ $wallet->currency ?? 'SAR' }}</strong>
        </div>
        <div class="b2c-wallet-hero__meta-item">
            <span>عدد الحركات الأخيرة</span>
            <strong>{{ number_format($transactions->count()) }}</strong>
        </div>
        <div class="b2c-wallet-hero__meta-item">
            <span>عدد الحجوزات</span>
            <strong>{{ number_format($activeHolds->count()) }}</strong>
        </div>
    </div>
</section>

<div class="stats-grid b2c-metrics-grid">
    <x-stat-card iconSvg='
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 8.5A2.75 2.75 0 0 1 7.75 5.75h8.5A2.75 2.75 0 0 1 19 8.5v7A2.75 2.75 0 0 1 16.25 18.25h-8.5A2.75 2.75 0 0 1 5 15.5z"></path>
            <path d="M15.75 11.25h3.5v2.5h-3.5a1.25 1.25 0 1 1 0-2.5Z"></path>
            <path d="M8 8.5h6.75"></path>
        </svg>
    ' label="الرصيد المتاح" :value="$wallet ? number_format((float) $wallet->available_balance, 2) . ' ' . ($wallet->currency ?? 'SAR') : '0.00'" meta="الجزء الجاهز للحجز أو الاستخدام" eyebrow="المتاح" />
    <x-stat-card iconSvg='
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5.5v5"></path>
            <path d="M12 12.5v.01"></path>
            <path d="M5.75 7.75h12.5v10.5H5.75z"></path>
        </svg>
    ' label="المبالغ المحجوزة" :value="$wallet ? number_format((float) ($wallet->reserved_balance ?? 0), 2) . ' ' . ($wallet->currency ?? 'SAR') : '0.00'" meta="مرتبطة بإصدار شحنات جارية" eyebrow="الحجز" />
    <x-stat-card iconSvg='
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5.5v13"></path>
            <path d="M5.5 12h13"></path>
        </svg>
    ' label="الحركات الأخيرة" :value="number_format($transactions->count())" meta="أحدث الخصومات أو الشحن أو الاسترداد" eyebrow="الحركة" />
    <x-stat-card iconSvg='
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 4.75 18 7v4.75c0 3.46-2.27 6.59-6 7.5-3.73-.91-6-4.04-6-7.5V7z"></path>
            <path d="m9.5 12 1.75 1.75L14.75 10"></path>
        </svg>
    ' label="حالة المحفظة" :value="$wallet ? ($wallet->isFrozen() ? 'متوقفة مؤقتًا' : 'نشطة') : 'غير مفعلة'" :meta="$wallet && $wallet->isLowBalance() ? 'الرصيد تحت حد التنبيه الحالي' : 'لا توجد ملاحظات تشغيلية على المحفظة'" eyebrow="الحالة" />
</div>

<div class="b2c-workspace-grid">
    <x-card title="أحدث الحركات المالية">
        <div class="b2c-activity-list">
            @forelse($transactions as $entry)
                <div class="b2c-activity-list__item">
                    <div>
                        <div class="b2c-activity-list__title">{{ $movementLabel($entry) }}</div>
                        <div class="b2c-activity-list__meta">{{ optional($entry->created_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</div>
                    </div>
                    <div class="b2c-activity-list__side">
                        <span class="b2c-status-pill b2c-status-pill--{{ $movementTone($entry) }}">
                            {{ $entry->isCredit() ? 'إضافة' : 'خصم' }}
                        </span>
                        <strong class="b2c-activity-list__amount {{ $entry->isCredit() ? 'is-positive' : 'is-negative' }}">
                            {{ number_format((float) $entry->amount, 2) }} {{ $wallet->currency ?? 'SAR' }}
                        </strong>
                    </div>
                </div>
            @empty
                <div class="b2c-empty-card b2c-empty-card--soft">
                    <div class="b2c-empty-card__title">لا توجد حركات مالية بعد</div>
                    <p class="b2c-empty-card__body">عند أول خصم أو شحن رصيد أو استرداد ستظهر التفاصيل هنا بترتيب واضح يساعدك على مراجعة الرصيد وفهم ما حدث.</p>
                </div>
            @endforelse
        </div>
    </x-card>

    <div class="b2c-panel-stack">
        <x-card title="الحجوزات والالتزامات الحالية">
            <div class="b2c-hold-list">
                @forelse($activeHolds as $hold)
                    <div class="b2c-hold-list__item">
                        <div>
                            <div class="b2c-hold-list__title">{{ $hold->shipment?->reference_number ?? $hold->shipment?->tracking_number ?? 'حجز مرتبط بمحفظتك' }}</div>
                            <div class="b2c-hold-list__meta">{{ optional($hold->created_at)->format('Y-m-d H:i') ?? 'غير محدد' }}</div>
                        </div>
                        <div class="b2c-hold-list__side">
                            <span class="b2c-status-pill b2c-status-pill--{{ $holdTone($hold->status) }}">{{ $holdLabel($hold->status) }}</span>
                            <strong>{{ number_format((float) $hold->amount, 2) }} {{ $hold->currency ?? ($wallet->currency ?? 'SAR') }}</strong>
                        </div>
                    </div>
                @empty
                    <div class="b2c-inline-empty">لا توجد حجوزات نشطة على الرصيد الآن. عندما تمر شحنة عبر خطوة الحجز المالي سنعرضها هنا فورًا.</div>
                @endforelse
            </div>
        </x-card>

        <x-card title="إرشادات سريعة">
            <div class="b2c-guidance-stack">
                <div class="b2c-guidance-card">
                    <div class="b2c-guidance-card__title">راجع الرصيد قبل الإصدار</div>
                    <div class="b2c-guidance-card__body">عندما تصل الشحنة إلى خطوة الحجز المالي سيُستخدم الرصيد المتاح أولًا، لذلك من المفيد مراجعة المبلغ المحجوز والمتاح قبل إصدار شحنة جديدة.</div>
                </div>
                <div class="b2c-guidance-card {{ $wallet && $wallet->isLowBalance() ? 'b2c-guidance-card--accent' : '' }}">
                    <div class="b2c-guidance-card__title">انتبه للتنبيهات</div>
                    <div class="b2c-guidance-card__body">
                        @if($wallet && $wallet->isFrozen())
                            المحفظة الحالية متوقفة مؤقتًا، وقد تتعطل بعض خطوات الشحنة حتى تعود إلى الحالة النشطة.
                        @elseif($wallet && $wallet->isLowBalance())
                            الرصيد أقل من حد التنبيه المضبوط. من الأفضل مراجعة المحفظة قبل محاولة إصدار شحنة جديدة.
                        @else
                            لا توجد تنبيهات تشغيلية على المحفظة حاليًا، ويمكنك متابعة استخدام الرصيد بصورة طبيعية.
                        @endif
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</div>
@endsection
