@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | إنشاء طلب شحنة')

@section('content')
@php
    $addressFormDefaults = $addressFormDefaults ?? [];
    $cloneFormDefaults = $cloneFormDefaults ?? [];
    $cloneSourceShipment = $cloneSourceShipment ?? null;
    $cloneDropsAdditionalParcels = $cloneDropsAdditionalParcels ?? false;
    $selectedSenderAddress = $selectedSenderAddress ?? null;
    $selectedRecipientAddress = $selectedRecipientAddress ?? null;
    $senderAddresses = $senderAddresses ?? collect();
    $recipientAddresses = $recipientAddresses ?? collect();
    $canViewAddressBook = $canViewAddressBook ?? false;
    $canManageAddressBook = $canManageAddressBook ?? false;

    $prefill = static function (string $key, mixed $fallback = null) use ($addressFormDefaults, $cloneFormDefaults, $draftShipment) {
        return data_get($addressFormDefaults, $key, data_get($cloneFormDefaults, $key, data_get($draftShipment, $key, $fallback)));
    };

    $firstParcel = old('parcels.0', [
        'weight' => data_get($cloneFormDefaults, 'parcels.0.weight', data_get($draftShipment, 'parcels.0.weight', '1.0')),
        'length' => data_get($cloneFormDefaults, 'parcels.0.length', data_get($draftShipment, 'parcels.0.length')),
        'width' => data_get($cloneFormDefaults, 'parcels.0.width', data_get($draftShipment, 'parcels.0.width')),
        'height' => data_get($cloneFormDefaults, 'parcels.0.height', data_get($draftShipment, 'parcels.0.height')),
    ]);

    $addressSelectionBaseQuery = collect([
        'draft' => request()->query('draft'),
        'clone' => request()->query('clone'),
    ])->filter(fn ($value) => filled($value))->all();

    $workflowBadges = [
        'draft' => ['label' => 'مسودة', 'tone' => 'neutral'],
        'validated' => ['label' => 'تم التحقق من البيانات', 'tone' => 'info'],
        'kyc_blocked' => ['label' => 'موقوف بسبب التحقق أو القيود', 'tone' => 'danger'],
        'ready_for_rates' => ['label' => 'جاهز لطلب العروض', 'tone' => 'success'],
        'rated' => ['label' => 'تم تجهيز العروض', 'tone' => 'info'],
        'offer_selected' => ['label' => 'تم تثبيت العرض', 'tone' => 'warning'],
        'declaration_required' => ['label' => 'إقرار المحتوى مطلوب', 'tone' => 'warning'],
        'declaration_complete' => ['label' => 'اكتمل إقرار المحتوى', 'tone' => 'success'],
        'requires_action' => ['label' => 'تتطلب هذه الشحنة إجراءً إضافيًا', 'tone' => 'danger'],
        'payment_pending' => ['label' => 'بانتظار الحجز المالي', 'tone' => 'warning'],
        'purchased' => ['label' => 'تم الإصدار لدى الناقل', 'tone' => 'success'],
    ];

    $currentBadge = $workflowBadges[$workflowState] ?? ['label' => $workflowState, 'tone' => 'neutral'];
    $selectedSenderAddressId = (string) old('sender_address_id', $prefill('sender_address_id'));
    $selectedRecipientAddressId = (string) old('recipient_address_id', $prefill('recipient_address_id'));
    $canContinueToOffers = $draftShipment && in_array($draftShipment->status, ['ready_for_rates', 'rated', 'offer_selected', 'declaration_required', 'declaration_complete', 'requires_action', 'payment_pending', 'purchased'], true);
    $draftReference = $draftShipment?->reference_number ?? $draftShipment?->id ?? 'سيُنشأ بعد';
    $draftDestination = collect([$draftShipment?->recipient_city, $draftShipment?->recipient_country])->filter()->implode(' / ');
    $draftParcels = $draftShipment ? ($draftShipment->parcels_count ?? ($draftShipment->parcels?->count() ?? 0)) : 0;
    $shipmentRouteArgs = $draftShipment ? ['id' => $draftShipment->id] : null;

    $addressValidationResults = session('address_validation_results', []);
    $senderValidation = data_get($addressValidationResults, 'sender');
    $recipientValidation = data_get($addressValidationResults, 'recipient');
    $validationFieldLabels = [
        'address_1' => __('portal_shipments.address_validation.fields.address_1'),
        'address_2' => __('portal_shipments.address_validation.fields.address_2'),
        'city' => __('portal_shipments.address_validation.fields.city'),
        'state' => __('portal_shipments.address_validation.fields.state'),
        'postal_code' => __('portal_shipments.address_validation.fields.postal_code'),
        'country' => __('portal_shipments.address_validation.fields.country'),
    ];
    $addressValidationStyle = static function (?array $result): array {
        return match (data_get($result, 'classification')) {
            'exact_validation_pass' => ['border' => 'rgba(4,120,87,.22)', 'background' => 'rgba(4,120,87,.08)', 'color' => '#065f46'],
            'normalized_suggestion' => ['border' => 'rgba(37,99,235,.18)', 'background' => 'rgba(37,99,235,.06)', 'color' => '#1d4ed8'],
            default => ['border' => 'rgba(234,88,12,.18)', 'background' => 'rgba(255,251,235,.9)', 'color' => '#9a3412'],
        };
    };
    $validationProviderUnavailable = collect($addressValidationResults)->contains(fn ($result) => data_get($result, 'source') === 'provider_unavailable');

    $stepStateOverrides = match ($workflowState) {
        'kyc_blocked', 'requires_action' => ['create' => 'attention'],
        'ready_for_rates', 'rated' => ['create' => 'complete', 'offers' => 'current'],
        'offer_selected', 'declaration_required' => ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'current'],
        'declaration_complete', 'payment_pending' => ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'complete', 'show' => 'current'],
        'purchased' => ['create' => 'complete', 'offers' => 'complete', 'declaration' => 'complete', 'show' => 'complete', 'documents' => 'current'],
        default => ['create' => 'current'],
    };

    $workflowStages = [
        'draft' => 'ابدأ بالمسودة ثم راجع بيانات الشحنة الأساسية.',
        'validated' => 'راجِع ناتج التحقق قبل طلب عروض الشحن.',
        'kyc_blocked' => 'هناك قيد تحقق أو استخدام يجب معالجته قبل المتابعة.',
        'ready_for_rates' => 'المسودة أصبحت جاهزة لطلب عروض الشحن.',
        'rated' => 'العروض جاهزة للمقارنة واختيار العرض الأنسب.',
        'offer_selected' => 'تم تثبيت العرض المختار ويمكن متابعة إقرار المحتوى.',
        'declaration_required' => 'يجب إكمال إقرار المحتوى قبل أي حجز مالي أو إصدار.',
        'declaration_complete' => 'الإقرار مكتمل وأصبحت الشحنة جاهزة للخطوة التالية.',
        'requires_action' => 'المسار يحتاج مراجعة أو معالجة إضافية قبل المتابعة الذاتية.',
    ];
@endphp

<div class="shipment-flow-stack">
    <x-page-header
        :eyebrow="($portalConfig['label'] ?? 'البوابة') . ' / الشحنات / إنشاء طلب'"
        :title="$portalConfig['headline']"
        :subtitle="$portalConfig['description']"
        meta="ابدأ الطلب، ثبّت العناوين، ثم شغّل التحقق قبل مقارنة عروض الشحن."
    >
        <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">العودة إلى الشحنات</a>
    </x-page-header>

    <x-shipment-workflow-stepper
        current="create"
        :create-route="route($portalConfig['create_route'], $addressSelectionBaseQuery)"
        :offers-route="$shipmentRouteArgs ? route($portalConfig['offers_route'], $shipmentRouteArgs) : null"
        :declaration-route="$shipmentRouteArgs ? route($portalConfig['declaration_route'], $shipmentRouteArgs) : null"
        :show-route="$shipmentRouteArgs ? route($portalConfig['show_route'], $shipmentRouteArgs) : null"
        :documents-route="$shipmentRouteArgs ? route($portalConfig['documents_route'], $shipmentRouteArgs) : null"
        :state-overrides="$stepStateOverrides"
    />

    @if(session('success'))
        <div class="shipment-flow-banner shipment-flow-banner--success">
            <div class="shipment-flow-banner__title">تم تجهيز المسودة بنجاح</div>
            <div class="shipment-flow-banner__body">{{ session('success') }}</div>
        </div>
    @endif

    @if($workflowFeedback)
        @php
            $workflowBannerSuccess = ($workflowFeedback['level'] ?? 'warning') === 'success';
        @endphp
        <div class="shipment-flow-banner {{ $workflowBannerSuccess ? 'shipment-flow-banner--success' : 'shipment-flow-banner--warning' }}">
            <div class="shipment-flow-banner__title">{{ $workflowFeedback['message'] ?? 'تم تحديث حالة المسودة.' }}</div>
            @if(!empty($workflowFeedback['next_action']))
                <div class="shipment-flow-banner__body"><strong>الخطوة التالية:</strong> {{ $workflowFeedback['next_action'] }}</div>
            @endif
            @if(!empty($workflowFeedback['validation_errors']))
                <div class="shipment-flow-banner__meta">
                    @foreach($workflowFeedback['validation_errors'] as $section => $messages)
                        <div><strong>{{ ucfirst($section) }}</strong>: {{ implode(' - ', $messages) }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($cloneSourceShipment)
        <div class="shipment-flow-banner shipment-flow-banner--info" data-testid="clone-prefill-banner">
            <div class="shipment-flow-banner__title">{{ __('portal_shipments.clone.banner_title', ['reference' => $cloneSourceShipment->reference_number ?? $cloneSourceShipment->id]) }}</div>
            <div class="shipment-flow-banner__body">{{ __('portal_shipments.clone.banner_body') }}</div>
            @if($cloneDropsAdditionalParcels)
                <div class="shipment-flow-banner__meta">{{ __('portal_shipments.clone.first_parcel_only') }}</div>
            @endif
        </div>
    @endif

    @if($validationProviderUnavailable)
        <div class="shipment-flow-banner shipment-flow-banner--warning" data-testid="address-validation-provider-warning">
            <div class="shipment-flow-banner__title">{{ __('portal_shipments.address_validation.provider_title') }}</div>
            <div class="shipment-flow-banner__body">{{ __('portal_shipments.address_validation.provider_message') }}</div>
        </div>
    @endif

    <section class="shipment-flow-hero">
        <div class="shipment-flow-hero__head">
            <div>
                <div class="shipment-flow-hero__eyebrow">بداية رحلة الشحنة</div>
                <h2 class="shipment-flow-hero__title">جهّز الطلب قبل المقارنة والشراء</h2>
                <p class="shipment-flow-hero__body">{{ $workflowStages[$workflowState] ?? 'أكمل بيانات المرسل والمستلم والطرد الأول ثم احفظ المسودة لتشغيل التحقق.' }}</p>
            </div>
            <span class="shipment-status-pill shipment-status-pill--{{ $currentBadge['tone'] }}">{{ $currentBadge['label'] }}</span>
        </div>
        <div class="shipment-flow-summary-grid">
            <div class="shipment-summary-card shipment-summary-card--soft">
                <div class="shipment-summary-card__eyebrow">مرجع العمل الحالي</div>
                <div class="shipment-summary-card__value td-mono">{{ $draftReference }}</div>
                <div class="shipment-summary-card__meta">سيتحول هذا المرجع إلى نقطة متابعة ثابتة عبر بقية مراحل الشحنة.</div>
            </div>
            <div class="shipment-summary-card shipment-summary-card--accent">
                <div class="shipment-summary-card__eyebrow">الوجهة والطرود</div>
                <div class="shipment-summary-card__value">{{ $draftDestination !== '' ? $draftDestination : 'حدّد وجهة الشحنة' }}</div>
                <div class="shipment-summary-card__meta">{{ $draftShipment ? 'عدد الطرود الحالية: ' . number_format($draftParcels) : 'ابدأ بالطرد الأول ثم أضف البقية لاحقًا عند الحاجة.' }}</div>
            </div>
            <div class="shipment-summary-card {{ $canViewAddressBook ? 'shipment-summary-card--success' : 'shipment-summary-card--warning' }}">
                <div class="shipment-summary-card__eyebrow">جاهزية العناوين</div>
                <div class="shipment-summary-card__value">{{ $canViewAddressBook ? 'دفتر العناوين متاح' : 'إدخال يدوي مباشر' }}</div>
                <div class="shipment-summary-card__meta">{{ $canViewAddressBook ? 'استخدم العناوين المحفوظة لتسريع التحقق وتقليل أخطاء الإدخال.' : 'أدخل بيانات العنوان بدقة ثم شغّل التحقق قبل المتابعة.' }}</div>
            </div>
        </div>
    </section>

    @if($canViewAddressBook)
        <section class="shipment-helper-card shipment-helper-card--soft" data-testid="saved-address-toolbar">
            <div class="shipment-doc-card__head">
                <div>
                    <div class="shipment-helper-card__eyebrow">{{ __('portal_addresses.common.address_book') }}</div>
                    <div class="shipment-helper-card__title">اختصر وقت الإدخال بالعناوين المحفوظة</div>
                    <div class="shipment-helper-card__body">{{ __('portal_addresses.common.picker_help') }}</div>
                </div>
                <div class="shipment-doc-card__actions">
                    <a href="{{ route($portalConfig['addresses_index_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.manage_link') }}</a>
                    @if($canManageAddressBook)
                        <a href="{{ route($portalConfig['addresses_create_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.new_address_cta') }}</a>
                    @endif
                </div>
            </div>
            <form method="GET" action="{{ route($portalConfig['create_route']) }}" class="filter-grid-fluid">
                @foreach($addressSelectionBaseQuery as $queryKey => $queryValue)
                    <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                @endforeach
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.sender_picker') }}</span>
                    <select class="f-input" name="sender_address" data-testid="sender-address-picker">
                        <option value="">{{ __('portal_addresses.common.picker_placeholder') }}</option>
                        @foreach($senderAddresses as $address)
                            <option value="{{ $address->id }}" @selected($selectedSenderAddressId === (string) $address->id)>{{ $address->label ?: $address->contact_name }}{{ $address->city ? ' - ' . $address->city : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.recipient_picker') }}</span>
                    <select class="f-input" name="recipient_address" data-testid="recipient-address-picker">
                        <option value="">{{ __('portal_addresses.common.picker_placeholder') }}</option>
                        @foreach($recipientAddresses as $address)
                            <option value="{{ $address->id }}" @selected($selectedRecipientAddressId === (string) $address->id)>{{ $address->label ?: $address->contact_name }}{{ $address->city ? ' - ' . $address->city : '' }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="filter-actions filter-actions-wide">
                    <button type="submit" class="btn btn-s" data-testid="saved-address-apply">{{ __('portal_addresses.common.use_saved_cta') }}</button>
                    @if(request()->filled('sender_address') || request()->filled('recipient_address'))
                        <a href="{{ route($portalConfig['create_route'], $addressSelectionBaseQuery) }}" class="btn btn-s" data-testid="saved-address-clear">{{ __('portal_addresses.common.clear_saved_cta') }}</a>
                    @endif
                </div>
            </form>
        </section>
    @endif

    <div class="shipment-flow-layout">
        <form method="POST" action="{{ route($portalConfig['store_route']) }}" class="shipment-form">
            @csrf
            @if(request()->filled('draft')) <input type="hidden" name="draft" value="{{ request()->query('draft') }}"> @endif
            @if(request()->filled('clone')) <input type="hidden" name="clone" value="{{ request()->query('clone') }}"> @endif
            <input type="hidden" name="sender_address_id" value="{{ $selectedSenderAddressId }}">
            <input type="hidden" name="recipient_address_id" value="{{ $selectedRecipientAddressId }}">

            @php
                $senderValidationStyle = $addressValidationStyle($senderValidation);
                $recipientValidationStyle = $addressValidationStyle($recipientValidation);
            @endphp

            <section class="shipment-form-section">
                <div class="shipment-form-section__head">
                    <div><div class="shipment-form-section__title">بيانات المرسل</div><div class="shipment-form-section__body">حدّد نقطة الانطلاق بدقة لتقليل الرفض أو اقتراحات التصحيح لاحقًا.</div></div>
                    <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="validate_sender" data-testid="validate-sender-address">{{ __('portal_shipments.address_validation.sender_cta') }}</button>
                </div>
                @if($selectedSenderAddress)<div class="shipment-note-card shipment-note-card--accent" data-testid="selected-sender-address-banner"><div class="shipment-note-card__title">{{ __('portal_addresses.common.selected_sender') }}</div><div class="shipment-note-card__body">{{ $selectedSenderAddress->label ?: $selectedSenderAddress->contact_name }}</div></div>@endif
                @include('pages.portal.shipments.partials.address-validation-card', ['result' => $senderValidation, 'prefix' => 'sender', 'style' => $senderValidationStyle, 'labels' => $validationFieldLabels])
                @include('pages.portal.shipments.partials.address-fields', ['prefix' => 'sender', 'prefill' => $prefill, 'statePlaceholder' => 'مثل NY أو TX', 'countryDefault' => 'SA'])
            </section>

            <section class="shipment-form-section">
                <div class="shipment-form-section__head">
                    <div><div class="shipment-form-section__title">بيانات المستلم</div><div class="shipment-form-section__body">هذا القسم يحدد الوجهة النهائية ويؤثر مباشرة على العروض والقيود التشغيلية.</div></div>
                    <button type="submit" class="btn btn-s" formaction="{{ route($portalConfig['address_validation_route']) }}" name="address_validation_action" value="validate_recipient" data-testid="validate-recipient-address">{{ __('portal_shipments.address_validation.recipient_cta') }}</button>
                </div>
                @if($selectedRecipientAddress)<div class="shipment-note-card shipment-note-card--accent" data-testid="selected-recipient-address-banner"><div class="shipment-note-card__title">{{ __('portal_addresses.common.selected_recipient') }}</div><div class="shipment-note-card__body">{{ $selectedRecipientAddress->label ?: $selectedRecipientAddress->contact_name }}</div></div>@endif
                @include('pages.portal.shipments.partials.address-validation-card', ['result' => $recipientValidation, 'prefix' => 'recipient', 'style' => $recipientValidationStyle, 'labels' => $validationFieldLabels])
                @include('pages.portal.shipments.partials.address-fields', ['prefix' => 'recipient', 'prefill' => $prefill, 'statePlaceholder' => 'مثل NY أو CA', 'countryDefault' => 'SA'])
            </section>

            <section class="shipment-form-section">
                <div class="shipment-form-section__head">
                    <div><div class="shipment-form-section__title">الطرد الأول</div><div class="shipment-form-section__body">أدخل الوزن والأبعاد الأساسية ليصبح الطلب جاهزًا للتسعير والمقارنة.</div></div>
                </div>
                <div class="shipment-form-grid">
                    <label><span class="f-label">الوزن</span><input class="f-input" type="number" step="0.01" min="0.01" name="parcels[0][weight]" value="{{ data_get($firstParcel, 'weight', '1.0') }}" required></label>
                    <label><span class="f-label">الطول</span><input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][length]" value="{{ data_get($firstParcel, 'length') }}"></label>
                    <label><span class="f-label">العرض</span><input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][width]" value="{{ data_get($firstParcel, 'width') }}"></label>
                    <label><span class="f-label">الارتفاع</span><input class="f-input" type="number" step="0.1" min="0.1" name="parcels[0][height]" value="{{ data_get($firstParcel, 'height') }}"></label>
                </div>
            </section>

            @if ($errors->any())
                <div class="shipment-flow-banner shipment-flow-banner--danger">
                    <div class="shipment-flow-banner__title">تعذر حفظ الطلب</div>
                    <div class="shipment-flow-banner__meta">{{ implode(' - ', $errors->all()) }}</div>
                </div>
            @endif

            <div class="shipment-form-actions">
                <button type="submit" class="btn btn-pr">حفظ المسودة وتشغيل التحقق</button>
                <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">إلغاء</a>
            </div>
        </form>

        <aside class="shipment-flow-rail">
            @if($draftShipment)
                <section class="shipment-helper-card shipment-helper-card--soft">
                    <div class="shipment-helper-card__eyebrow">ملخص آخر مسودة</div>
                    <div class="shipment-helper-card__title td-mono">{{ $draftReference }}</div>
                    <div class="shipment-key-value-grid">
                        <div class="shipment-key-value"><div class="shipment-key-value__label">الحالة</div><div class="shipment-key-value__value">{{ $currentBadge['label'] }}</div></div>
                        <div class="shipment-key-value"><div class="shipment-key-value__label">الوجهة</div><div class="shipment-key-value__value">{{ $draftDestination !== '' ? $draftDestination : 'غير محددة بعد' }}</div></div>
                        <div class="shipment-key-value"><div class="shipment-key-value__label">عدد الطرود</div><div class="shipment-key-value__value">{{ number_format($draftParcels) }}</div></div>
                    </div>
                    @if($canContinueToOffers)
                        <div class="shipment-action-card shipment-action-card--success">
                            <div class="shipment-action-card__title">المسودة جاهزة للانتقال</div>
                            <div class="shipment-action-card__body">يمكنك الآن مراجعة الأسعار والخدمات قبل اختيار العرض المناسب لهذه الشحنة.</div>
                            <div class="shipment-action-card__actions">
                                <a href="{{ route($portalConfig['offers_route'], ['id' => $draftShipment->id]) }}" class="btn btn-pr">مقارنة العروض المتاحة</a>
                            </div>
                        </div>
                    @endif
                </section>
            @endif

            <section class="shipment-helper-card shipment-helper-card--soft">
                <div class="shipment-helper-card__eyebrow">كيف تسير الرحلة</div>
                <div class="shipment-helper-card__title">من المسودة إلى الإصدار</div>
                <div class="shipment-helper-list">
                    <div class="shipment-key-value"><div class="shipment-key-value__label">1</div><div class="shipment-key-value__value">إدخال البيانات وتشغيل التحقق</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">2</div><div class="shipment-key-value__value">طلب عروض الشحن ومقارنتها</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">3</div><div class="shipment-key-value__value">إقرار المحتوى والمواد الخطرة</div></div>
                    <div class="shipment-key-value"><div class="shipment-key-value__label">4</div><div class="shipment-key-value__value">فحص المحفظة ثم الإصدار لدى الناقل</div></div>
                </div>
            </section>

            <section class="shipment-helper-card shipment-helper-card--soft">
                <div class="shipment-helper-card__eyebrow">آخر المسودات</div>
                <div class="shipment-helper-card__title">استكمال سريع من نفس الحساب</div>
                <div class="shipment-table-shell">
                    <table class="table">
                        <thead><tr><th>المرجع</th><th>الحالة</th><th>الوجهة</th><th>التاريخ</th></tr></thead>
                        <tbody>
                        @forelse($recentDrafts as $shipment)
                            <tr>
                                <td class="td-mono">{{ $shipment->reference_number ?? $shipment->id }}</td>
                                <td>{{ data_get($workflowBadges, $shipment->status . '.label', $shipment->status) }}</td>
                                <td>{{ $shipment->recipient_city ?? 'غير محددة' }}</td>
                                <td>{{ optional($shipment->created_at)->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-state">لا توجد مسودات سابقة بعد.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection
