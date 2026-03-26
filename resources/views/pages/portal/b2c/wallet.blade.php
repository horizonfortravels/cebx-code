@extends('layouts.app')
@section('title', 'بوابة الأفراد | المحفظة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('b2c.dashboard') }}" style="color:inherit;text-decoration:none">بوابة الأفراد</a>
            <span style="margin:0 6px">/</span>
            <span>المحفظة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">محفظتك الشخصية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:720px">
            هذه الصفحة تعطيك نظرة سريعة على رصيد الحساب الفردي والحركة الأخيرة قبل الانتقال إلى مركز المحفظة الكامل لشحن الرصيد أو مراجعة السجل المالي.
        </p>
    </div>
    <a href="{{ route('wallet.index') }}" class="btn btn-pr">فتح مركز المحفظة</a>
</div>

<div style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);border-radius:20px;padding:28px 32px;color:#fff;margin-bottom:24px">
    <div style="font-size:13px;opacity:.82;margin-bottom:6px">الرصيد المتاح</div>
    <div style="font-size:40px;font-weight:800;letter-spacing:-1px">
        {{ $wallet ? number_format((float) $wallet->available_balance, 2) : '0.00' }} {{ $wallet->currency ?? 'SAR' }}
    </div>
    <div style="font-size:13px;opacity:.78;margin-top:8px">
        @if($wallet)
            الرصيد المعلّق: {{ number_format((float) ($wallet->reserved_balance ?? $wallet->locked_balance ?? 0), 2) }} {{ $wallet->currency ?? 'SAR' }}
        @else
            لم يتم إنشاء محفظة لهذا الحساب بعد.
        @endif
    </div>
</div>

<div class="grid-2">
    <x-card title="أحدث العمليات">
        <div style="overflow:auto">
            <table class="table">
                <thead>
                <tr>
                    <th>الوصف</th>
                    <th>المبلغ</th>
                    <th>التاريخ</th>
                </tr>
                </thead>
                <tbody>
                @forelse($transactions as $entry)
                    <tr>
                        <td>{{ $entry->description ?? $entry->type ?? 'عملية مالية' }}</td>
                        <td>{{ number_format((float) $entry->amount, 2) }} {{ $wallet->currency ?? 'SAR' }}</td>
                        <td>{{ optional($entry->created_at)->format('Y-m-d H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="empty-state">لا توجد عمليات بعد. افتح مركز المحفظة الكامل لتنفيذ أول شحن أو مراجعة السجل.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="إجراءات سريعة">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">شحن الرصيد</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">استخدم مركز المحفظة لتعبئة الرصيد قبل إنشاء الشحنات الجديدة.</div>
            </div>
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">مراجعة السجل</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">السجل الكامل يساعدك على فهم الحركات الأخيرة والخصومات ذات الصلة بالشحن.</div>
            </div>
        </div>
    </x-card>
</div>
@endsection
