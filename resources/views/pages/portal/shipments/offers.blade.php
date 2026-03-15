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
    $documentsAvailable = $shipment->carrierDocuments()->where('is_available', true)->exists();
    $documentsRoute = request()->routeIs('b2b.*')
        ? route('b2b.shipments.documents.index', ['id' => $shipment->id])
        : route('b2c.shipments.documents.index', ['id' => $shipment->id]);
@endphp

<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['index_route']) }}" style="color:inherit;text-decoration:none">الشحنات</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['create_route'], ['draft' => $shipment->id]) }}" style="color:inherit;text-decoration:none">المسودة الحالية</a>
            <span style="margin:0 6px">/</span>
            <span>مقارنة العروض</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">مقارنة عروض الشحن</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:820px">
            راجع العروض المتاحة لهذه الشحنة، قارن بين السعر وموعد الوصول والملاحظات التشغيلية، ثم اختر عرضًا واحدًا فقط للانتقال إلى المرحلة التالية.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['create_route'], ['draft' => $shipment->id]) }}" class="btn btn-s">العودة إلى المسودة</a>
        @if($selectedOptionId !== '' || in_array($shipment->status, ['declaration_required', 'declaration_complete', 'requires_action'], true))
            <a href="{{ route($portalConfig['declaration_route'], ['id' => $shipment->id]) }}" class="btn btn-s">الانتقال إلى إقرار المحتوى</a>
        @endif
        @if($documentsAvailable)
            <a href="{{ $documentsRoute }}" class="btn btn-s">عرض الوثائق</a>
        @endif
        @if($canRefreshOffers)
            <form method="POST" action="{{ route($portalConfig['offers_fetch_route'], ['id' => $shipment->id]) }}">
                @csrf
                <button type="submit" class="btn btn-pr">{{ $hasOffers ? 'تحديث العروض' : 'جلب العروض الآن' }}</button>
            </form>
        @endif
    </div>
</div>

@if($offerFeedback)
    <div style="margin-bottom:20px;padding:18px;border-radius:18px;border:1px solid {{ ($offerFeedback['level'] ?? 'warning') === 'success' ? 'rgba(4,120,87,.22)' : 'rgba(185,28,28,.18)' }};background:{{ ($offerFeedback['level'] ?? 'warning') === 'success' ? 'rgba(4,120,87,.08)' : 'rgba(185,28,28,.06)' }}">
        <div style="font-size:20px;font-weight:800;color:var(--tx)">{{ $offerFeedback['message'] ?? 'تم تحديث حالة العروض.' }}</div>
        @if(!empty($offerFeedback['next_action']))
            <div style="margin-top:10px;color:var(--td);font-size:14px">
                <strong style="color:var(--tx)">الخطوة التالية:</strong>
                {{ $offerFeedback['next_action'] }}
            </div>
        @endif
        @if(!empty($offerFeedback['error_code']))
            <div class="td-mono" style="margin-top:10px;font-size:12px;color:var(--tm)">{{ $offerFeedback['error_code'] }}</div>
        @endif
    </div>
@endif

<div class="grid-2" style="margin-bottom:24px">
    <x-card title="ملخص الشحنة الحالية">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مرجع الشحنة</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $shipment->reference_number ?? $shipment->id }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة الشحنة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $shipmentStatusLabel }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الوجهة</div>
                <div style="font-weight:700;color:var(--tx)">{{ $shipment->recipient_city }} / {{ $shipment->recipient_country }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">عدد الطرود</div>
                <div style="font-weight:700;color:var(--tx)">{{ $shipment->parcels_count ?: $shipment->parcels()->count() }}</div>
            </div>
        </div>
    </x-card>

    <x-card title="حالة التسعير والعرض المختار">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">مرجع عرض الأسعار</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ data_get($offersPayload, 'rate_quote_id', 'لم يُنشأ بعد') }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">حالة عرض الأسعار</div>
                <div style="font-weight:700;color:var(--tx)">{{ data_get($offersPayload, 'quote_status', $hasOffers ? 'completed' : 'pending') }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">انتهاء الصلاحية</div>
                <div style="font-weight:700;color:var(--tx)">{{ data_get($offersPayload, 'expires_at') ? \Illuminate\Support\Carbon::parse(data_get($offersPayload, 'expires_at'))->format('Y-m-d H:i') : 'غير متاح بعد' }}</div>
            </div>
            <div>
                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">العرض المثبت</div>
                <div class="td-mono" style="font-weight:700;color:var(--tx)">{{ $selectedOptionId !== '' ? $selectedOptionId : 'لم يتم الاختيار بعد' }}</div>
            </div>
        </div>
        @if($isExpired)
            <div style="margin-top:16px;padding:12px;border-radius:14px;background:rgba(185,28,28,.06);border:1px solid rgba(185,28,28,.18);color:#7f1d1d">
                انتهت صلاحية العروض الحالية. أعد جلب العروض قبل محاولة اختيار عرض جديد.
            </div>
        @endif
    </x-card>
</div>

@if(!$hasOffers)
    <x-card title="العروض المتاحة">
        <div style="display:flex;flex-direction:column;gap:14px">
            <div class="empty-state" style="padding:20px;border-radius:18px;border:1px dashed var(--bd);background:rgba(15,23,42,.02)">
                <div style="font-size:18px;font-weight:800;color:var(--tx);margin-bottom:8px">
                    {{ $offerError ? 'الشحنة ليست جاهزة لعرض الأسعار بعد' : 'لا توجد عروض متاحة حاليًا' }}
                </div>
                <div style="color:var(--td);font-size:14px;max-width:760px">
                    {{ $offerError['message'] ?? 'لم يتم توليد عروض لهذه الشحنة بعد. يمكنك طلب العروض عندما تكون الشحنة في حالة ready_for_rates.' }}
                </div>
                @if(!empty($offerError['next_action']))
                    <div style="margin-top:10px;color:var(--td);font-size:14px">
                        <strong style="color:var(--tx)">الإجراء المقترح:</strong>
                        {{ $offerError['next_action'] }}
                    </div>
                @endif
            </div>

            @if($canRefreshOffers)
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <form method="POST" action="{{ route($portalConfig['offers_fetch_route'], ['id' => $shipment->id]) }}">
                        @csrf
                        <button type="submit" class="btn btn-pr">جلب العروض لهذه الشحنة</button>
                    </form>
                    <a href="{{ route($portalConfig['create_route'], ['draft' => $shipment->id]) }}" class="btn btn-s">مراجعة بيانات المسودة</a>
                </div>
            @endif
        </div>
    </x-card>
@else
    <x-card title="العروض المتاحة للمقارنة">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
            @foreach($offers as $offer)
                @php
                    $isSelected = (bool) ($offer['is_selected'] ?? false);
                    $isAvailable = (bool) ($offer['is_available'] ?? true);
                    $deliveryLabel = data_get($offer, 'estimated_delivery.label') ?: 'غير متوفر';
                    $borderColor = $isSelected ? '#0f766e' : ($isAvailable ? 'var(--bd)' : 'rgba(185,28,28,.18)');
                    $background = $isSelected ? 'rgba(15,118,110,.06)' : 'white';
                @endphp
                <div style="border:1px solid {{ $borderColor }};border-radius:18px;padding:18px;background:{{ $background }};display:flex;flex-direction:column;gap:14px">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                        <div>
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الناقل</div>
                            <div style="font-size:18px;font-weight:800;color:var(--tx)">{{ $offer['carrier_name'] }}</div>
                            <div class="td-mono" style="font-size:12px;color:var(--tm);margin-top:4px">{{ $offer['carrier_code'] }} / {{ $offer['service_code'] }}</div>
                        </div>
                        <div style="text-align:left">
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">السعر المعروض</div>
                            <div style="font-size:24px;font-weight:900;color:var(--tx)">{{ number_format((float) ($offer['retail_rate'] ?? 0), 2) }}</div>
                            <div style="font-size:12px;color:var(--tm)">{{ $offer['currency'] }}</div>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
                        <div style="padding:12px;border:1px solid var(--bd);border-radius:14px">
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">الخدمة</div>
                            <div style="font-weight:700;color:var(--tx)">{{ $offer['service_name'] }}</div>
                        </div>
                        <div style="padding:12px;border:1px solid var(--bd);border-radius:14px">
                            <div style="font-size:12px;color:var(--tm);margin-bottom:4px">موعد الوصول</div>
                            <div style="font-weight:700;color:var(--tx)">{{ $deliveryLabel }}</div>
                        </div>
                    </div>

                    @if(!empty($offer['badges']))
                        <div style="display:flex;flex-wrap:wrap;gap:8px">
                            @foreach($offer['badges'] as $badge)
                                <span style="padding:6px 10px;border-radius:999px;background:rgba(59,130,246,.08);color:var(--tx);font-size:12px;font-weight:700">{{ $badge['label'] }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div style="display:flex;flex-direction:column;gap:8px">
                        @if(!empty($offer['notes']))
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">ملاحظات الخدمة</div>
                                <ul style="margin:0;padding-right:18px;color:var(--td);font-size:13px">
                                    @foreach($offer['notes'] as $note)
                                        <li>{{ $note }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(!empty($offer['restrictions']))
                            <div>
                                <div style="font-size:12px;color:var(--tm);margin-bottom:4px">قيود أو أسباب عدم التوفر</div>
                                <ul style="margin:0;padding-right:18px;color:#991b1b;font-size:13px">
                                    @foreach($offer['restrictions'] as $restriction)
                                        <li>{{ $restriction }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>

                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:center;margin-top:auto">
                        <div style="font-size:13px;color:var(--td)">
                            @if($isSelected)
                                <strong style="color:#0f766e">هذا هو العرض المختار حاليًا.</strong>
                            @elseif(!$isAvailable)
                                <strong style="color:#991b1b">هذا العرض غير متاح حاليًا.</strong>
                            @else
                                قارن هذا العرض مع بقية الخيارات قبل التثبيت.
                            @endif
                        </div>
                        @if($canSelectOffers)
                            <form method="POST" action="{{ route($portalConfig['offers_select_route'], ['id' => $shipment->id]) }}">
                                @csrf
                                <input type="hidden" name="option_id" value="{{ $offer['id'] }}">
                                <button type="submit" class="btn {{ $isSelected ? 'btn-s' : 'btn-pr' }}" {{ (!$isAvailable || $isExpired) ? 'disabled' : '' }}>
                                    {{ $isSelected ? 'تم التثبيت' : 'اختيار هذا العرض' }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-card>
@endif
@endsection
