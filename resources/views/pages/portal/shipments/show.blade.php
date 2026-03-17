@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? 'البوابة') . ' | حالة الشحنة')

@php
    $timeline = $timeline ?? ['events' => [], 'current_status' => null, 'current_status_label' => null, 'last_updated' => null];
    $events = $timeline['events'] ?? [];
    $documents = $documents ?? [];
    $completionFeedback = $completionFeedback ?? session('shipment_completion_feedback');
    $reservation = $shipment->balanceReservation;
    $reservationStatus = $reservation?->status;
    $reservationStatusLabels = [
        \App\Models\WalletHold::STATUS_ACTIVE => 'حجز نشط',
        \App\Models\WalletHold::STATUS_CAPTURED => 'تم الالتقاط',
        \App\Models\WalletHold::STATUS_RELEASED => 'تم الإفراج',
        \App\Models\WalletHold::STATUS_EXPIRED => 'منتهي',
    ];
    $selectedOfferAmount = ($shipment->selectedRateOption ?? $shipment->rateQuote?->selectedOption)?->retail_rate ?? $shipment->reserved_amount ?? $shipment->total_charge ?? null;
    $selectedOfferCurrency = ($shipment->selectedRateOption ?? $shipment->rateQuote?->selectedOption)?->currency ?? $shipment->currency ?? 'SAR';
    $canTriggerWalletPreflight = $canTriggerWalletPreflight ?? false;
    $canIssueShipment = $canIssueShipment ?? false;
    $showWalletPreflightAction = $canTriggerWalletPreflight
        && (
            $shipment->status === \App\Models\Shipment::STATUS_DECLARATION_COMPLETE
            || ($shipment->status === \App\Models\Shipment::STATUS_PAYMENT_PENDING && $reservationStatus === null)
        )
        && ! $shipment->carrierShipment
        && $reservationStatus !== \App\Models\WalletHold::STATUS_CAPTURED
        && $reservationStatus !== \App\Models\WalletHold::STATUS_ACTIVE;
    $showCarrierIssueAction = $canIssueShipment
        && $shipment->status === \App\Models\Shipment::STATUS_PAYMENT_PENDING
        && $reservationStatus === \App\Models\WalletHold::STATUS_ACTIVE
        && ! $shipment->carrierShipment;
    $selectedOffer = $shipment->selectedRateOption ?? $shipment->rateQuote?->selectedOption;
    $trackingNumber = $shipment->tracking_number ?? $shipment->carrierShipment?->tracking_number ?? $shipment->carrier_tracking_number ?? 'غير متاح بعد';
@endphp

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['shipments_index_route']) }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <span>حالة الشحنة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">الحالة الزمنية للشحنة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:780px">
            تعرض هذه الصفحة الحالة الحالية للشحنة بعد الإصدار، مع التسلسل الزمني الكامل للأحداث القادمة من النظام والناقل في سجل واحد واضح.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['shipments_index_route']) }}" class="btn btn-s">العودة إلى الشحنات</a>
        @if(!empty($documents))
            <a href="{{ route($portalConfig['documents_route'], ['id' => $shipment->id]) }}" class="btn btn-pr">عرض المستندات</a>
        @endif
    </div>
</div>

@if($completionFeedback)
    @php($isSuccessFeedback = ($completionFeedback['level'] ?? 'warning') === 'success')
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid {{ $isSuccessFeedback ? 'rgba(4,120,87,.22)' : 'rgba(185,28,28,.18)' }};background:{{ $isSuccessFeedback ? 'rgba(4,120,87,.08)' : 'rgba(185,28,28,.06)' }}">
        <div style="font-size:20px;font-weight:800;color:var(--tx)">{{ $completionFeedback['message'] ?? 'تم تحديث مرحلة إكمال الشحنة.' }}</div>
        @if(!empty($completionFeedback['next_action']))
            <div style="margin-top:10px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الخطوة التالية:</strong>
                {{ $completionFeedback['next_action'] }}
            </div>
        @endif
        @if(!empty($completionFeedback['error_code']))
            <div class="td-mono" style="margin-top:10px;font-size:12px;color:var(--tm)">{{ $completionFeedback['error_code'] }}</div>
        @endif
    </div>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <x-card title="الملخص الحالي">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مرجع الشحنة</div>
                <div class="td-mono" style="font-weight:800;color:var(--tx)">{{ $shipment->reference_number ?? $shipment->id }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">رقم التتبع</div>
                <div class="td-mono" style="font-weight:800;color:var(--tx)">{{ $trackingNumber }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الحالة المعيارية الحالية</div>
                <div style="font-weight:800;color:var(--tx)">{{ $timeline['current_status_label'] ?? 'غير متاحة' }}</div>
                <div class="td-mono" style="font-size:12px;color:var(--tm);margin-top:4px">{{ $timeline['current_status'] ?? 'unknown' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">آخر تحديث</div>
                <div style="font-weight:800;color:var(--tx)">
                    {{ !empty($timeline['last_updated']) ? \Illuminate\Support\Carbon::parse($timeline['last_updated'])->format('Y-m-d H:i') : 'لا يوجد بعد' }}
                </div>
            </div>
        </div>
    </x-card>

    <x-card title="بيانات الإصدار والنقل">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الناقل</div>
                <div style="font-weight:800;color:var(--tx)">{{ $shipment->carrierShipment?->carrier_name ?? $selectedOffer?->carrier_name ?? 'غير محدد' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الخدمة</div>
                <div style="font-weight:800;color:var(--tx)">{{ $shipment->carrierShipment?->service_name ?? $selectedOffer?->service_name ?? $shipment->service_name ?? 'غير محددة' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة سير العمل</div>
                <div style="font-weight:800;color:var(--tx)">{{ $shipment->status ?? 'غير متاحة' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">عدد الأحداث</div>
                <div style="font-weight:800;color:var(--tx)">{{ number_format((int) ($timeline['total_events'] ?? 0)) }}</div>
            </div>
        </div>
    </x-card>
</div>

<x-card title="إكمال الشحنة" style="margin-bottom:24px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:start">
        <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:white">
            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الحجز المالي</div>
            <div style="font-weight:800;color:var(--tx)">
                {{ $reservationStatus ? ($reservationStatusLabels[$reservationStatus] ?? $reservationStatus) : 'لا يوجد حجز بعد' }}
            </div>
            <div style="font-size:13px;color:var(--td);margin-top:6px">
                @if($selectedOfferAmount !== null)
                    {{ number_format((float) $selectedOfferAmount, 2) }} {{ $selectedOfferCurrency }}
                @else
                    لم يتم تحديد قيمة قابلة للحجز بعد.
                @endif
            </div>
        </div>

        <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:white">
            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الإصدار</div>
            <div style="font-weight:800;color:var(--tx)">
                {{ $shipment->carrierShipment ? 'تم الإصدار لدى الناقل' : 'لم يتم الإصدار بعد' }}
            </div>
            <div style="font-size:13px;color:var(--td);margin-top:6px">
                {{ $shipment->carrierShipment?->tracking_number ? 'رقم التتبع: ' . $shipment->carrierShipment->tracking_number : 'سيظهر رقم التتبع هنا بعد نجاح الإصدار.' }}
            </div>
        </div>

        <div style="padding:14px;border:1px solid var(--bd);border-radius:16px;background:rgba(15,23,42,.02)">
            @if($showWalletPreflightAction)
                <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">فحص المحفظة قبل الإصدار</div>
                <div style="color:var(--td);font-size:14px;margin-bottom:12px">
                    نفّذ الحجز المالي لهذه الشحنة قبل محاولة الإصدار لدى الناقل.
                </div>
                <form method="POST" action="{{ route($portalConfig['preflight_route'], ['id' => $shipment->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-pr" data-testid="wallet-preflight-button">تنفيذ فحص المحفظة</button>
                </form>
            @elseif($showCarrierIssueAction)
                <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">الإصدار لدى الناقل</div>
                <div style="color:var(--td);font-size:14px;margin-bottom:12px">
                    الحجز المالي نشط، ويمكنك الآن إرسال الشحنة إلى الناقل وإكمال الإصدار.
                </div>
                <form method="POST" action="{{ route($portalConfig['issue_route'], ['id' => $shipment->id]) }}">
                    @csrf
                    <button type="submit" class="btn btn-pr" data-testid="carrier-issue-button">إصدار الشحنة لدى الناقل</button>
                </form>
            @elseif($shipment->carrierShipment)
                <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">اكتمل الإصدار</div>
                <div style="color:var(--td);font-size:14px">
                    تم ربط هذه الشحنة بالناقل، ويمكنك الآن تنزيل المستندات ومتابعة الحالة الزمنية.
                </div>
            @elseif($shipment->status === \App\Models\Shipment::STATUS_REQUIRES_ACTION)
                <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">الشحنة متوقفة</div>
                <div style="color:var(--td);font-size:14px">
                    توجد متطلبات امتثال أو مراجعة يدوية تمنع المتابعة الذاتية لهذه الشحنة حاليًا.
                </div>
                <div style="margin-top:12px">
                    <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة إقرار المحتوى</a>
                </div>
            @else
                <div style="font-size:16px;font-weight:800;color:var(--tx);margin-bottom:8px">أكمل الخطوات السابقة أولًا</div>
                <div style="color:var(--td);font-size:14px">
                    لا يمكن تشغيل فحص المحفظة أو الإصدار قبل اكتمال اختيار العرض وإقرار المحتوى لهذه الشحنة.
                </div>
                <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
                    <a href="{{ route($portalConfig['offers_route'], ['id' => $shipment->id]) }}" class="btn btn-s">العودة إلى العروض</a>
                    <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">مراجعة الإقرار</a>
                </div>
            @endif
        </div>
    </div>
</x-card>

<div class="grid-2">
    <x-card title="التسلسل الزمني">
        @if($events === [])
            <div style="padding:16px;border:1px dashed var(--bd);border-radius:16px;background:rgba(15,23,42,.02)">
                <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">لا توجد أحداث زمنية مسجلة بعد</div>
                <div style="color:var(--td);font-size:14px">
                    سيظهر هنا تاريخ الشحنة بعد الإصدار، بما في ذلك تحديثات الناقل والمستندات المتاحة وأي حالة معيارية لاحقة.
                </div>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:14px">
                @foreach($events as $event)
                    <div style="display:flex;gap:14px;padding:16px;border:1px solid var(--bd);border-radius:18px;background:white">
                        <div style="width:14px;min-width:14px;height:14px;border-radius:999px;background:{{ $loop->last ? '#0f766e' : 'var(--pr)' }};margin-top:6px"></div>
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px">
                            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
                                <div>
                                    <div style="font-size:17px;font-weight:800;color:var(--tx)">{{ $event['event_type_label'] ?? $event['description'] }}</div>
                                    <div style="color:var(--td);font-size:13px;margin-top:4px">{{ $event['description'] ?? '' }}</div>
                                </div>
                                <div style="text-align:left;min-width:170px">
                                    <div class="td-mono" style="font-size:12px;color:var(--tm)">{{ $event['event_type'] ?? '' }}</div>
                                    <div style="font-size:13px;color:var(--td);margin-top:4px">
                                        {{ !empty($event['event_time']) ? \Illuminate\Support\Carbon::parse($event['event_time'])->format('Y-m-d H:i') : 'غير محدد' }}
                                    </div>
                                </div>
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px">
                                <div>
                                    <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الحالة المعيارية</div>
                                    <div style="font-weight:700;color:var(--tx)">{{ $event['status_label'] ?? 'غير متاحة' }}</div>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مصدر الحدث</div>
                                    <div style="font-weight:700;color:var(--tx)">{{ $event['source_label'] ?? $event['source'] ?? 'النظام' }}</div>
                                </div>
                                <div>
                                    <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الموقع</div>
                                    <div style="font-weight:700;color:var(--tx)">{{ $event['location'] ?? 'غير محدد' }}</div>
                                </div>
                            </div>
                            @if(!empty($event['correlation_id']))
                                <div class="td-mono" style="font-size:12px;color:var(--tm)">Correlation: {{ $event['correlation_id'] }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card title="المستندات المرتبطة">
        @if($documents === [])
            <div style="padding:16px;border:1px dashed var(--bd);border-radius:16px;background:rgba(15,23,42,.02)">
                <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">لا توجد مستندات متاحة حاليًا</div>
                <div style="color:var(--td);font-size:14px">
                    عند إتاحة ملصق الشحن أو بقية مستندات الناقل ستظهر هنا، كما ستظهر في التسلسل الزمني كحدث مستقل.
                </div>
            </div>
        @else
            <div style="display:flex;flex-direction:column;gap:12px">
                @foreach($documents as $document)
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:14px;border:1px solid var(--bd);border-radius:16px;background:white">
                        <div>
                            <div style="font-weight:800;color:var(--tx)">{{ $document['document_type'] }}</div>
                            <div style="font-size:13px;color:var(--td);margin-top:4px">{{ $document['filename'] }}</div>
                            <div class="td-mono" style="font-size:12px;color:var(--tm);margin-top:4px">{{ $document['carrier_code'] }} / {{ $document['file_format'] }}</div>
                        </div>
                        <div style="display:flex;align-items:center">
                            <a href="{{ $document['download_route'] }}" class="btn btn-s">تنزيل المستند</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
@endsection
