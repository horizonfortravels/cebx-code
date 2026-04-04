@extends('layouts.app')
@section('title', 'المحفظة')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">💰 المحفظة</h1>
    <button type="button" class="btn btn-pr" data-modal-open="topup">+ شحن الرصيد</button>
</div>

{{-- Balance Card --}}
<div style="background:linear-gradient(135deg,#3B82F6 0%,#1D4ED8 100%);border-radius:20px;padding:32px 36px;color:#fff;margin-bottom:24px">
    <div style="font-size:14px;opacity:.8;margin-bottom:8px">الرصيد المتاح</div>
    <div style="font-size:42px;font-weight:800;letter-spacing:-1px">SAR {{ number_format($wallet->available_balance ?? 0, 2) }}</div>
    @if($wallet->pending_balance > 0)
        <div style="font-size:13px;opacity:.7;margin-top:8px">رصيد معلّق: SAR {{ number_format($wallet->pending_balance, 2) }}</div>
    @endif
</div>

{{-- Transactions --}}
<x-card title="🧾 العمليات الأخيرة">
    <div class="table-wrap">
        <table>
            <thead><tr><th>المعرّف</th><th>الوصف</th><th>المبلغ</th><th>الرصيد بعد</th><th>التاريخ</th></tr></thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td class="td-mono" style="font-size:12px">{{ $tx->reference_number ?? '—' }}</td>
                        <td>{{ $tx->description }}</td>
                        <td style="font-weight:700;color:{{ $tx->amount > 0 ? 'var(--ac)' : 'var(--dg)' }}">
                            {{ $tx->amount > 0 ? '+' : '' }}{{ number_format($tx->amount, 2) }} SAR
                        </td>
                        <td style="font-size:12px;color:var(--td)">{{ number_format($tx->balance_after, 2) }} SAR</td>
                        <td style="font-size:12px;color:var(--tm)">{{ $tx->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">لا توجد عمليات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div style="margin-top:14px">{{ $transactions->links() }}</div>
    @endif
</x-card>

{{-- Topup Modal --}}
<x-modal id="topup" title="شحن الرصيد">
    <form method="POST" action="{{ route('wallet.topup') }}">
        @csrf
        <div style="margin-bottom:16px">
            <label class="form-label">المبلغ (ريال)</label>
            <input type="number" name="amount" class="form-input" min="10" step="0.01" required placeholder="100.00">
            <div style="display:flex;gap:8px;margin-top:10px">
                @foreach([100, 250, 500, 1000, 5000] as $amt)
                    <button type="button" class="btn btn-s" style="font-size:12px" onclick="this.form.amount.value={{ $amt }}">{{ $amt }}</button>
                @endforeach
            </div>
        </div>
        <div style="margin-bottom:16px">
            <label class="form-label">طريقة الدفع</label>
            <select name="payment_method" class="form-input">
                <option value="bank_transfer">تحويل بنكي</option>
                <option value="credit_card">بطاقة ائتمان</option>
                <option value="mada">مدى</option>
                <option value="stc_pay">STC Pay</option>
            </select>
        </div>
        <button type="submit" class="btn btn-pr" style="width:100%">تأكيد الشحن</button>
    </form>
</x-modal>
@endsection
