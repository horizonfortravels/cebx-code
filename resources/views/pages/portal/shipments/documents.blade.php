@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | وثائق الشحنة')

@section('content')
@php
    $documents = $documents ?? [];
    $hasDocuments = count($documents) > 0;
    $selectedOffer = $shipment->selectedRateOption;
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
    ];
@endphp

<div class="shipment-flow-stack">
    <x-page-header
        :eyebrow="($portalConfig['label'] ?? 'البوابة') . ' / الشحنات / الوثائق'"
        title="وثائق الشحنة وملفات الناقل"
        subtitle="نزّل المستندات الجاهزة للطباعة أو العرض، وراجع نوع كل ملف وطريقة استرجاعه ضمن نفس مساحة العمل."
        meta="هذه الصفحة تُكمل مرحلة الإصدار وتجمع الملفات الرسمية المرتبطة بهذه الشحنة."
    >
        <a href="{{ route($portalConfig['show_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى حالة الشحنة</a>
        <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
    </x-page-header>

    <x-shipment-workflow-stepper
        current="documents"
        :create-route="route($portalConfig['create_route'], ['draft' => $shipment->id])"
        :offers-route="route($portalConfig['offers_route'], ['id' => $shipment->id])"
        :declaration-route="route($portalConfig['declaration_route'], ['id' => $shipment->id])"
        :show-route="route($portalConfig['show_route'], ['id' => $shipment->id])"
        :documents-route="route($portalConfig['documents_route'], ['id' => $shipment->id])"
        :state-overrides="['create' => 'complete', 'offers' => 'complete', 'declaration' => 'complete', 'show' => 'complete', 'documents' => 'current']"
    />

    <section class="shipment-flow-hero">
        <div class="shipment-flow-hero__head">
            <div>
                <div class="shipment-flow-hero__eyebrow">مساحة التنزيل والطباعة</div>
                <h2 class="shipment-flow-hero__title">الوثائق الجاهزة للشحنة</h2>
                <p class="shipment-flow-hero__body">بعد الإصدار لدى الناقل، تظهر هنا ملفات الشحنة الرسمية مثل الملصق والفاتورة والمستندات المرافقة. استخدم العرض السريع عندما يكون الملف قابلاً للمعاينة، أو نزّله مباشرةً للطباعة والأرشفة.</p>
            </div>
            <span class="shipment-status-pill shipment-status-pill--{{ $hasDocuments ? 'success' : 'warning' }}">{{ $hasDocuments ? 'وثائق متاحة' : 'بانتظار الوثائق' }}</span>
        </div>
        <div class="shipment-flow-summary-grid">
            <div class="shipment-summary-card shipment-summary-card--soft"><div class="shipment-summary-card__eyebrow">مرجع الشحنة</div><div class="shipment-summary-card__value td-mono">{{ $shipment->reference_number ?? $shipment->id }}</div><div class="shipment-summary-card__meta">{{ $workflowStatusLabels[$shipment->status] ?? $shipment->status }}</div></div>
            <div class="shipment-summary-card shipment-summary-card--accent"><div class="shipment-summary-card__eyebrow">الناقل المرتبط</div><div class="shipment-summary-card__value">{{ \App\Support\PortalShipmentLabeler::carrier((string) ($shipment->carrierShipment?->carrier_code ?? $selectedOffer?->carrier_code ?? $shipment->carrier_code ?? ''), (string) ($shipment->carrierShipment?->carrier_name ?? $selectedOffer?->carrier_name ?? __('portal_shipments.common.not_specified'))) }}</div><div class="shipment-summary-card__meta">رقم التتبع: <span class="td-mono">{{ $shipment->carrierShipment?->tracking_number ?? $shipment->tracking_number ?? $shipment->carrier_tracking_number ?? 'غير متاح بعد' }}</span></div></div>
            <div class="shipment-summary-card {{ $hasDocuments ? 'shipment-summary-card--success' : 'shipment-summary-card--warning' }}"><div class="shipment-summary-card__eyebrow">جاهزية الوثائق</div><div class="shipment-summary-card__value">{{ number_format(count($documents)) }}</div><div class="shipment-summary-card__meta">{{ $hasDocuments ? 'مستند/مستندات جاهزة للتنزيل والطباعة.' : 'ستظهر المستندات هنا فور إتاحتها من الناقل.' }}</div></div>
        </div>
    </section>

    @if(!$hasDocuments)
        <section class="shipment-empty-state">
            <div class="shipment-empty-state__title">لا توجد مستندات قابلة للتنزيل</div>
            <div class="shipment-empty-state__body">عند توفر الملصقات أو الفواتير أو المستندات الجمركية من الناقل، ستظهر هنا بشكل منظم وآمن. حتى ذلك الحين يمكنك الرجوع إلى صفحة حالة الشحنة لمتابعة التحديثات الزمنية.</div>
            <div class="shipment-form-actions"><a href="{{ route($portalConfig['show_route'], ['id' => $shipment->id]) }}" class="btn btn-pr">فتح حالة الشحنة</a></div>
        </section>
    @else
        <section class="shipment-doc-list">
            @foreach($documents as $document)
                <article class="shipment-doc-card">
                    <div class="shipment-doc-card__head">
                        <div>
                            <div class="shipment-doc-card__eyebrow">{{ $document['document_type_label'] ?? $document['document_type'] }}</div>
                            <div class="shipment-doc-card__title">{{ $document['filename'] }}</div>
                            <div class="shipment-doc-card__meta">{{ $document['carrier_label'] ?? $document['carrier_code'] }} / {{ $document['format_label'] ?? $document['file_format'] }}</div>
                        </div>
                    </div>
                    <div class="shipment-breakdown-grid">
                        <div class="shipment-breakdown"><div class="shipment-breakdown__label">طريقة الاسترجاع</div><div class="shipment-breakdown__value">{{ $document['retrieval_mode_label'] ?? $document['retrieval_mode'] }}</div></div>
                        <div class="shipment-breakdown"><div class="shipment-breakdown__label">الحجم</div><div class="shipment-breakdown__value">{{ $document['size'] ? number_format(((int) $document['size']) / 1024, 1) . ' كيلوبايت' : 'غير محدد' }}</div></div>
                        <div class="shipment-breakdown"><div class="shipment-breakdown__label">تاريخ الإتاحة</div><div class="shipment-breakdown__value">{{ $document['created_at'] ? \Illuminate\Support\Carbon::parse($document['created_at'])->format('Y-m-d H:i') : '—' }}</div></div>
                    </div>
                    @if(!empty($document['notes']))
                        <div class="shipment-note-card shipment-note-card--accent">
                            <div class="shipment-note-card__title">ملاحظات من الناقل</div>
                            <div class="shipment-note-card__body">{{ implode(' - ', (array) $document['notes']) }}</div>
                        </div>
                    @endif
                    <div class="shipment-doc-card__actions">
                        @if(!empty($document['previewable']) && !empty($document['preview_route']))
                            <a href="{{ $document['preview_route'] }}" class="btn btn-s" target="_blank" rel="noopener noreferrer" @if(!$document['available']) aria-disabled="true" @endif>عرض المستند</a>
                        @endif
                        <a href="{{ $document['download_route'] }}" class="btn btn-pr" download="{{ $document['filename'] }}" @if(!$document['available']) aria-disabled="true" @endif>تنزيل المستند</a>
                    </div>
                </article>
            @endforeach
        </section>
    @endif
</div>
@endsection
