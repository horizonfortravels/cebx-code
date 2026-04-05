@extends('layouts.app')
@section('title', 'بوابة الأفراد | الدعم')

@section('content')
<x-page-header
    eyebrow="بوابة الأفراد"
    title="الدعم والمساندة"
    subtitle="نعمل على توسيع مركز الدعم داخل بوابة الأفراد. وحتى يكتمل ذلك، أبقينا لك هذه الصفحة صادقة وواضحة بدل شاشة رقيقة أو غير مفيدة."
    meta="ستستمر الشحنات والمحفظة والتتبع بالعمل كالمعتاد أثناء تطوير مساحة الدعم."
>
    <a href="{{ route('b2c.tracking.index') }}" class="btn btn-s">تتبع شحنة</a>
    <a href="{{ route('b2c.shipments.index') }}" class="btn btn-pr">عرض الشحنات</a>
</x-page-header>

<div class="b2c-workspace-grid">
    <x-card title="ما الذي يمكنك فعله الآن؟">
        <div class="b2c-guidance-stack">
            <div class="b2c-guidance-card">
                <div class="b2c-guidance-card__title">راجع الشحنات التي تحتاج إلى انتباه</div>
                <div class="b2c-guidance-card__body">هناك {{ number_format($attentionCount) }} شحنة تحتاج إلى متابعة حاليًا. ابدأ من سجل الشحنات أو من التتبع للوصول إلى الشحنة الصحيحة بسرعة.</div>
            </div>
            <div class="b2c-guidance-card">
                <div class="b2c-guidance-card__title">افتح المحفظة قبل الإكمال</div>
                <div class="b2c-guidance-card__body">
                    @if($wallet)
                        الرصيد المتاح الآن هو {{ number_format((float) $wallet->available_balance, 2) }} {{ $wallet->currency ?? 'SAR' }}، ويمكنك مراجعته قبل الانتقال إلى الحجز المالي أو إصدار شحنة جديدة.
                    @else
                        إذا لم تكن المحفظة مفعلة بعد، فستبقى هذه الخطوة ظاهرة بوضوح حتى لا تفاجأ عند الوصول إلى الحجز المالي.
                    @endif
                </div>
            </div>
        </div>
    </x-card>

    <x-card title="حالة هذه المساحة الآن">
        <div class="b2c-empty-card b2c-empty-card--soft">
            <div class="b2c-empty-card__title">مركز الدعم داخل بوابة الأفراد قيد التوسعة</div>
            <p class="b2c-empty-card__body">لا نعرض هنا وعودًا أو أزرارًا مكسورة. سنضيف في مرحلة لاحقة طلبات الدعم والمحادثات والمتابعة، لكننا أبقينا الصفحة مفهومة حتى لا تبدو كأنها شاشة ناقصة.</p>
            <div class="b2c-inline-actions">
                <a href="{{ route('b2c.shipments.create') }}" class="btn btn-pr">إنشاء شحنة</a>
                <a href="{{ route('b2c.wallet.index') }}" class="btn btn-s">فتح المحفظة</a>
            </div>
        </div>

        <div class="b2c-placeholder-summary">
            <div class="b2c-placeholder-summary__item">
                <span>إجمالي الشحنات</span>
                <strong>{{ number_format($shipmentsCount) }}</strong>
            </div>
            <div class="b2c-placeholder-summary__item">
                <span>تحتاج متابعة</span>
                <strong>{{ number_format($attentionCount) }}</strong>
            </div>
        </div>
    </x-card>
</div>
@endsection
