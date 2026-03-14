@extends('layouts.app')
@section('title', 'إدارة الطلبات')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">🛒 الطلبات</h1>
    <button type="button" class="btn btn-pr" data-modal-open="syncOrders">🔄 مزامنة الطلبات</button>
</div>

<x-card>
    <form method="GET" style="display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap">
        @foreach(['' => 'الكل', 'new' => 'جديد', 'processing' => 'قيد المعالجة', 'shipped' => 'تم الشحن', 'delivered' => 'مسلّم'] as $val => $label)
            <button type="submit" name="status" value="{{ $val }}" class="btn {{ request('status','') === $val ? 'btn-pr' : 'btn-s' }}" style="font-size:13px">{{ $label }}</button>
        @endforeach
        <div style="flex:1"></div>
        <input type="text" name="search" value="{{ request('search') }}" placeholder="بحث..." class="form-input" style="width:200px">
    </form>

    <div class="table-wrap">
        <table>
            <thead><tr>
                <th>رقم الطلب</th><th>المتجر</th><th>المنتجات</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th></th>
            </tr></thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td class="td-mono" style="font-weight:600">{{ $order->order_number }}</td>
                        <td>{{ $order->store->name ?? '—' }}</td>
                        <td>{{ $order->items_count }} منتج</td>
                        <td style="font-weight:600">SAR {{ number_format($order->total_amount, 2) }}</td>
                        <td><x-badge :status="$order->status" /></td>
                        <td style="font-size:12px;color:var(--tm)">{{ $order->created_at->format('Y-m-d') }}</td>
                        <td>
                            @if($order->status === 'new' || $order->status === 'processing')
                                <form method="POST" action="{{ route('orders.ship', $order) }}" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-pr" style="font-size:12px;padding:5px 14px">شحن</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-state">لا توجد طلبات</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($orders->hasPages())
        <div style="margin-top:14px">{{ $orders->links() }}</div>
    @endif
</x-card>
@endsection
