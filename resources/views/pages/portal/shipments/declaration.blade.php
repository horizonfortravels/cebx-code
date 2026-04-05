@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | إقرار المحتوى')

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
    $selectedOffer = $selectedOffer ?? null;
    $declaration = $declaration ?? null;
    $declarationStatusLabels = [
        \App\Models\ContentDeclaration::STATUS_PENDING => 'قيد الانتظار',
        \App\Models\ContentDeclaration::STATUS_COMPLETED => 'مكتمل',
        \App\Models\ContentDeclaration::STATUS_HOLD_DG => 'متوقف بسبب مواد خطرة',
        \App\Models\ContentDeclaration::STATUS_REQUIRES_ACTION => 'متوقف ويتطلب مراجعة',
    ];
    $isBlocked = $declaration?->isBlocked() ?? false;
    $isComplete = (bool) ($declaration?->waiver_accepted && $declaration?->status === \App\Models\ContentDeclaration::STATUS_COMPLETED);
    $documentsAvailable = $shipment->carrierDocuments()->where('is_available', true)->exists();
    $selectedCarrierLabel = \App\Support\PortalShipmentLabeler::carrier((string) ($selectedOffer?->carrier_code ?? ''), (string) ($selectedOffer?->carrier_name ?? ''));
    $selectedServiceLabel = \App\Support\PortalShipmentLabeler::service((string) ($selectedOffer?->service_code ?? ''), (string) ($selectedOffer?->service_name ?? ''));
    $stepStateOverrides = $isBlocked
        ? ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'attention']
        : ($isComplete
            ? ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'complete', 'show' => 'current']
            : ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'current']);
@endphp

<div class="shipment-flow-stack">
    <x-page-header
        :eyebrow="($portalConfig['label'] ?? 'البوابة') . ' / الشحنات / إقرار المحتوى'"
        title="إقرار المحتوى والتصريح بالمواد الخطرة"
        subtitle="هذه الخطوة إلزامية بعد اختيار العرض، وهي التي تحدد إن كانت الشحنة ستكمل المسار الذاتي أو ستتوقف لمراجعة إضافية."
        meta="الإقرار هنا قانوني وتشغيلي في الوقت نفسه، لذلك يجب أن يكون واضحًا وصريحًا."
    >
        <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
        @if($documentsAvailable)
            <a href="{{ route($portalConfig['documents_route'], ['id' => $shipment->id]) }}" class="btn btn-s">عرض الوثائق</a>
        @endif
    </x-page-header>

    <x-shipment-workflow-stepper
        current="declaration"
        :create-route="route($portalConfig['create_route'], ['draft' => $shipment->id])"
        :offers-route="route($portalConfig['offers_route'], ['id' => $shipment->id])"
        :declaration-route="route($portalConfig['declaration_route'], ['id' => $shipment->id])"
        :show-route="route($portalConfig['show_route'], ['id' => $shipment->id])"
        :documents-route="route($portalConfig['documents_route'], ['id' => $shipment->id])"
        :state-overrides="$stepStateOverrides"
    />

    @if($offerFeedback)
        <div class="shipment-flow-banner shipment-flow-banner--success">
            <div class="shipment-flow-banner__title">{{ $offerFeedback['message'] ?? 'تم تحديث حالة الشحنة.' }}</div>
            @if(!empty($offerFeedback['next_action']))
                <div class="shipment-flow-banner__body"><strong>الخطوة التالية:</strong> {{ $offerFeedback['next_action'] }}</div>
            @endif
        </div>
    @endif

    @if($declarationFeedback)
        @php
            $declarationFeedbackSuccess = ($declarationFeedback['level'] ?? 'warning') === 'success';
        @endphp
        <div class="shipment-flow-banner {{ $declarationFeedbackSuccess ? 'shipment-flow-banner--success' : 'shipment-flow-banner--warning' }}">
            <div class="shipment-flow-banner__title">{{ $declarationFeedback['message'] ?? 'تم تحديث حالة الإقرار.' }}</div>
            @if(!empty($declarationFeedback['next_action']))
                <div class="shipment-flow-banner__body"><strong>الإجراء التالي:</strong> {{ $declarationFeedback['next_action'] }}</div>
            @endif
            @if(!empty($declarationFeedback['error_code']))
                <div class="shipment-flow-banner__meta td-mono">{{ $declarationFeedback['error_code'] }}</div>
            @endif
        </div>
    @endif

    <section class="shipment-flow-hero">
        <div class="shipment-flow-hero__head">
            <div>
                <div class="shipment-flow-hero__eyebrow">مرحلة التصريح الإلزامي</div>
                <h2 class="shipment-flow-hero__title">وضوح قانوني قبل الحجز والإصدار</h2>
                <p class="shipment-flow-hero__body">حدّد بوضوح ما إذا كانت هذه الشحنة تحتوي على مواد خطرة. عند اختيار "لا"، يجب الموافقة على الإقرار القانوني الإلزامي. وعند اختيار "نعم"، سيتوقف المسار العادي لهذه الشحنة لحين مراجعتها.</p>
            </div>
            <span class="shipment-status-pill shipment-status-pill--{{ $isBlocked ? 'danger' : ($isComplete ? 'success' : 'warning') }}">{{ $isBlocked ? 'يتطلب معالجة' : ($isComplete ? 'مكتمل' : 'بانتظار الإقرار') }}</span>
        </div>
        <div class="shipment-flow-summary-grid">
            <div class="shipment-summary-card shipment-summary-card--soft"><div class="shipment-summary-card__eyebrow">مرجع الشحنة</div><div class="shipment-summary-card__value td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div><div class="shipment-summary-card__meta">{{ $shipmentStatusLabel }}</div></div>
            <div class="shipment-summary-card shipment-summary-card--accent"><div class="shipment-summary-card__eyebrow">العرض المختار</div><div class="shipment-summary-card__value">{{ $selectedOffer ? $selectedCarrierLabel : 'لم يتم اختيار عرض بعد' }}</div><div class="shipment-summary-card__meta">{{ $selectedOffer ? $selectedServiceLabel : 'ارجع إلى مرحلة العروض لاختيار خدمة مناسبة.' }}</div></div>
            <div class="shipment-summary-card {{ $isBlocked ? 'shipment-summary-card--danger' : ($isComplete ? 'shipment-summary-card--success' : 'shipment-summary-card--warning') }}"><div class="shipment-summary-card__eyebrow">حالة الإقرار</div><div class="shipment-summary-card__value">{{ $declaration ? ($declarationStatusLabels[$declaration->status] ?? $declaration->status) : 'لم يبدأ بعد' }}</div><div class="shipment-summary-card__meta">{{ $declaration?->updated_at ? 'آخر تحديث: ' . $declaration->updated_at->format('Y-m-d H:i') : 'سيظهر آخر تحديث بعد أول إرسال.' }}</div></div>
        </div>
    </section>

    @if(!$workflowReady)
        <section class="shipment-empty-state">
            <div class="shipment-empty-state__title">لا يمكن متابعة إقرار المحتوى بعد</div>
            <div class="shipment-empty-state__body">يجب اختيار عرض شحن صالح أولًا قبل فتح هذه الخطوة. بعد تثبيت العرض، ستنتقل هذه الشحنة تلقائيًا إلى بوابة إقرار المحتوى.</div>
            <div class="shipment-form-actions"><a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-pr">العودة إلى العروض</a></div>
        </section>
    @elseif($isBlocked)
        <section class="shipment-action-card shipment-action-card--danger">
            <div class="shipment-action-card__eyebrow">الشحنة متوقفة</div>
            <div class="shipment-action-card__title">تم تعليق المسار العادي لهذه الشحنة</div>
            <div class="shipment-action-card__body">لأنك صرحت بوجود مواد خطرة، لم يعد من الممكن متابعة الإصدار العادي لهذه الشحنة عبر التدفق الذاتي. تواصل مع فريق الدعم أو العمليات لاستكمال المعالجة اليدوية.</div>
            @if($declaration?->hold_reason)
                <div class="shipment-inline-meta"><strong>سبب الإيقاف:</strong> {{ $declaration->hold_reason }}</div>
            @endif
        </section>
    @elseif($isComplete)
        <section class="shipment-action-card shipment-action-card--success">
            <div class="shipment-action-card__eyebrow">الإقرار محفوظ</div>
            <div class="shipment-action-card__title">تم حفظ الإقرار القانوني بنجاح</div>
            <div class="shipment-action-card__body">تم التصريح بأن الشحنة لا تحتوي على مواد خطرة، وحُفظت موافقتك القانونية كسجل تدقيقي مرتبط بالشحنة. الشحنة أصبحت جاهزة للمرحلة التالية.</div>
            @if($declaration?->waiverVersion)
                <div class="shipment-inline-meta td-mono">نسخة الإقرار: {{ $declaration->waiverVersion->version }} / {{ strtoupper($declaration->waiverVersion->locale) }}</div>
            @endif
            <div class="shipment-action-card__actions">
                <a href="{{ route($portalConfig['show_route'], ['id' => $shipment->id]) }}" class="btn btn-pr" data-testid="shipment-completion-link">الانتقال إلى خطوة المحفظة والإصدار</a>
                <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة العرض المختار</a>
            </div>
        </section>
    @else
        <div class="shipment-flow-layout">
            <form method="POST" action="{{ route($portalConfig['declaration_submit_route'], ['id' => $shipment->id]) }}" class="shipment-form">
                @csrf
                <section class="shipment-form-section">
                    <div class="shipment-form-section__head">
                        <div>
                            <div class="shipment-form-section__title">هل تحتوي هذه الشحنة على مواد خطرة؟</div>
                            <div class="shipment-form-section__body">اختر إجابة واحدة فقط. هذا الاختيار يحدد إن كانت الشحنة ستبقى ضمن المسار الذاتي أو ستنتقل إلى معالجة إضافية.</div>
                        </div>
                    </div>
                    <div class="shipment-choice-grid">
                        <label class="shipment-choice-card shipment-choice-card--safe">
                            <input type="radio" name="contains_dangerous_goods" value="no" @checked(old('contains_dangerous_goods', ($declaration && $declaration->dg_flag_declared && ! $declaration->contains_dangerous_goods) ? 'no' : '') === 'no')>
                            <span>
                                <div class="shipment-choice-card__title">لا، الشحنة آمنة للمسار العادي</div>
                                <div class="shipment-choice-card__body">سأتابع عبر الإقرار القانوني الإلزامي وأؤكد صحة التصريح قبل الحجز المالي والإصدار.</div>
                            </span>
                        </label>
                        <label class="shipment-choice-card shipment-choice-card--hold">
                            <input type="radio" name="contains_dangerous_goods" value="yes" @checked(old('contains_dangerous_goods', ($declaration && $declaration->dg_flag_declared && $declaration->contains_dangerous_goods) ? 'yes' : '') === 'yes')>
                            <span>
                                <div class="shipment-choice-card__title">نعم، تحتوي على مواد خطرة</div>
                                <div class="shipment-choice-card__body">سيتم تعليق الشحنة والتحويل إلى مراجعة إضافية، ولن يستمر المسار الذاتي للإصدار لدى الناقل.</div>
                            </span>
                        </label>
                    </div>
                    @error('contains_dangerous_goods') <div class="shipment-inline-meta" style="color:#991b1b">{{ $message }}</div> @enderror
                </section>

                <section class="shipment-form-section">
                    <div class="shipment-form-section__head">
                        <div>
                            <div class="shipment-form-section__title">الإقرار القانوني الإلزامي</div>
                            <div class="shipment-form-section__body">اقرأ النص كما هو، ثم أكّد موافقتك عند اختيار المسار الآمن للشحنة.</div>
                        </div>
                    </div>
                    <div class="shipment-legal-copy">{{ $waiver?->waiver_text ?? 'لا توجد نسخة إقرار قانوني نشطة حاليًا.' }}</div>
                    @if($waiver)
                        <div class="shipment-inline-meta td-mono">الإصدار: {{ $waiver->version }} / {{ strtoupper($waiver->locale) }}</div>
                    @endif
                    <label class="shipment-choice-card shipment-choice-card--safe">
                        <input type="checkbox" name="accept_disclaimer" value="1" @checked(old('accept_disclaimer', $declaration?->waiver_accepted ? '1' : null))>
                        <span>
                            <div class="shipment-choice-card__title">أوافق على الإقرار القانوني</div>
                            <div class="shipment-choice-card__body">أؤكد أن هذه الشحنة لا تحتوي على مواد خطرة، وأنني أتحمل مسؤولية صحة هذا التصريح.</div>
                        </span>
                    </label>
                    @error('accept_disclaimer') <div class="shipment-inline-meta" style="color:#991b1b">{{ $message }}</div> @enderror
                </section>

                <div class="shipment-form-actions">
                    <button type="submit" class="btn btn-pr">حفظ الإقرار والمتابعة</button>
                    <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة العروض مرة أخرى</a>
                </div>
            </form>

            <aside class="shipment-flow-rail">
                <section class="shipment-helper-card shipment-helper-card--soft">
                    <div class="shipment-helper-card__eyebrow">ملخص الشحنة والعرض</div>
                    <div class="shipment-helper-card__title td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div>
                    <div class="shipment-key-value-grid">
                        <div class="shipment-key-value"><div class="shipment-key-value__label">حالة الشحنة</div><div class="shipment-key-value__value">{{ $shipmentStatusLabel }}</div></div>
                        <div class="shipment-key-value"><div class="shipment-key-value__label">الناقل</div><div class="shipment-key-value__value">{{ $selectedOffer ? $selectedCarrierLabel : 'غير محدد' }}</div></div>
                        <div class="shipment-key-value"><div class="shipment-key-value__label">الخدمة</div><div class="shipment-key-value__value">{{ $selectedOffer ? $selectedServiceLabel : 'غير محددة' }}</div></div>
                        <div class="shipment-key-value"><div class="shipment-key-value__label">السعر المعروض</div><div class="shipment-key-value__value">{{ $selectedOffer ? number_format((float) $selectedOffer->retail_rate, 2) . ' ' . $selectedOffer->currency : '—' }}</div></div>
                    </div>
                </section>

                <section class="shipment-action-grid">
                    <div class="shipment-helper-card shipment-helper-card--soft">
                        <div class="shipment-helper-card__eyebrow">إذا اخترت "نعم"</div>
                        <div class="shipment-helper-card__title">يتوقف المسار الذاتي</div>
                        <div class="shipment-helper-card__body">ستحتاج الشحنة إلى معالجة إضافية ولن تنتقل إلى الحجز المالي أو الإصدار حتى تُراجع يدويًا.</div>
                    </div>
                    <div class="shipment-helper-card shipment-helper-card--soft">
                        <div class="shipment-helper-card__eyebrow">إذا اخترت "لا"</div>
                        <div class="shipment-helper-card__title">يلزمك قبول الإقرار</div>
                        <div class="shipment-helper-card__body">من دون الموافقة الصريحة على الإقرار القانوني الإلزامي لن تنتقل الشحنة إلى المرحلة التالية.</div>
                    </div>
                </section>
            </aside>
        </div>
    @endif
</div>
@endsection
