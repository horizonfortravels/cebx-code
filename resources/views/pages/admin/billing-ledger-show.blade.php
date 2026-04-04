@extends('layouts.app')
@section('title', 'تفاصيل سجل المحفظة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.index') }}" style="color:inherit;text-decoration:none">المحفظة والفوترة</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.billing.show', $account) }}" style="color:inherit;text-decoration:none">{{ $account->name }}</a>
            <span style="margin:0 6px">/</span>
            <span>تفاصيل السجل</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تفاصيل قيد السجل</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:920px">
            عرض للقراءة فقط لحدث السجل ومرجعه التشغيلي وأي سياق مرتبط بالشحنة أو الحجز.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route('internal.billing.show', $account) }}" class="btn btn-s">العودة إلى تفاصيل المحفظة</a>
        @if($canViewAccount)
            <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s">فتح تفاصيل الحساب المرتبط</a>
        @endif
        @if($canViewShipment && $linkedShipment)
            <a href="{{ route('internal.shipments.show', $linkedShipment['id']) }}" class="btn btn-pr">فتح الشحنة المرتبطة</a>
        @endif
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <section class="card" data-testid="internal-billing-ledger-detail-card">
        <div class="card-title">ملخص السجل</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">النوع</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['type'] }}</dd>

            <dt style="color:var(--tm)">الاتجاه</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['direction'] }}</dd>

            <dt style="color:var(--tm)">المبلغ</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['amount'] }}</dd>

            <dt style="color:var(--tm)">الرصيد الجاري</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['running_balance'] }}</dd>

            <dt style="color:var(--tm)">المرجع</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['reference'] }}</dd>

            <dt style="color:var(--tm)">أُنشئ</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['created_at'] }}</dd>

            <dt style="color:var(--tm)">الملاحظة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $ledgerEntry['note'] }}</dd>
        </dl>
    </section>

    <section class="card">
        <div class="card-title">سياق المحفظة</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">مصدر المحفظة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['source_label'] }}</dd>

            <dt style="color:var(--tm)">الرصيد الحالي</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['current_balance'] }}</dd>

            <dt style="color:var(--tm)">الرصيد المحجوز</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['reserved_balance'] }}</dd>

            <dt style="color:var(--tm)">الرصيد المتاح</dt>
            <dd style="margin:0;color:var(--tx)">{{ $walletSummary['available_balance'] }}</dd>
        </dl>
    </section>
</div>

<div class="grid-2">
    <section class="card" data-testid="internal-billing-ledger-linked-shipment-card">
        <div class="card-title">سياق الشحنة المرتبطة</div>
        @if($linkedShipment)
            <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">مرجع الشحنة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['reference'] }}</dd>

                <dt style="color:var(--tm)">حالة سير العمل</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['status'] }}</dd>

                <dt style="color:var(--tm)">إجمالي الشحنة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['total_charge'] }}</dd>

                <dt style="color:var(--tm)">المحجوز على الشحنة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedShipment['reserved_amount'] }}</dd>
            </dl>
        @else
            <div class="empty-state">هذا القيد غير مرتبط حاليًا بأي شحنة.</div>
        @endif
    </section>

    <section class="card" data-testid="internal-billing-ledger-linked-preflight-card">
        <div class="card-title">سياق الحجز المسبق المرتبط</div>
        @if($linkedPreflight)
            <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
                <dt style="color:var(--tm)">حالة الحجز</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['status'] }}</dd>

                <dt style="color:var(--tm)">المبلغ المحجوز</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['amount'] }}</dd>

                <dt style="color:var(--tm)">المصدر</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['source'] }}</dd>

                <dt style="color:var(--tm)">النتيجة</dt>
                <dd style="margin:0;color:var(--tx)">{{ $linkedPreflight['outcome'] }}</dd>
            </dl>
            <div style="margin-top:10px">
                <a href="{{ route('internal.billing.preflights.show', ['account' => $account, 'hold' => $linkedPreflight['id']]) }}" class="btn btn-s">فتح تفاصيل الحجز المسبق</a>
            </div>
        @else
            <div class="empty-state">هذا القيد غير مرتبط حاليًا بأي حجز.</div>
        @endif
    </section>
</div>

@if($linkedTopup)
    <section class="card" data-testid="internal-billing-ledger-linked-topup-card" style="margin-top:24px">
        <div class="card-title">سياق عملية الشحن المرتبطة</div>
        <dl style="display:grid;grid-template-columns:minmax(140px,190px) 1fr;gap:10px 14px;margin:0">
            <dt style="color:var(--tm)">المبلغ</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['amount'] }}</dd>

            <dt style="color:var(--tm)">الحالة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['status'] }}</dd>

            <dt style="color:var(--tm)">البوابة</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['gateway'] }}</dd>

            <dt style="color:var(--tm)">أُنشئ</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['created_at'] }}</dd>

            <dt style="color:var(--tm)">أُكد</dt>
            <dd style="margin:0;color:var(--tx)">{{ $linkedTopup['confirmed_at'] }}</dd>
        </dl>
    </section>
@endif
@endsection
