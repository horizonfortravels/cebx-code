@extends('layouts.app')
@section('title', 'إنشاء تذكرة داخلية')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">الرئيسية الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.tickets.index') }}" style="color:inherit;text-decoration:none">التذاكر</a>
            <span style="margin:0 6px">/</span>
            <span>إنشاء تذكرة</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">إنشاء تذكرة داخلية</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:860px">
            أنشئ تذكرة دعم داخلية مع سياق حساب واضح وربط اختياري بالشحنة. تُنشأ التذاكر المرتبطة بالشحنة من صفحة تفاصيل الشحنة حتى يبقى سياق الحساب والشحنة متسقًا وقابلًا للتدقيق.
        </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        @if($selectedShipment)
            <a href="{{ route('internal.shipments.show', $selectedShipment['shipment']) }}" class="btn btn-s">العودة إلى الشحنة</a>
        @elseif($selectedAccount)
            <a href="{{ route('internal.accounts.show', $selectedAccount['account']) }}" class="btn btn-s">العودة إلى الحساب</a>
        @else
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">العودة إلى التذاكر</a>
        @endif
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<form method="POST" action="{{ route('internal.tickets.store') }}" class="grid-2" data-testid="internal-ticket-create-form">
    @csrf

    <x-card title="السياق المرتبط">
        @if($selectedAccount)
            <input type="hidden" name="account_id" value="{{ $selectedAccount['id'] }}">
            <div data-testid="internal-ticket-linked-account-card" style="display:flex;flex-direction:column;gap:8px">
                <div style="font-weight:700;color:var(--tx)">{{ $selectedAccount['name'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $selectedAccount['type_label'] }} - {{ $selectedAccount['slug'] }}</div>
                @if($selectedAccount['organization_label'])
                    <div style="font-size:12px;color:var(--tm)">المنظمة: {{ $selectedAccount['organization_label'] }}</div>
                @endif
            </div>
        @else
            <div>
                <label for="ticket-account-id" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحساب المرتبط</label>
                <select id="ticket-account-id" name="account_id" class="input" data-testid="internal-ticket-account-select" required>
                    <option value="">اختر حسابًا</option>
                    @foreach($accountOptions as $option)
                        <option value="{{ $option['id'] }}" @selected(old('account_id') === $option['id'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                <div style="font-size:12px;color:var(--td);margin-top:8px">
                    تُنشأ التذاكر العامة دائمًا مع حساب مرتبط. ويُضاف ربط الشحنة من صفحة التفاصيل للحفاظ على السياق الدقيق.
                </div>
            </div>
        @endif

        @if($selectedShipment)
            <input type="hidden" name="shipment_id" value="{{ $selectedShipment['id'] }}">
            <div data-testid="internal-ticket-linked-shipment-card" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--bd);display:flex;flex-direction:column;gap:8px">
                <div style="font-weight:700;color:var(--tx)">{{ $selectedShipment['reference'] }}</div>
                <div style="font-size:13px;color:var(--td)">{{ $selectedShipment['status_label'] }}</div>
                <div style="font-size:12px;color:var(--tm)">{{ $selectedShipment['tracking_summary'] }}</div>
            </div>
        @endif
    </x-card>

    <x-card title="ملخص التذكرة">
        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
            <div style="grid-column:1 / -1">
                <label for="ticket-subject" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الموضوع</label>
                <input id="ticket-subject" name="subject" type="text" class="input" value="{{ old('subject') }}" maxlength="300" required>
            </div>
            <div>
                <label for="ticket-category" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الفئة</label>
                <select id="ticket-category" name="category" class="input" required>
                    @foreach(['shipping' => 'الشحن', 'billing' => 'الفوترة', 'technical' => 'تقنية', 'account' => 'الحساب', 'carrier' => 'شركة الشحن', 'general' => 'عام'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('category', $defaults['category']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="ticket-priority" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الأولوية</label>
                <select id="ticket-priority" name="priority" class="input" required>
                    @foreach(['low' => 'منخفضة', 'medium' => 'متوسطة', 'high' => 'مرتفعة', 'urgent' => 'عاجلة'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $defaults['priority']) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div style="grid-column:1 / -1">
                <label for="ticket-description" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">ملخص الطلب</label>
                <textarea id="ticket-description" name="description" class="input" rows="7" maxlength="5000" required>{{ old('description') }}</textarea>
                <div style="font-size:12px;color:var(--td);margin-top:8px">
                    يبقى هذا الملخص داخليًا وقابلًا للتدقيق وآمنًا. وتظل مسارات الملاحظات الداخلية وتغييرات إسناد التذاكر خارج نطاق هذه المرحلة عمدًا.
                </div>
            </div>
        </div>
    </x-card>

    <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px;flex-wrap:wrap">
        @if($selectedShipment)
            <a href="{{ route('internal.shipments.show', $selectedShipment['shipment']) }}" class="btn btn-s">إلغاء</a>
        @elseif($selectedAccount)
            <a href="{{ route('internal.accounts.show', $selectedAccount['account']) }}" class="btn btn-s">إلغاء</a>
        @else
            <a href="{{ route('internal.tickets.index') }}" class="btn btn-s">إلغاء</a>
        @endif
        <button type="submit" class="btn btn-pr" data-testid="internal-ticket-create-submit">إنشاء التذكرة</button>
    </div>
</form>
@endsection
