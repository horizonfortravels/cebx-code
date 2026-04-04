@extends('layouts.app')
@section('title', 'إدارة المتاجر')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">🏪 المتاجر</h1>
    <button type="button" class="btn btn-pr" data-modal-open="addStore">+ ربط متجر</button>
</div>

@php
    $platformIcons = ['salla' => '🟣', 'zid' => '🔵', 'shopify' => '🟢', 'woocommerce' => '🟠'];
    $platformNames = ['salla' => 'سلة', 'zid' => 'زد', 'shopify' => 'شوبيفاي', 'woocommerce' => 'ووكومرس'];
@endphp

<div class="grid-auto-320">
    @forelse($stores as $store)
        <x-card>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                <div style="display:flex;gap:14px;align-items:center">
                    <div style="width:48px;height:48px;border-radius:12px;background:var(--sf);display:flex;align-items:center;justify-content:center;font-size:24px">
                        {{ $platformIcons[$store->platform] ?? '🏪' }}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:15px;color:var(--tx)">{{ $store->name }}</div>
                        <div style="font-size:12px;color:var(--tm);margin-top:2px">{{ $platformNames[$store->platform] ?? $store->platform }} • {{ $store->orders_count }} طلب</div>
                    </div>
                </div>
                <x-badge :status="$store->status" />
            </div>
            <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <span style="font-size:12px;color:var(--tm)">
                    آخر مزامنة: {{ $store->last_sync_at ? $store->last_sync_at->diffForHumans() : 'لم تتم' }}
                </span>
                <div style="display:flex;gap:8px">
                    <form method="POST" action="{{ route('stores.sync', $store) }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-s" style="font-size:12px;padding:5px 14px">🔄 مزامنة</button>
                    </form>
                    @if($store->status === 'connected')
                        <form method="POST" action="{{ route('stores.disconnect', $store) }}" style="display:inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-dg" style="font-size:12px;padding:5px 14px">فصل</button>
                        </form>
                    @endif
                </div>
            </div>
        </x-card>
    @empty
        <div class="empty-state" style="grid-column:1/-1;padding:60px">لا توجد متاجر مربوطة — اربط متجرك الآن</div>
    @endforelse
</div>

{{-- نافذة ربط متجر جديد --}}
<x-modal id="addStore" title="ربط متجر جديد">
    <form method="POST" action="{{ route('stores.store') }}">
        @csrf
        <div style="margin-bottom:16px">
            <label class="form-label">اسم المتجر</label>
            <input type="text" name="name" class="form-input" required placeholder="متجري">
        </div>
        <div style="margin-bottom:16px">
            <label class="form-label">المنصة</label>
            <select name="platform" class="form-input" required>
                <option value="salla">سلة</option><option value="zid">زد</option>
                <option value="shopify">شوبيفاي</option><option value="woocommerce">ووكومرس</option>
            </select>
        </div>
        <div style="margin-bottom:16px">
            <label class="form-label">رابط المتجر</label>
            <input type="url" name="store_url" class="form-input" placeholder="https://متجري.example">
        </div>
        <div style="margin-bottom:16px">
            <label class="form-label">مفتاح واجهة البرمجة</label>
            <input type="text" name="api_key" class="form-input" placeholder="أدخل مفتاح واجهة البرمجة">
        </div>
        <button type="submit" class="btn btn-pr" style="width:100%">ربط المتجر</button>
    </form>
</x-modal>
@endsection
