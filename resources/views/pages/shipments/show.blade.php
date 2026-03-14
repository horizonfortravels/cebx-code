@extends('layouts.app')
@section('title', 'تفاصيل الشحنة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">تفاصيل الشحنة {{ $portalType === 'b2b' ? '#' . $shipment->reference_number : '' }}</h1>
    <div style="display:flex;gap:10px">
        @if($portalType === 'b2b' && $shipment->label_url)
            <a href="{{ route('shipments.label', $shipment) }}" class="btn btn-s">🖨️ طباعة البوليصة</a>
        @endif
        <a href="{{ route('shipments.index') }}" class="btn btn-s">← {{ $portalType === 'b2b' ? 'العودة' : 'رجوع' }}</a>
    </div>
</div>

{{-- ═══ STATUS BANNER ═══ --}}
@php
    $statusConfig = [
        'delivered' => ['label' => 'تم التسليم', 'color' => '#10B981', 'icon' => '✅', 'desc' => 'تم تسليم الشحنة بنجاح'],
        'in_transit' => ['label' => 'قيد الشحن', 'color' => '#8B5CF6', 'icon' => '🚚', 'desc' => 'الشحنة في الطريق إلى المستلم'],
        'out_for_delivery' => ['label' => 'خرج للتوصيل', 'color' => '#3B82F6', 'icon' => '🏃', 'desc' => 'المندوب في الطريق للتوصيل'],
        'processing' => ['label' => 'قيد المعالجة', 'color' => '#F59E0B', 'icon' => '⏳', 'desc' => 'جاري تجهيز الشحنة'],
        'cancelled' => ['label' => 'ملغي', 'color' => '#EF4444', 'icon' => '❌', 'desc' => 'تم إلغاء الشحنة'],
    ];
    $sc = $statusConfig[$shipment->status] ?? ['label' => $shipment->status, 'color' => '#64748B', 'icon' => '📦', 'desc' => ''];
@endphp
<div style="background:linear-gradient(135deg,{{ $sc['color'] }}33,{{ $sc['color'] }}11);border-radius:16px;padding:24px 28px;border:1px solid {{ $sc['color'] }}33;margin-bottom:24px;display:flex;justify-content:space-between;align-items:center">
    <div style="display:flex;align-items:center;gap:16px">
        <div style="width:56px;height:56px;border-radius:50%;background:{{ $sc['color'] }}33;display:flex;align-items:center;justify-content:center;font-size:28px">{{ $sc['icon'] }}</div>
        <div>
            <div style="font-weight:700;color:{{ $sc['color'] }};font-size:18px">{{ $sc['label'] }}</div>
            <div style="color:var(--tm);font-size:13px;margin-top:4px">{{ $sc['desc'] }}</div>
        </div>
    </div>
    <div style="text-align:left">
        <div style="font-family:monospace;font-size:20px;color:var(--tx);font-weight:700">{{ $shipment->reference_number }}</div>
        <div style="font-size:12px;color:var(--td);margin-top:4px">{{ $shipment->carrier_code }} • {{ $shipment->service_name ?? $shipment->service_code }}</div>
    </div>
</div>

<div class="grid-2-1">
    <div>
        {{-- ═══ SENDER & RECIPIENT ═══ --}}
        <div class="grid-2" style="margin-bottom:20px">
            <x-card title="📤 المرسل">
                <div style="font-weight:600;color:var(--tx);margin-bottom:8px">{{ $shipment->sender_name }}</div>
                <div style="font-size:13px;color:var(--tm);line-height:2">
                    📞 {{ $shipment->sender_phone }}<br>
                    📍 {{ $shipment->sender_city }}{{ $shipment->sender_state ? ', ' . $shipment->sender_state : '' }}<br>
                    🏠 {{ $shipment->sender_address_1 }}
                </div>
            </x-card>
            <x-card title="📥 المستلم">
                <div style="font-weight:600;color:var(--tx);margin-bottom:8px">{{ $shipment->recipient_name }}</div>
                <div style="font-size:13px;color:var(--tm);line-height:2">
                    📞 {{ $shipment->recipient_phone }}<br>
                    📍 {{ $shipment->recipient_city }}{{ $shipment->recipient_state ? ', ' . $shipment->recipient_state : '' }}<br>
                    🏠 {{ $shipment->recipient_address_1 }}
                </div>
            </x-card>
        </div>

        {{-- ═══ PARCEL DETAILS ═══ --}}
        <x-card title="📦 تفاصيل الطرد">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
                @foreach([
                    ['الوزن', ($shipment->total_weight ?? '—') . ' كغ'],
                    ['الأبعاد', ($shipment->parcels->first()?->length ?? '—') . '×' . ($shipment->parcels->first()?->width ?? '—') . '×' . ($shipment->parcels->first()?->height ?? '—')],
                    ['المحتوى', $shipment->parcels->first()?->description ?? '—'],
                    ['القطع', $shipment->parcels_count ?? 1],
                ] as $detail)
                    <div style="text-align:center;padding:16px;background:var(--sf);border-radius:10px">
                        <div style="font-size:12px;color:var(--td);margin-bottom:6px">{{ $detail[0] }}</div>
                        <div style="font-size:15px;font-weight:600;color:var(--tx)">{{ $detail[1] }}</div>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- ═══ COST ═══ --}}
        <x-card title="💰 {{ $portalType === 'b2b' ? 'التفاصيل المالية' : 'التكلفة' }}">
            @php
                $costItems = [['رسوم الشحن', $shipment->shipping_rate]];
                if($portalType === 'b2b' && $shipment->is_cod) $costItems[] = ['رسوم COD', 5.00];
                if($shipment->is_insured) $costItems[] = ['التأمين', $shipment->insurance_amount];
                $subtotal = array_sum(array_column($costItems, 1));
                $tax = $subtotal * 0.15;
                $costItems[] = ['الضريبة (15%)', $tax];
            @endphp
            @foreach($costItems as $item)
                <div class="info-row">
                    <span class="label">{{ $item[0] }}</span>
                    <span class="value" style="font-family:monospace">{{ number_format($item[1], 2) }} ر.س</span>
                </div>
            @endforeach
            <div style="display:flex;justify-content:space-between;padding-top:14px;font-weight:700">
                <span style="color:var(--tx)">الإجمالي</span>
                <span style="color:{{ $portalType === 'b2c' ? '#0D9488' : 'var(--pr)' }};font-size:20px;font-family:monospace">
                    {{ number_format($shipment->total_charge ?? ($subtotal + $tax), 2) }} ر.س
                </span>
            </div>
        </x-card>
    </div>

    <div>
        @if($portalType === 'b2b')
            {{-- B2B: EXTRA INFO --}}
            <x-card title="📋 معلومات إضافية">
                @foreach([
                    ['الناقل', $shipment->carrier_code],
                    ['الخدمة', $shipment->service_name ?? $shipment->service_code ?? '—'],
                    ['COD', $shipment->is_cod ? number_format($shipment->cod_amount, 2) . ' ر.س' : '—'],
                    ['المصدر', $shipment->source],
                    ['تاريخ الإنشاء', $shipment->created_at->format('d/m/Y')],
                    ['آخر تحديث', $shipment->updated_at->format('d/m/Y')],
                ] as $row)
                    <x-info-row :label="$row[0]" :value="$row[1]" />
                @endforeach
            </x-card>
        @endif

        {{-- ═══ TRACKING TIMELINE ═══ --}}
        <x-card title="📍 سجل التتبع">
            <x-timeline :items="$trackingHistory ?? []" :teal="$portalType === 'b2c'" />
            @if($portalType === 'b2c')
                <a href="{{ route('tracking.index', ['tracking_number' => $shipment->tracking_number]) }}" class="btn btn-pr" style="width:100%;margin-top:16px;text-align:center;background:#0D9488;display:block">📍 تتبع مباشر</a>
            @endif
        </x-card>

        @if($portalType === 'b2c')
            {{-- B2C: NEED HELP --}}
            <x-card title="📞 هل تحتاج مساعدة؟">
                <p style="font-size:13px;color:var(--tm);margin:0 0 16px">إذا واجهت أي مشكلة مع شحنتك، تواصل معنا</p>
                <a href="{{ route('support.index') }}" class="btn btn-pr" style="width:100%;text-align:center;margin-bottom:8px;background:#0D9488;display:block">💬 تواصل مع الدعم</a>
                <a href="tel:920000000" class="btn btn-s" style="width:100%;text-align:center;display:block">📞 اتصل بنا</a>
            </x-card>
        @endif

        {{-- ═══ ACTIONS ═══ --}}
        @if(!in_array($shipment->status, ['delivered', 'cancelled']))
            <x-card title="⚡ إجراءات">
                @if(!in_array($shipment->status, ['cancelled']))
                    <form method="POST" action="{{ route('shipments.cancel', $shipment) }}" style="margin-bottom:8px">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-dg" style="width:100%" onclick="return confirm('هل أنت متأكد من الإلغاء؟')">❌ إلغاء الشحنة</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('shipments.return', $shipment) }}">
                    @csrf
                    <button type="submit" class="btn btn-wn" style="width:100%">↩️ طلب إرجاع</button>
                </form>
            </x-card>
        @endif
    </div>
</div>
@endsection
