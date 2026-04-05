@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | مقارنة عروض الشحن')

@section('content')
@php
    $shipmentStatusLabels = [
        'draft' => 'مسودة',
        'validated' => 'تم التحقق من البيانات',
        'kyc_blocked' => 'موقوف بسبب التحقق أو القيود',
        'ready_for_rates' => 'جاهز لطلب العروض',
        'rated' => 'تم تجهيز العروض',
        'offer_selected' => 'تم تثبيت العرض',
        'declaration_required' => 'إقرار المحتوى مطلوب',
        'declaration_complete' => 'اكتمل إقرار المحتوى',
        'requires_action' => 'تتطلب هذه الشحنة إجراءً إضافيًا',
    ];
    $shipmentStatusLabel = $shipmentStatusLabels[$shipment->status] ?? $shipment->status;
    $offers = data_get($offersPayload, 'offers', []);
    $hasOffers = count($offers) > 0;
    $isExpired = (bool) data_get($offersPayload, 'is_expired', false);
    $selectedOptionId = $selectedOptionId !== '' ? $selectedOptionId : (string) data_get($offersPayload, 'selected_rate_option_id', '');
    $quoteStatusLabels = ['pending' => 'قيد الانتظار', 'completed' => 'مكتمل', 'expired' => 'منتهي', 'failed' => 'فشل الجلب'];
    $quoteStatus = data_get($offersPayload, 'quote_status', $hasOffers ? 'completed' : 'pending');
    $localizedQuoteStatus = $quoteStatusLabels[$quoteStatus] ?? $quoteStatus;
    $quoteExpiryLabel = data_get($offersPayload, 'expires_at') ? \Illuminate\Support\Carbon::parse(data_get($offersPayload, 'expires_at'))->format('Y-m-d H:i') : 'غير متاح بعد';
    $documentsAvailable = $shipment->carrierDocuments()->where('is_available', true)->exists();
    $stepStateOverrides = $selectedOptionId !== '' || in_array($shipment->status, ['declaration_required', 'declaration_complete', 'requires_action'], true)
        ? ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'current']
        : ['create' => 'complete', 'offers' => $isExpired ? 'attention' : 'current'];
    $offerEmptyMessage = (string) ($offerError['message'] ?? 'لم يتم توليد عروض لهذه الشحنة بعد. يمكنك طلب العروض عندما تصبح الشحنة جاهزة لطلب العروض.');
    $offerEmptyNextAction = (string) data_get($offerError, 'next_action', '');
    if ($offerEmptyMessage === 'No offers are available for this shipment yet.') {
        $offerEmptyMessage = 'لا توجد عروض متاحة لهذه الشحنة حتى الآن.';
    }
    if ($offerEmptyNextAction === 'Fetch shipment rates after the shipment reaches the ready_for_rates stage.') {
        $offerEmptyNextAction = 'اطلب عروض الشحنة بعد وصولها إلى مرحلة جاهز لطلب العروض.';
    }
@endphp

<div class="shipment-flow-stack">
    <x-page-header
        :eyebrow="($portalConfig['label'] ?? 'البوابة') . ' / الشحنات / العروض'"
        title="مقارنة عروض الشحن"
        subtitle="قارن بين الخدمة والسعر والتوقيت والملاحظات التشغيلية قبل تثبيت عرض واحد فقط للمتابعة."
        meta="هذه المرحلة توازن بين السرعة والتكلفة والقيود الفعلية على نفس الشحنة."
    >
        <a href="{{ route($portalConfig['create_route'], ['draft' => $shipment->id]) }}" class="btn btn-s">العودة إلى المسودة</a>
        @if($selectedOptionId !== '' || in_array($shipment->status, ['declaration_required', 'declaration_complete', 'requires_action'], true))
            <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">الانتقال إلى إقرار المحتوى</a>
        @endif
        @if($documentsAvailable)
            <a href="{{ route($portalConfig['documents_route'], ['id' => $shipment->id]) }}" class="btn btn-s">عرض الوثائق</a>
        @endif
        @if($canRefreshOffers)
            <form method="POST" action="{{ route($portalConfig['offers_fetch_route'], ['id' => $shipment->id]) }}">
                @csrf
                <button type="submit" class="btn btn-pr">{{ $hasOffers ? 'تحديث العروض' : 'جلب العروض الآن' }}</button>
            </form>
        @endif
    </x-page-header>

    <x-shipment-workflow-stepper
        current="offers"
        :create-route="route($portalConfig['create_route'], ['draft' => $shipment->id])"
        :offers-route="route($portalConfig['offers_route'], ['id' => $shipment->id])"
        :declaration-route="route($portalConfig['declaration_route'], ['id' => $shipment->id])"
        :show-route="route($portalConfig['show_route'], ['id' => $shipment->id])"
        :documents-route="route($portalConfig['documents_route'], ['id' => $shipment->id])"
        :state-overrides="$stepStateOverrides"
    />

    @if($offerFeedback)
        @php
            $offerFeedbackSuccess = ($offerFeedback['level'] ?? 'warning') === 'success';
        @endphp
        <div class="shipment-flow-banner {{ $offerFeedbackSuccess ? 'shipment-flow-banner--success' : 'shipment-flow-banner--warning' }}">
            <div class="shipment-flow-banner__title">{{ $offerFeedback['message'] ?? 'تم تحديث حالة العروض.' }}</div>
            @if(!empty($offerFeedback['next_action']))
                <div class="shipment-flow-banner__body"><strong>الخطوة التالية:</strong> {{ $offerFeedback['next_action'] }}</div>
            @endif
            @if(!empty($offerFeedback['error_code']))
                <div class="shipment-flow-banner__meta td-mono">{{ $offerFeedback['error_code'] }}</div>
            @endif
        </div>
    @endif

    <section class="shipment-flow-hero">
        <div class="shipment-flow-hero__head">
            <div>
                <div class="shipment-flow-hero__eyebrow">مرحلة المقارنة والاختيار</div>
                <h2 class="shipment-flow-hero__title">قارن العروض كما لو أنك تختار خطة تشغيل</h2>
                <p class="shipment-flow-hero__body">كل عرض هنا يعكس خدمة حقيقية مرتبطة بهذه الشحنة. راجع السعر المعروض والقيود والتوقيت ثم ثبّت الخيار الذي يناسبك قبل الانتقال إلى التصريح القانوني.</p>
            </div>
            <span class="shipment-status-pill shipment-status-pill--{{ $selectedOptionId !== '' ? 'success' : ($isExpired ? 'danger' : 'info') }}">{{ $selectedOptionId !== '' ? 'تم اختيار عرض' : ($isExpired ? 'العروض منتهية' : 'جاهز للمقارنة') }}</span>
        </div>
        <div class="shipment-flow-summary-grid">
            <div class="shipment-summary-card shipment-summary-card--soft"><div class="shipment-summary-card__eyebrow">مرجع الشحنة</div><div class="shipment-summary-card__value td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div><div class="shipment-summary-card__meta">{{ $shipmentStatusLabel }}</div></div>
            <div class="shipment-summary-card shipment-summary-card--accent"><div class="shipment-summary-card__eyebrow">حالة عرض الأسعار</div><div class="shipment-summary-card__value">{{ $localizedQuoteStatus }}</div><div class="shipment-summary-card__meta">الصلاحية حتى {{ $quoteExpiryLabel }}</div></div>
            <div class="shipment-summary-card {{ $selectedOptionId !== '' ? 'shipment-summary-card--success' : 'shipment-summary-card--warning' }}"><div class="shipment-summary-card__eyebrow">العرض المثبت</div><div class="shipment-summary-card__value td-mono">{{ $selectedOptionId !== '' ? $selectedOptionId : 'لم يتم الاختيار بعد' }}</div><div class="shipment-summary-card__meta">{{ $hasOffers ? 'قارن بين جميع الخيارات قبل التثبيت.' : 'ستظهر المقارنة هنا بعد جلب العروض.' }}</div></div>
        </div>
    </section>

    <div class="shipment-flow-layout">
        <div class="shipment-flow-stack">
            @if(!$hasOffers)
                <section class="shipment-empty-state">
                    <div class="shipment-empty-state__title">{{ $offerError ? 'الشحنة ليست جاهزة لعرض الأسعار بعد' : 'لا توجد عروض متاحة حاليًا' }}</div>
                    <div class="shipment-empty-state__body">{{ $offerEmptyMessage }}</div>
                    @if($offerEmptyNextAction !== '')
                        <div class="shipment-inline-meta"><strong>الإجراء المقترح:</strong> {{ $offerEmptyNextAction }}</div>
                    @endif
                    <div class="shipment-form-actions">
                        @if($canRefreshOffers)
                            <form method="POST" action="{{ route($portalConfig['offers_fetch_route'], ['id' => $shipment->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-pr">جلب العروض لهذه الشحنة</button>
                            </form>
                        @endif
                        <a href="{{ route($portalConfig['create_route'], ['draft' => $shipment->id]) }}" class="btn btn-s">مراجعة بيانات المسودة</a>
                    </div>
                </section>
            @else
                <section class="shipment-offers-grid">
                    @foreach($offers as $offer)
                        @php
                            $isSelected = (bool) ($offer['is_selected'] ?? false);
                            $isAvailable = (bool) ($offer['is_available'] ?? true);
                            $deliveryLabel = data_get($offer, 'estimated_delivery.label') ?: 'غير متوفر';
                            $carrierLabel = \App\Support\PortalShipmentLabeler::carrier((string) ($offer['carrier_code'] ?? ''), (string) ($offer['carrier_name'] ?? ''));
                            $serviceLabel = \App\Support\PortalShipmentLabeler::service((string) ($offer['service_code'] ?? ''), (string) ($offer['service_name'] ?? ''));
                            $carrierServicePair = $carrierLabel . ' / ' . $serviceLabel;
                            $notes = (array) ($offer['notes'] ?? []);
                            $restrictions = (array) ($offer['restrictions'] ?? []);
                            $breakdown = array_filter([
                                ['label' => 'السعر المعروض', 'value' => number_format((float) ($offer['retail_rate'] ?? 0), 2) . ' ' . ($offer['currency'] ?? '')],
                                ['label' => 'الخدمة', 'value' => $serviceLabel],
                                ['label' => 'الوصول المتوقع', 'value' => $deliveryLabel],
                                ['label' => 'حالة التوفر', 'value' => $isAvailable ? 'متاح للاختيار' : 'غير متاح حاليًا'],
                            ], fn ($item) => filled($item['value']));
                        @endphp
                        <article class="shipment-offer-card {{ $isSelected ? 'shipment-offer-card--selected' : '' }} {{ !$isAvailable ? 'shipment-offer-card--unavailable' : '' }}">
                            <div class="shipment-offer-card__head">
                                <div>
                                    <div class="shipment-offer-card__title">{{ $carrierLabel }}</div>
                                    <div class="shipment-offer-card__body">{{ $carrierServicePair }}</div>
                                </div>
                                <div class="shipment-offer-card__price">
                                    <div class="shipment-offer-card__amount">{{ number_format((float) ($offer['retail_rate'] ?? 0), 2) }}</div>
                                    <div class="shipment-offer-card__currency">السعر المعروض / {{ $offer['currency'] }}</div>
                                </div>
                            </div>

                            <div class="shipment-breakdown-grid">
                                @foreach($breakdown as $item)
                                    <div class="shipment-breakdown">
                                        <div class="shipment-breakdown__label">{{ $item['label'] }}</div>
                                        <div class="shipment-breakdown__value">{{ $item['value'] }}</div>
                                    </div>
                                @endforeach
                            </div>

                            @if(!empty($offer['badges']))
                                <div class="shipment-flow-chip-row">
                                    @foreach($offer['badges'] as $badge)
                                        <span class="shipment-flow-chip">{{ $badge['label'] }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if($notes)
                                <div class="shipment-note-card shipment-note-card--accent">
                                    <div class="shipment-note-card__title">ملاحظات الخدمة</div>
                                    <div class="shipment-note-card__body">{{ implode(' - ', $notes) }}</div>
                                </div>
                            @endif

                            @if($restrictions)
                                <div class="shipment-note-card shipment-note-card--danger">
                                    <div class="shipment-note-card__title">قيود أو أسباب عدم التوفر</div>
                                    <div class="shipment-note-card__body">{{ implode(' - ', $restrictions) }}</div>
                                </div>
                            @endif

                            <div class="shipment-action-card {{ $isSelected ? 'shipment-action-card--success' : 'shipment-action-card--soft' }}">
                                <div class="shipment-action-card__title">{{ $isSelected ? 'هذا هو العرض المختار حاليًا' : ($isAvailable ? 'اختيار آمن وواضح' : 'هذا العرض غير متاح حاليًا') }}</div>
                                <div class="shipment-action-card__body">{{ $isSelected ? 'يمكنك الآن متابعة إقرار المحتوى والمواد الخطرة لهذه الشحنة.' : ($isAvailable ? 'اختيار هذا العرض يثبت السعر والخدمة قبل الانتقال إلى الإقرار القانوني.' : 'راجع القيود أو أعد جلب العروض للحصول على بدائل قابلة للتنفيذ.') }}</div>
                                @if($canSelectOffers)
                                    <div class="shipment-action-card__actions">
                                        <form method="POST" action="{{ route($portalConfig['offers_select_route'], ['id' => $shipment->id]) }}">
                                            @csrf
                                            <input type="hidden" name="option_id" value="{{ $offer['id'] }}">
                                            <button type="submit" class="btn {{ $isSelected ? 'btn-s' : 'btn-pr' }}" {{ (!$isAvailable || $isExpired) ? 'disabled' : '' }}>{{ $isSelected ? 'تم التثبيت' : 'اختيار هذا العرض' }}</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </section>
            @endif
        </div>

        <aside class="shipment-flow-rail">
            <section class="shipment-helper-card shipment-helper-card--soft">
                <div class="shipment-helper-card__eyebrow">ملخص الشحنة الحالية</div>
                <div class="shipment-helper-card__title td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div>
                <div class="shipment-key-value-grid">
                    <div class="shipment-key-value"><div class="shipment-key-value__label">الحالة</div><div class="shipment-key-value__value">{{ $shipmentStatusLabel }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">الوجهة</div><div class="shipment-key-value__value">{{ $shipment->recipient_city }} / {{ $shipment->recipient_country }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">عدد الطرود</div><div class="shipment-key-value__value">{{ $shipment->parcels_count ?: $shipment->parcels()->count() }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">الصلاحية</div><div class="shipment-key-value__value">{{ $quoteExpiryLabel }}</div></div>
                </div>
            </section>

            <section class="shipment-action-grid">
                @if($selectedOptionId !== '')
                    <div class="shipment-action-card shipment-action-card--success">
                        <div class="shipment-action-card__eyebrow">الخطوة التالية</div>
                        <div class="shipment-action-card__title">إقرار المحتوى والتصريح بالمواد الخطرة</div>
                        <div class="shipment-action-card__body">بعد تثبيت العرض، راجع الإقرار القانوني وصرّح بوضوح عن أي مواد خطرة قبل متابعة الحجز المالي أو الإصدار.</div>
                        <div class="shipment-action-card__actions"><a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-pr">متابعة الإقرار</a></div>
                    </div>
                @else
                    <div class="shipment-action-card shipment-action-card--warning">
                        <div class="shipment-action-card__eyebrow">قبل التثبيت</div>
                        <div class="shipment-action-card__title">راجع السعر والتوقيت والقيود</div>
                        <div class="shipment-action-card__body">اختيار العرض هنا يحسم المسار اللاحق للشحنة، لذلك لا تنتقل إلى الإقرار إلا بعد التأكد من ملاءمة هذا الخيار.</div>
                    </div>
                @endif

                <div class="shipment-helper-card shipment-helper-card--soft">
                    <div class="shipment-helper-card__eyebrow">نصيحة تشغيلية</div>
                    <div class="shipment-helper-card__title">قارن قبل أن تثبّت</div>
                    <div class="shipment-helper-card__body">إذا انتهت صلاحية العروض أو ظهرت قيود جديدة، أعد جلب العروض بدل الاعتماد على عرض قديم أو غير متاح.</div>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
