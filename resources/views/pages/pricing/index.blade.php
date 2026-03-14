@extends('layouts.app')
@section('title', 'التسعير')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">🏷️ التسعير</h1>
    <button class="btn btn-pr" data-modal-open="add-rule">+ قاعدة تسعير</button>
</div>

<div class="stats-grid" style="margin-bottom:24px">
    <x-stat-card icon="🏷️" label="قواعد التسعير" :value="$rulesCount ?? 0" />
    <x-stat-card icon="🚚" label="الناقلين المفعّلين" :value="$activeCarriers ?? 0" />
    <x-stat-card icon="📊" label="متوسط السعر / كجم" :value="'SAR ' . number_format($avgPricePerKg ?? 0, 2)" />
</div>

{{-- Carrier Pricing Table --}}
<x-card title="💲 أسعار الناقلين">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>الناقل</th><th>النوع</th><th>المنطقة</th><th>الوزن الأساسي (كجم)</th><th>السعر الأساسي</th><th>سعر الكجم الإضافي</th><th>الحالة</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($pricingRules ?? [] as $rule)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px">
                                <span class="badge badge-in">{{ $rule->carrier_code }}</span>
                                <span>{{ $rule->carrier_name }}</span>
                            </div>
                        </td>
                        <td>{{ $rule->service_type === 'domestic' ? '🇸🇦 محلي' : '🌍 دولي' }}</td>
                        <td>{{ $rule->zone_name ?? 'الكل' }}</td>
                        <td class="td-mono">{{ $rule->base_weight ?? 1 }} كجم</td>
                        <td style="font-weight:600">SAR {{ number_format($rule->base_price, 2) }}</td>
                        <td class="td-mono">SAR {{ number_format($rule->extra_kg_price, 2) }}</td>
                        <td>
                            <span style="color:{{ $rule->is_active ? 'var(--ac)' : 'var(--dg)' }}">
                                ● {{ $rule->is_active ? 'مفعّل' : 'معطّل' }}
                            </span>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px">
                                <button class="btn btn-s" style="font-size:12px">تعديل</button>
                                <button class="btn btn-s" style="font-size:12px;color:var(--dg)">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="empty-state">لا توجد قواعد تسعير</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

{{-- Surcharges --}}
<x-card title="📌 الرسوم الإضافية">
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>اسم الرسم</th><th>النوع</th><th>القيمة</th><th>ينطبق على</th><th>الحالة</th></tr>
            </thead>
            <tbody>
                @forelse($surcharges ?? [] as $sc)
                    <tr>
                        <td>{{ $sc->name }}</td>
                        <td>{{ $sc->type === 'fixed' ? 'ثابت' : 'نسبة %' }}</td>
                        <td class="td-mono">{{ $sc->type === 'fixed' ? 'SAR ' . number_format($sc->value, 2) : $sc->value . '%' }}</td>
                        <td>{{ $sc->applies_to ?? 'الكل' }}</td>
                        <td><span style="color:{{ $sc->is_active ? 'var(--ac)' : 'var(--dg)' }}">● {{ $sc->is_active ? 'مفعّل' : 'معطّل' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty-state">لا توجد رسوم إضافية</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-modal id="add-rule" title="إضافة قاعدة تسعير" wide>
    <form method="POST" action="{{ route('pricing.index') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div><label class="form-label">الناقل</label><select name="carrier_code" class="form-input"><option>-- اختر الناقل --</option></select></div>
            <div><label class="form-label">نوع الخدمة</label><select name="service_type" class="form-input"><option value="domestic">محلي</option><option value="international">دولي</option></select></div>
            <div><label class="form-label">المنطقة</label><input type="text" name="zone_name" class="form-input" placeholder="مثال: المنطقة الوسطى"></div>
            <div><label class="form-label">الوزن الأساسي (كجم)</label><input type="number" name="base_weight" class="form-input" value="1" step="0.5"></div>
            <div><label class="form-label">السعر الأساسي (SAR)</label><input type="number" name="base_price" class="form-input" step="0.01" placeholder="0.00"></div>
            <div><label class="form-label">سعر الكجم الإضافي (SAR)</label><input type="number" name="extra_kg_price" class="form-input" step="0.01" placeholder="0.00"></div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
            <button type="button" class="btn btn-s" data-modal-close>إلغاء</button>
            <button type="submit" class="btn btn-pr">حفظ</button>
        </div>
    </form>
</x-modal>
@endsection
