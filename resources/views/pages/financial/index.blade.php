@extends('layouts.app')
@section('title', 'المالية')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">💳 المالية</h1>
    <div style="display:flex;gap:10px">
        <a href="{{ route('reports.export', 'financial') }}" class="btn btn-s">📥 تصدير</a>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="💰" label="إجمالي الإيرادات" :value="'SAR ' . number_format($totalRevenue ?? 0)" />
    <x-stat-card icon="📤" label="المدفوعات" :value="'SAR ' . number_format($totalPayouts ?? 0)" />
    <x-stat-card icon="📊" label="صافي الربح" :value="'SAR ' . number_format($netProfit ?? 0)" :trend="($profitTrend ?? 0) . '%'" :up="($profitTrend ?? 0) > 0" />
    <x-stat-card icon="🧾" label="الفواتير المعلقة" :value="$pendingInvoices ?? 0" />
</div>

{{-- Revenue Chart --}}
<x-card title="📈 الإيرادات الشهرية">
    <div class="bar-chart" style="height:200px">
        @foreach($monthlyRevenue ?? [] as $month)
            @php $barH = ($maxRevenue ?? 1) > 0 ? ($month['amount'] / $maxRevenue * 180) : 0; @endphp
            <div class="bar-col">
                <span class="bar-label" style="font-size:10px">{{ number_format($month['amount']) }}</span>
                <div class="bar" style="height:{{ $barH }}px;background:linear-gradient(180deg,#7C3AED,rgba(124,58,237,0.2))"></div>
                <span class="bar-label">{{ $month['name'] }}</span>
            </div>
        @endforeach
    </div>
</x-card>

{{-- Transactions --}}
<x-card title="🧾 آخر العمليات المالية">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>المعرّف</th><th>النوع</th><th>الوصف</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th></tr>
            </thead>
            <tbody>
                @forelse($transactions ?? [] as $txn)
                    @php
                        $typeLabels = ['credit' => ['إيداع', '🟢'], 'debit' => ['خصم', '🔴'], 'refund' => ['استرداد', '🟡'], 'payout' => ['تحويل', '🔵']];
                        $tl = $typeLabels[$txn->type] ?? ['أخرى', '⚪'];
                    @endphp
                    <tr>
                        <td class="td-mono">{{ $txn->reference_number ?? '#FIN-' . str_pad($txn->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $tl[0] }} {{ $tl[1] }}</td>
                        <td>{{ $txn->description }}</td>
                        <td style="font-weight:600;color:{{ $txn->type === 'credit' ? 'var(--ac)' : 'var(--dg)' }}">
                            {{ $txn->type === 'credit' ? '+' : '-' }} SAR {{ number_format($txn->amount, 2) }}
                        </td>
                        <td><x-badge :status="$txn->status" /></td>
                        <td>{{ $txn->created_at->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-state">لا توجد عمليات مالية</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($transactions ?? collect(), 'links'))
        <div style="margin-top:14px">{{ $transactions->links() }}</div>
    @endif
</x-card>
@endsection
