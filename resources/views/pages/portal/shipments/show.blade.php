@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | حالة الشحنة')

@php
    $timeline = $timeline ?? ['events' => [], 'current_status' => null, 'current_status_label' => null, 'last_updated' => null];
    $events = $timeline['events'] ?? [];
    $documents = $documents ?? [];
    $shipmentNotifications = $shipmentNotifications ?? [];
    $completionFeedback = $completionFeedback ?? session('shipment_completion_feedback');
    $canCreateShipment = $canCreateShipment ?? false;
    $publicTrackingUrl = $publicTrackingUrl ?? null;
    $reservation = $shipment->balanceReservation;
    $reservationStatus = $reservation?->status;
    $workflowStatusLabels = [
        \App\Models\Shipment::STATUS_DRAFT => 'مسودة',
        \App\Models\Shipment::STATUS_VALIDATED => 'تم التحقق من البيانات',
        \App\Models\Shipment::STATUS_KYC_BLOCKED => 'موقوف بسبب التحقق أو القيود',
        \App\Models\Shipment::STATUS_READY_FOR_RATES => 'جاهز لطلب العروض',
        \App\Models\Shipment::STATUS_RATED => 'تم تجهيز العروض',
        \App\Models\Shipment::STATUS_OFFER_SELECTED => 'تم تثبيت العرض',
        \App\Models\Shipment::STATUS_DECLARATION_REQUIRED => 'إقرار المحتوى مطلوب',
        \App\Models\Shipment::STATUS_DECLARATION_COMPLETE => 'اكتمل إقرار المحتوى',
        \App\Models\Shipment::STATUS_REQUIRES_ACTION => 'تتطلب هذه الشحنة إجراءً إضافيًا',
        \App\Models\Shipment::STATUS_PAYMENT_PENDING => 'بانتظار تأكيد الحجز المالي',
        \App\Models\Shipment::STATUS_PURCHASED => 'تم الإصدار لدى الناقل',
        \App\Models\Shipment::STATUS_FAILED => 'تعذر الإصدار',
    ];
    $reservationStatusLabels = [
        \App\Models\WalletHold::STATUS_ACTIVE => 'حجز نشط',
        \App\Models\WalletHold::STATUS_CAPTURED => 'تم الالتقاط',
        \App\Models\WalletHold::STATUS_RELEASED => 'تم الإفراج',
        \App\Models\WalletHold::STATUS_EXPIRED => 'منتهي',
    ];
    $selectedOffer = $shipment->selectedRateOption ?? $shipment->rateQuote?->selectedOption;
    $selectedOfferAmount = $selectedOffer?->retail_rate ?? $shipment->reserved_amount ?? $shipment->total_charge ?? null;
    $selectedOfferCurrency = $selectedOffer?->currency ?? $shipment->currency ?? 'SAR';
    $canTriggerWalletPreflight = $canTriggerWalletPreflight ?? false;
    $canIssueShipment = $canIssueShipment ?? false;
    $canViewNotifications = $canViewNotifications ?? false;
    $carrierShipmentStatus = (string) ($shipment->carrierShipment?->status ?? '');
    $issuanceSucceeded = (string) $shipment->status === \App\Models\Shipment::STATUS_PURCHASED
        || in_array($carrierShipmentStatus, [\App\Models\CarrierShipment::STATUS_CREATED, \App\Models\CarrierShipment::STATUS_LABEL_READY], true);
    $issuanceFailed = (string) $shipment->status === \App\Models\Shipment::STATUS_FAILED
        || $carrierShipmentStatus === \App\Models\CarrierShipment::STATUS_FAILED;
    $showWalletPreflightAction = $canTriggerWalletPreflight
        && ($shipment->status === \App\Models\Shipment::STATUS_DECLARATION_COMPLETE || ($shipment->status === \App\Models\Shipment::STATUS_PAYMENT_PENDING && $reservationStatus === null))
        && ! $shipment->carrierShipment
        && $reservationStatus !== \App\Models\WalletHold::STATUS_CAPTURED
        && $reservationStatus !== \App\Models\WalletHold::STATUS_ACTIVE;
    $showCarrierIssueAction = $canIssueShipment
        && $shipment->status === \App\Models\Shipment::STATUS_PAYMENT_PENDING
        && $reservationStatus === \App\Models\WalletHold::STATUS_ACTIVE
        && ! $shipment->carrierShipment;
    $trackingNumber = $shipment->tracking_number ?? $shipment->carrierShipment?->tracking_number ?? $shipment->carrier_tracking_number ?? 'غير متاح بعد';
    $currentStatusLabel = $timeline['current_status_label'] ?? ($issuanceFailed ? 'تعذر الإصدار' : ($workflowStatusLabels[$shipment->status] ?? 'غير متاحة'));
    $completionFeedbackMessage = $completionFeedback['message'] ?? 'تم تحديث مرحلة إكمال الشحنة.';
    $completionFeedbackNextAction = $completionFeedback['next_action'] ?? null;
    $completionFeedbackErrorCode = $completionFeedback['error_code'] ?? null;
    $completionFeedbackErrorMap = [
        'ERR_WALLET_NOT_AVAILABLE' => ['message' => 'لا توجد محفظة متاحة بعملة هذه الشحنة.', 'next_action' => 'أنشئ محفظة أو موّل محفظة بعملة الشحنة قبل متابعة الحجز والإصدار.'],
        'ERR_INSUFFICIENT_BALANCE' => ['message' => 'رصيد المحفظة غير كافٍ لإتمام الحجز المسبق لهذه الشحنة.', 'next_action' => 'أضف رصيدًا كافيًا إلى المحفظة ثم أعد تنفيذ فحص المحفظة.'],
        'ERR_INVALID_STATE' => ['message' => 'لا يمكن تنفيذ هذه الخطوة من حالة الشحنة الحالية.', 'next_action' => 'أكمل اختيار العرض وإقرار المحتوى أولًا ثم أعد المحاولة.'],
        'ERR_WALLET_RESERVATION_REQUIRED' => ['message' => 'يجب وجود حجز مالي نشط قبل محاولة الإصدار لدى الناقل.', 'next_action' => 'نفّذ فحص المحفظة أولًا ثم أعد محاولة الإصدار.'],
        'ERR_DG_DECLARATION_INCOMPLETE' => ['message' => 'لا يمكن متابعة الشحنة قبل اكتمال إقرار المحتوى.', 'next_action' => 'افتح صفحة إقرار المحتوى وأكمل التصريح القانوني أولًا.'],
        'ERR_DG_HOLD_REQUIRED' => ['message' => 'هذه الشحنة متوقفة حاليًا بسبب قيد امتثال أو مواد خطرة.', 'next_action' => 'راجع صفحة إقرار المحتوى أو تواصل مع فريق الدعم لاستكمال المعالجة اليدوية.'],
    ];
    if ($completionFeedbackErrorCode && isset($completionFeedbackErrorMap[$completionFeedbackErrorCode])) {
        $completionFeedbackMessage = $completionFeedbackErrorMap[$completionFeedbackErrorCode]['message'];
        $completionFeedbackNextAction = $completionFeedbackErrorMap[$completionFeedbackErrorCode]['next_action'];
    }
    $timelineItems = collect($events)->map(function (array $event): array {
        return [
            'title' => $event['event_type_label'] ?? $event['description'] ?? 'حدث جديد',
            'date' => !empty($event['event_time']) ? \Illuminate\Support\Carbon::parse($event['event_time'])->format('Y-m-d H:i') : 'غير محدد',
            'desc' => $event['description'] ?? '',
            'details' => array_filter([
                ['label' => 'الحالة', 'value' => $event['status_label'] ?? 'غير متاحة'],
                ['label' => 'المصدر', 'value' => $event['source_label'] ?? $event['source'] ?? 'النظام'],
                ['label' => 'الموقع', 'value' => $event['location_label'] ?? $event['location'] ?? 'غير محدد'],
                ['label' => 'الربط المرجعي', 'value' => $event['correlation_id'] ?? null],
            ], fn ($item) => filled($item['value'])),
        ];
    })->all();
    $documentsPreview = collect($documents)->take(3)->all();
    $stepStateOverrides = ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'complete', 'show' => $issuanceFailed ? 'attention' : 'current'];
@endphp

@section('content')
<div class="shipment-flow-stack">
    <x-page-header
        :eyebrow="($portalConfig['label'] ?? 'البوابة') . ' / الشحنات / الحالة الزمنية'"
        title="الحالة الزمنية للشحنة"
        subtitle="هذه الصفحة هي مركز التحكم بعد الشراء أو قبله: تتابع الحجز المالي، الإصدار لدى الناقل، المستندات، والأحداث الزمنية في مكان واحد."
        meta="تحديثات التتبع والمستندات والحالة المعيارية الحالية تظهر هنا بعد كل خطوة."
    >
        @if($canCreateShipment)
            <a href="{{ route($portalConfig['create_route'], ['clone' => $shipment->id]) }}" class="btn btn-s" data-testid="shipment-clone-primary">{{ __('portal_shipments.common.clone_long') }}</a>
        @endif
        @if($publicTrackingUrl)
            <a href="{{ $publicTrackingUrl }}" class="btn btn-pr" target="_blank" rel="noopener noreferrer" data-testid="public-tracking-link">{{ __('public_tracking.manage.share_cta') }}</a>
        @endif
        <a href="{{ route($portalConfig['shipments_index_route']) }}" class="btn btn-s">العودة إلى الشحنات</a>
        @if(!empty($documents))
            <a href="{{ route($portalConfig['documents_route'], ['id' => $shipment->id]) }}" class="btn btn-s">عرض المستندات</a>
        @endif
        @if($canViewNotifications)
            <a href="{{ route('notifications.index') }}" class="btn btn-s" data-testid="shipment-notifications-link">عرض الإشعارات</a>
        @endif
    </x-page-header>

    <x-shipment-workflow-stepper
        current="show"
        :create-route="route($portalConfig['create_route'], ['draft' => $shipment->id])"
        :offers-route="route($portalConfig['offers_route'], ['id' => $shipment->id])"
        :declaration-route="route($portalConfig['declaration_route'], ['id' => $shipment->id])"
        :show-route="route($portalConfig['show_route'], ['id' => $shipment->id])"
        :documents-route="route($portalConfig['documents_route'], ['id' => $shipment->id])"
        :state-overrides="$stepStateOverrides"
    />

    @if($completionFeedback)
        @php
            $completionFeedbackSuccess = ($completionFeedback['level'] ?? 'warning') === 'success';
        @endphp
        <div class="shipment-flow-banner {{ $completionFeedbackSuccess ? 'shipment-flow-banner--success' : 'shipment-flow-banner--warning' }}">
            <div class="shipment-flow-banner__title">{{ $completionFeedbackMessage }}</div>
            @if(!empty($completionFeedbackNextAction))
                <div class="shipment-flow-banner__body"><strong>الخطوة التالية:</strong> {{ $completionFeedbackNextAction }}</div>
            @endif
            @if(!empty($completionFeedback['error_code']))
                <div class="shipment-flow-banner__meta td-mono">{{ $completionFeedback['error_code'] }}</div>
            @endif
        </div>
    @endif

    @if($publicTrackingUrl)
        <section class="shipment-share-card">
            <div>
                <div class="shipment-doc-card__eyebrow">{{ __('public_tracking.manage.card_title') }}</div>
                <div class="shipment-doc-card__title">رابط التتبع العام</div>
                <div class="shipment-doc-card__meta">{{ __('public_tracking.manage.card_description') }}</div>
                <div class="shipment-share-card__code td-mono">{{ $publicTrackingUrl }}</div>
            </div>
            <div class="shipment-doc-card__actions">
                <a href="{{ $publicTrackingUrl }}" class="btn btn-s" target="_blank" rel="noopener noreferrer">{{ __('public_tracking.manage.open_public_page') }}</a>
            </div>
        </section>
    @endif

    <section class="shipment-flow-hero">
        <div class="shipment-flow-hero__head">
            <div>
                <div class="shipment-flow-hero__eyebrow">مركز التحكم بعد الإصدار</div>
                <h2 class="shipment-flow-hero__title">متابعة الحجز والتتبع والوثائق من شاشة واحدة</h2>
                <p class="shipment-flow-hero__body">راجع الحالة المعيارية الحالية، ثم نفّذ فحص المحفظة أو الإصدار إذا كانت الشحنة جاهزة. بعد الإصدار ستجد المستندات والتتبع العام والتسلسل الزمني هنا بشكل متصل.</p>
            </div>
            <span class="shipment-status-pill shipment-status-pill--{{ $issuanceFailed ? 'danger' : ($issuanceSucceeded ? 'success' : 'warning') }}">{{ $issuanceFailed ? 'تعذر الإصدار' : ($issuanceSucceeded ? 'تم الإصدار' : 'قيد المتابعة') }}</span>
        </div>
        <div class="shipment-flow-summary-grid">
            <div class="shipment-summary-card shipment-summary-card--soft"><div class="shipment-summary-card__eyebrow">مرجع الشحنة</div><div class="shipment-summary-card__value td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div><div class="shipment-summary-card__meta">رقم التتبع: <span class="td-mono">{{ $trackingNumber }}</span></div></div>
            <div class="shipment-summary-card shipment-summary-card--accent"><div class="shipment-summary-card__eyebrow">الحالة المعيارية الحالية</div><div class="shipment-summary-card__value">{{ $currentStatusLabel }}</div><div class="shipment-summary-card__meta">{{ !empty($timeline['last_updated']) ? 'آخر تحديث: ' . \Illuminate\Support\Carbon::parse($timeline['last_updated'])->format('Y-m-d H:i') : 'لا يوجد تحديث زمني بعد.' }}</div></div>
            <div class="shipment-summary-card {{ $reservationStatus === \App\Models\WalletHold::STATUS_ACTIVE ? 'shipment-summary-card--success' : 'shipment-summary-card--warning' }}"><div class="shipment-summary-card__eyebrow">الحجز المالي</div><div class="shipment-summary-card__value">{{ $reservationStatus ? ($reservationStatusLabels[$reservationStatus] ?? $reservationStatus) : 'لا يوجد حجز بعد' }}</div><div class="shipment-summary-card__meta">{{ $selectedOfferAmount !== null ? number_format((float) $selectedOfferAmount, 2) . ' ' . $selectedOfferCurrency : 'سيظهر المبلغ بعد تثبيت العرض.' }}</div></div>
        </div>
    </section>

    <div class="shipment-flow-layout shipment-flow-layout--detail">
        <div class="shipment-flow-stack">
            <section class="shipment-action-grid">
                <div class="shipment-action-card shipment-action-card--soft">
                    <div class="shipment-action-card__eyebrow">حالة الحجز المالي</div>
                    <div class="shipment-action-card__title">{{ $reservationStatus ? ($reservationStatusLabels[$reservationStatus] ?? $reservationStatus) : 'بانتظار إنشاء الحجز' }}</div>
                    <div class="shipment-action-card__body">{{ $selectedOfferAmount !== null ? 'قيمة المتابعة الحالية: ' . number_format((float) $selectedOfferAmount, 2) . ' ' . $selectedOfferCurrency : 'لا توجد قيمة قابلة للحجز بعد.' }}</div>
                </div>
                <div class="shipment-action-card {{ $issuanceSucceeded ? 'shipment-action-card--success' : ($issuanceFailed ? 'shipment-action-card--danger' : 'shipment-action-card--warning') }}">
                    <div class="shipment-action-card__eyebrow">الإصدار لدى الناقل</div>
                    <div class="shipment-action-card__title">{{ $issuanceSucceeded ? 'تم الإصدار لدى الناقل' : ($issuanceFailed ? 'تعذر الإصدار لدى الناقل' : 'لم يتم الإصدار بعد') }}</div>
                    <div class="shipment-action-card__body">{{ $shipment->carrierShipment?->tracking_number ? 'رقم التتبع الحالي: ' . $shipment->carrierShipment->tracking_number : 'سيظهر رقم التتبع هنا بعد نجاح الإصدار.' }}</div>
                </div>
                <div class="shipment-action-card {{ $showWalletPreflightAction ? 'shipment-action-card--accent' : ($showCarrierIssueAction ? 'shipment-action-card--success' : 'shipment-action-card--soft') }}">
                    <div class="shipment-action-card__eyebrow">الإجراء المتاح الآن</div>
                    <div class="shipment-action-card__title">
                        @if($showWalletPreflightAction)
                            فحص المحفظة قبل الإصدار
                        @elseif($showCarrierIssueAction)
                            الإصدار لدى الناقل
                        @elseif($issuanceSucceeded)
                            الشحنة أصبحت جاهزة للتتبع والتنزيل
                        @elseif($issuanceFailed)
                            عالج الخطأ ثم أعد المحاولة
                        @else
                            أكمل الخطوات السابقة أولًا
                        @endif
                    </div>
                    <div class="shipment-action-card__body">
                        @if($showWalletPreflightAction)
                            نفّذ الحجز المالي لهذه الشحنة قبل إرسالها إلى الناقل.
                        @elseif($showCarrierIssueAction)
                            الحجز المالي نشط ويمكنك الآن إرسال الشحنة إلى الناقل وإكمال الإصدار.
                        @elseif($issuanceSucceeded)
                            افتح المستندات وشارك رابط التتبع العام عند الحاجة.
                        @elseif($issuanceFailed)
                            راجع رسالة الخطأ الظاهرة أعلى الصفحة ثم صحّح البيانات المطلوبة قبل إعادة الإصدار.
                        @else
                            لا يمكن تشغيل فحص المحفظة أو الإصدار قبل اكتمال اختيار العرض وإقرار المحتوى.
                        @endif
                    </div>
                    <div class="shipment-action-card__actions">
                        @if($showWalletPreflightAction)
                            <form method="POST" action="{{ route($portalConfig['preflight_route'], ['id' => $shipment->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-pr" data-testid="wallet-preflight-button">تنفيذ فحص المحفظة</button>
                            </form>
                        @elseif($showCarrierIssueAction)
                            <form method="POST" action="{{ route($portalConfig['issue_route'], ['id' => $shipment->id]) }}">
                                @csrf
                                <button type="submit" class="btn btn-pr" data-testid="carrier-issue-button">إصدار الشحنة لدى الناقل</button>
                            </form>
                        @elseif(! $issuanceSucceeded)
                            <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة الإقرار</a>
                            <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
                        @endif
                    </div>
                </div>
            </section>

            <section class="shipment-helper-card shipment-helper-card--soft">
                <div class="shipment-helper-card__eyebrow">التسلسل الزمني</div>
                <div class="shipment-helper-card__title">تاريخ الشحنة من آخر تحديث إلى البداية</div>
                @if($timelineItems === [])
                    <div class="shipment-empty-state">
                        <div class="shipment-empty-state__title">لا توجد أحداث زمنية مسجلة بعد</div>
                        <div class="shipment-empty-state__body">سيظهر هنا تاريخ الشحنة بعد الإصدار، بما في ذلك تحديثات الناقل والمستندات المتاحة وأي حالة معيارية لاحقة.</div>
                    </div>
                @else
                    <x-timeline :items="$timelineItems" />
                @endif
            </section>

            @if($canViewNotifications)
                <section class="shipment-helper-card shipment-helper-card--soft">
                    <div class="shipment-helper-card__eyebrow">الإشعارات المرتبطة بالشحنة</div>
                    <div class="shipment-helper-card__title">آخر التنبيهات والرسائل</div>
                    @if($shipmentNotifications === [])
                        <div class="shipment-empty-state">
                            <div class="shipment-empty-state__title">لا توجد إشعارات مرتبطة بهذه الشحنة حتى الآن</div>
                            <div class="shipment-empty-state__body">عند وصول أي تحديث معياري جديد لهذه الشحنة سيظهر هنا، كما سيظل متاحًا من مركز الإشعارات العام.</div>
                        </div>
                    @else
                        <div class="shipment-notification-list">
                            @foreach($shipmentNotifications as $notification)
                                <article class="shipment-notification-card">
                                    <div class="shipment-notification-card__title">{{ $notification['subject'] }}</div>
                                    @if(empty($notification['read_at'])) <span class="shipment-status-pill shipment-status-pill--success">جديد</span> @endif
                                    @if(!empty($notification['body'])) <div class="shipment-notification-card__body">{{ $notification['body'] }}</div> @endif
                                    <div class="shipment-inline-meta td-mono">{{ $notification['event_type_label'] ?? $notification['event_type'] }}</div>
                                    <div class="shipment-inline-meta">{{ !empty($notification['created_at']) ? \Illuminate\Support\Carbon::parse($notification['created_at'])->format('Y-m-d H:i') : 'غير محدد' }}</div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif
        </div>

        <aside class="shipment-flow-rail">
            <section class="shipment-helper-card shipment-helper-card--soft">
                <div class="shipment-helper-card__eyebrow">ملخص الخدمة والتكلفة</div>
                <div class="shipment-helper-card__title td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div>
                <div class="shipment-key-value-grid">
                    <div class="shipment-key-value"><div class="shipment-key-value__label">الخدمة</div><div class="shipment-key-value__value">{{ $serviceDisplayLabel ?? ($shipment->carrierShipment?->service_name ?? $selectedOffer?->service_name ?? $shipment->service_name ?? 'غير محددة') }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">تكلفة الشحنة</div><div class="shipment-key-value__value">{{ $selectedOfferAmount !== null ? number_format((float) $selectedOfferAmount, 2) . ' ' . $selectedOfferCurrency : '—' }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">عدد الأحداث</div><div class="shipment-key-value__value">{{ number_format((int) ($timeline['total_events'] ?? 0)) }}</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">المستندات المتاحة</div><div class="shipment-key-value__value">{{ number_format(count($documents)) }}</div></div>
                </div>
            </section>

            <section class="shipment-doc-list">
                @if($documentsPreview === [])
                    <div class="shipment-empty-state">
                        <div class="shipment-empty-state__title">لا توجد مستندات متاحة حاليًا</div>
                        <div class="shipment-empty-state__body">عند إتاحة ملصق الشحن أو بقية مستندات الناقل ستجدها هنا وفي صفحة الوثائق الكاملة.</div>
                    </div>
                @else
                    @foreach($documentsPreview as $document)
                        <article class="shipment-doc-card">
                            <div class="shipment-doc-card__head">
                                <div>
                                    <div class="shipment-doc-card__eyebrow">{{ $document['document_type_label'] ?? $document['document_type'] }}</div>
                                    <div class="shipment-doc-card__title">{{ $document['filename'] }}</div>
                                    <div class="shipment-doc-card__meta">{{ $document['carrier_label'] ?? $document['carrier_code'] }} / {{ $document['format_label'] ?? $document['file_format'] }}</div>
                                </div>
                            </div>
                            <div class="shipment-doc-card__actions">
                                @if(!empty($document['previewable']) && !empty($document['preview_route']))
                                    <a href="{{ $document['preview_route'] }}" class="btn btn-s" target="_blank" rel="noopener noreferrer">عرض المستند</a>
                                @endif
                                <a href="{{ $document['download_route'] }}" class="btn btn-s" download="{{ $document['filename'] }}">تنزيل المستند</a>
                            </div>
                        </article>
                    @endforeach
                    <a href="{{ route($portalConfig['documents_route'], ['id' => $shipment->id]) }}" class="btn btn-s">فتح مساحة الوثائق</a>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
