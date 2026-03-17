@extends('layouts.app')
@section('title', 'بوابة الأعمال | المحفظة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2b.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأعمال</a>
            <span style="margin:0 6px">/</span>
            <span>المحفظة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">المركز المالي للمنظمة</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            متابعة الرصيد والحركات الأخيرة تساعد فريق المنظمة على اتخاذ قرار سريع قبل فتح صفحة المحفظة الكاملة أو معالجة الشحنات المكلفة عبر المنصة.
        </p>
    </div>
    <a href="{{ route('wallet.index') }}" class="btn btn-pr">فتح المحفظة الكاملة</a>
</div>

<div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);border-radius:20px;padding:28px 32px;color:#fff;margin-bottom:24px">
    <div style="font-size:13px;opacity:.82;margin-bottom:6px">الرصيد التشغيلي</div>
    <div style="font-size:40px;font-weight:800;letter-spacing:-1px">
        {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }} {{ $wallet->currency ?? 'SAR' }}
    </div>
    <div style="font-size:13px;opacity:.78;margin-top:8px">
        @if($wallet)
            الرصيد المعلّق: {{ number_format((float) ($wallet->reserved_balance ?? $wallet->locked_balance ?? 0), 2) }} {{ $wallet->currency ?? 'SAR' }}
        @else
            لا توجد محفظة فعالة لهذا الحساب حتى الآن.
        @endif
    </div>
</div>

<x-card title="آخر الحركات">
    <div style="overflow:auto">
        <table class="table">
            <thead>
            <tr>
                <th>الوصف</th>
                <th>النوع</th>
                <th>المبلغ</th>
                <th>التاريخ</th>
            </tr>
            </thead>
            <tbody>
            @forelse($transactions as $entry)
                <tr>
                    <td>{{ $entry->description ?? 'عملية مالية' }}</td>
                    <td>{{ $entry->type ?? '—' }}</td>
                    <td>{{ number_format((float) $entry->amount, 2) }} {{ $wallet->currency ?? 'SAR' }}</td>
                    <td>{{ optional($entry->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="empty-state">لا توجد حركات حديثة. افتح المحفظة الكاملة لشحن الرصيد أو مراجعة التفاصيل.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-card>
@endsection
