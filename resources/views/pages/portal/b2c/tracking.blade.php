@extends('layouts.app')
@section('title', 'بوابة الأفراد | التتبع')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2c.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأفراد</a>
            <span style="margin:0 6px">/</span>
            <span>التتبع</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">لوحة تتبع الشحنات</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            ابحث بسرعة برقم التتبع أو المرجع لشحنات حسابك الفردي، ثم انتقل إلى مركز التتبع الكامل إذا احتجت لعرض كل الشحنات النشطة عبر شبكة الناقلين التابعة للمنصة ومسارها التفصيلي.
        </p>
    </div>
    <a href="{{ route('tracking.index') }}" class="btn btn-pr">فتح مركز التتبع</a>
</div>

<x-card title="بحث سريع">
    <form method="GET" action="{{ route('b2c.tracking.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
        <input type="text" name="q" value="{{ $searchQuery }}" placeholder="رقم تتبع أو مرجع" class="form-input" style="max-width:320px">
        <button type="submit" class="btn btn-pr">بحث</button>
    </form>

    @if($searchQuery !== '')
        <div style="margin-top:18px;padding:14px;border:1px solid var(--bd);border-radius:14px;background:rgba(59,130,246,.05)">
            @if($matchedShipment)
                <div style="font-weight:700;color:var(--tx)">تم العثور على شحنة مطابقة</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">
                    {{ $matchedShipment->tracking_number ?? $matchedShipment->reference_number ?? $matchedShipment->id }}
                    • الحالة: {{ $matchedShipment->status ?? '—' }}
                    • الوجهة: {{ $matchedShipment->recipient_city ?? 'غير محددة' }}
                </div>
            @else
                <div style="font-weight:700;color:var(--tx)">لا توجد نتيجة مطابقة</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">جرّب رقمًا آخر أو افتح مركز التتبع الكامل لعرض الشحنات النشطة.</div>
            @endif
        </div>
    @endif
</x-card>

<x-card title="الشحنات القابلة للتتبع">
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>رقم التتبع</th>
                <th>المرجع</th>
                <th>الحالة</th>
                <th>آخر تحديث</th>
            </tr>
            </thead>
            <tbody>
            @forelse($trackedShipments as $shipment)
                <tr>
                    <td class="td-mono">{{ $shipment->tracking_number ?? '—' }}</td>
                    <td>{{ $shipment->reference_number ?? '—' }}</td>
                    <td>{{ $shipment->status ?? '—' }}</td>
                    <td>{{ optional($shipment->updated_at)->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا توجد شحنات قابلة للتتبع حاليًا لهذا الحساب.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
