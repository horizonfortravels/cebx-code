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

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['shipments_index_route']) }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <span>وثائق الشحنة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">وثائق الشحنة وملفات الناقل</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            تعرض هذه الصفحة وثائق الشحنة التي أعادها الناقل بعد نجاح الإصدار، مثل ملصق الشحن والفاتورة التجارية وأي مستندات مرافقة أخرى.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
        <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى الإقرار</a>
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <x-card title="ملخص الشحنة">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مرجع الشحنة</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $shipment->reference_number ?? $shipment->id }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الشحنة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $workflowStatusLabels[$shipment->status] ?? $shipment->status }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الناقل</div>
                <div style="font-weight:700;color:var(--tx)">{{ $shipment->carrierShipment?->carrier_name ?? $selectedOffer?->carrier_name ?? '—' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">رقم التتبع</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $shipment->carrierShipment?->tracking_number ?? $shipment->tracking_number ?? $shipment->carrier_tracking_number ?? 'غير متاح بعد' }}</div>
            </div>
        </div>
    </x-card>

    <x-card title="جاهزية الوثائق">
        @if($hasDocuments)
            <div style="padding:16px;border-radius:16px;border:1px solid rgba(4,120,87,.22);background:rgba(4,120,87,.08)">
                <div style="font-size:18px;font-weight:800;color:#0f766e;margin-bottom:8px">الوثائق جاهزة للتنزيل والطباعة</div>
                <div style="color:var(--td);font-size:14px">
                    تم العثور على {{ count($documents) }} مستند/مستندات مرتبطة بهذه الشحنة. يمكنك تنزيل كل ملف على حدة من القائمة أدناه.
                </div>
            </div>
        @else
            <div style="padding:16px;border-radius:16px;border:1px dashed var(--bd);background:rgba(15,23,42,.02)">
                <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">لا توجد وثائق متاحة حتى الآن</div>
                <div style="color:var(--td);font-size:14px">
                    لم تُسجل أي وثائق قابلة للتنزيل لهذه الشحنة بعد. قد يكون الإصدار تم بدون إرجاع ملصق فوري أو أن الناقل لم يرسل ملفاته بعد.
                </div>
            </div>
        @endif
    </x-card>
</div>

<x-card title="قائمة المستندات">
    @if(!$hasDocuments)
        <div class="empty-state" style="padding:18px;border-radius:18px;border:1px dashed var(--bd);background:rgba(15,23,42,.02)">
            <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">لا توجد مستندات قابلة للتنزيل</div>
            <div style="color:var(--td);font-size:14px">
                عند توفر الملصقات أو الفواتير أو المستندات الجمركية من الناقل، ستظهر هنا بشكل منظم وآمن.
            </div>
        </div>
    @else
        <div style="display:flex;flex-direction:column;gap:14px">
            @foreach($documents as $document)
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:16px;border:1px solid var(--bd);border-radius:18px;background:white">
                    <div style="display:flex;flex-direction:column;gap:8px;min-width:260px;flex:1">
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                            <span style="font-size:18px;font-weight:800;color:var(--tx)">{{ $document['document_type'] }}</span>
                            <span class="td-mono" style="font-size:12px;color:var(--tm)">{{ $document['file_format'] }}</span>
                            <span class="td-mono" style="font-size:12px;color:var(--tm)">{{ $document['carrier_code'] }}</span>
                        </div>
                        <div style="font-size:14px;color:var(--td)">{{ $document['filename'] }}</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px">
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">طريقة الاسترجاع</div>
                                <div style="font-weight:700;color:var(--tx)">{{ $document['retrieval_mode'] }}</div>
                            </div>
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الحجم</div>
                                <div style="font-weight:700;color:var(--tx)">{{ $document['size'] ? number_format(((int) $document['size']) / 1024, 1) . ' KB' : 'غير محدد' }}</div>
                            </div>
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">تاريخ الإتاحة</div>
                                <div style="font-weight:700;color:var(--tx)">{{ $document['created_at'] ? \Illuminate\Support\Carbon::parse($document['created_at'])->format('Y-m-d H:i') : '—' }}</div>
                            </div>
                        </div>
                        @if(!empty($document['notes']))
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">ملاحظات من الناقل</div>
                                <ul style="margin:0;padding-right:18px;color:var(--td);font-size:13px">
                                    @foreach($document['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;min-width:180px">
                        <div style="font-size:13px;color:var(--td)">
                            @if($document['available'])
                                المستند متاح حاليًا للتنزيل.
                            @else
                                المستند غير متاح حاليًا.
                            @endif
                        </div>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            @if(!empty($document['previewable']) && !empty($document['preview_route']))
                                <a href="{{ $document['preview_route'] }}" class="btn btn-s" target="_blank" rel="noopener noreferrer" @if(!$document['available']) aria-disabled="true" @endif>
                                    &#1593;&#1585;&#1590; &#1575;&#1604;&#1605;&#1587;&#1578;&#1606;&#1583;
                                </a>
                            @endif
                            <a href="{{ $document['download_route'] }}" class="btn btn-pr" download="{{ $document['filename'] }}" @if(!$document['available']) aria-disabled="true" @endif>
                                &#1578;&#1606;&#1586;&#1610;&#1604; &#1575;&#1604;&#1605;&#1587;&#1578;&#1606;&#1583;
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-card>
@endsection
