@extends('layouts.app')
@section('title', 'شحنة جديدة')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px">
    <h1 style="font-size:24px;font-weight:700;color:var(--tx);margin:0">📦 {{ $portalType === 'b2b' ? 'إنشاء شحنة جديدة' : 'شحنة جديدة' }}</h1>
    <a href="{{ route('shipments.index') }}" class="btn btn-s">← {{ $portalType === 'b2b' ? 'العودة' : 'رجوع' }}</a>
</div>

{{-- ═══ STEP INDICATOR ═══ --}}
<div class="card" style="padding:16px 24px;margin-bottom:32px">
    <div style="display:flex;gap:0" id="stepIndicator">
        @php
            $steps = $portalType === 'b2b'
                ? ['المرسل والمستلم', 'بيانات الطرد', 'اختيار الناقل', 'المراجعة']
                : ['العناوين', 'تفاصيل الطرد', 'اختيار الناقل', 'المراجعة'];
        @endphp
        @foreach($steps as $i => $stepLabel)
            <div style="flex:1;display:flex;align-items:center;gap:10px" data-step="{{ $i + 1 }}">
                <div class="step-circle" id="stepCircle{{ $i + 1 }}"
                     style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;font-weight:700;
                     background:{{ $i === 0 ? ($portalType === 'b2c' ? '#0D9488' : 'var(--pr)') : 'var(--bd)' }}">
                    {{ $i + 1 }}
                </div>
                <span class="step-label" style="font-size:13px;color:{{ $i === 0 ? 'var(--tx)' : 'var(--td)' }};font-weight:{{ $i === 0 ? '600' : '400' }}">{{ $stepLabel }}</span>
                @if($i < 3)
                    <div class="step-line" style="flex:1;height:2px;background:var(--bd);margin:0 12px"></div>
                @endif
            </div>
        @endforeach
    </div>
</div>

<form action="{{ route('shipments.store') }}" method="POST" id="shipmentForm">
    @csrf

    {{-- ═══ STEP 1: ADDRESSES ═══ --}}
    <div class="form-step" id="step1">
        <div class="grid-2">
            <x-card title="📤 {{ $portalType === 'b2b' ? 'بيانات المرسل' : 'من (المرسل)' }}">
                @if($portalType === 'b2b')
                    <div style="margin-bottom:16px">
                        <label class="form-label">من دفتر العناوين</label>
                        <select name="sender_address_id" class="form-input">
                            <option value="">— اختر عنوان محفوظ —</option>
                            @foreach($savedAddresses ?? [] as $addr)
                                <option value="{{ $addr->id }}">{{ $addr->label }} - {{ $addr->city }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="margin-bottom:16px">
                    <label class="form-label">الاسم</label>
                    <input type="text" name="sender_name" placeholder="{{ $portalType === 'b2c' ? 'اسمك الكامل' : 'اسم المرسل' }}"
                           class="form-input" value="{{ old('sender_name', auth()->user()->name) }}">
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">الهاتف</label>
                    <input type="text" name="sender_phone" placeholder="05xxxxxxxx" class="form-input" value="{{ old('sender_phone') }}">
                </div>
                <div class="grid-2">
                    <div style="margin-bottom:16px">
                        <label class="form-label">الدولة</label>
                        <select name="sender_country" class="form-input">
                            <option value="SA">🇸🇦 السعودية</option>
                            <option value="AE">🇦🇪 الإمارات</option>
                            <option value="KW">🇰🇼 الكويت</option>
                        </select>
                    </div>
                    <div style="margin-bottom:16px">
                        <label class="form-label">المدينة</label>
                        <select name="sender_city" class="form-input">
                            <option>الرياض</option><option>جدة</option><option>الدمام</option><option>مكة</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="sender_address_1" placeholder="الحي، الشارع، رقم المبنى" class="form-input" value="{{ old('sender_address_1') }}">
                </div>
                @if($portalType === 'b2c')
                    <label style="display:flex;align-items:center;gap:8px;color:var(--tm);font-size:13px;cursor:pointer">
                        <input type="checkbox" name="save_sender_address"> حفظ هذا العنوان في دفتر العناوين
                    </label>
                @endif
            </x-card>

            <x-card title="📥 {{ $portalType === 'b2b' ? 'بيانات المستلم' : 'إلى (المستلم)' }}">
                @if($portalType === 'b2b')
                    <div style="margin-bottom:16px">
                        <label class="form-label">من دفتر العناوين</label>
                        <select name="recipient_address_id" class="form-input">
                            <option value="">— اختر عنوان محفوظ —</option>
                        </select>
                    </div>
                @endif
                <div style="margin-bottom:16px">
                    <label class="form-label">الاسم</label>
                    <input type="text" name="recipient_name" placeholder="اسم المستلم" class="form-input" value="{{ old('recipient_name') }}">
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">الهاتف</label>
                    <input type="text" name="recipient_phone" placeholder="05xxxxxxxx" class="form-input" value="{{ old('recipient_phone') }}">
                </div>
                <div class="grid-2">
                    <div style="margin-bottom:16px">
                        <label class="form-label">الدولة</label>
                        <select name="recipient_country" class="form-input">
                            <option value="SA">🇸🇦 السعودية</option>
                            <option value="AE">🇦🇪 الإمارات</option>
                            <option value="KW">🇰🇼 الكويت</option>
                        </select>
                    </div>
                    <div style="margin-bottom:16px">
                        <label class="form-label">المدينة</label>
                        <select name="recipient_city" class="form-input">
                            <option>جدة</option><option>الرياض</option><option>الدمام</option><option>مكة</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">العنوان</label>
                    <input type="text" name="recipient_address_1" placeholder="الحي، الشارع، رقم المبنى" class="form-input" value="{{ old('recipient_address_1') }}">
                </div>
            </x-card>
        </div>

        {{-- ═══ PARCEL DETAILS ═══ --}}
        <x-card title="📦 {{ $portalType === 'b2b' ? 'بيانات الطرد' : 'تفاصيل الطرد' }}">
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px">
                <div style="margin-bottom:16px">
                    <label class="form-label">الوزن (كغ)</label>
                    <input type="number" name="weight" placeholder="0.5" step="0.01" class="form-input" value="{{ old('weight') }}">
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">الطول (سم)</label>
                    <input type="number" name="length" placeholder="20" class="form-input" value="{{ old('length') }}">
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">العرض (سم)</label>
                    <input type="number" name="width" placeholder="15" class="form-input" value="{{ old('width') }}">
                </div>
                <div style="margin-bottom:16px">
                    <label class="form-label">الارتفاع (سم)</label>
                    <input type="number" name="height" placeholder="10" class="form-input" value="{{ old('height') }}">
                </div>
            </div>
            <div style="margin-bottom:16px">
                <label class="form-label">وصف المحتوى</label>
                <input type="text" name="description" placeholder="مثال: ملابس، إلكترونيات..." class="form-input" value="{{ old('description') }}">
            </div>
            @if($portalType === 'b2b')
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:8px">
                    <div style="margin-bottom:16px">
                        <label class="form-label">عدد القطع</label>
                        <input type="number" name="parcels_count" placeholder="1" class="form-input" value="{{ old('parcels_count', 1) }}">
                    </div>
                    <div style="margin-bottom:16px">
                        <label class="form-label">القيمة المصرح بها</label>
                        <input type="number" name="declared_value" placeholder="0.00 ر.س" step="0.01" class="form-input" value="{{ old('declared_value') }}">
                    </div>
                </div>
                <div style="display:flex;gap:24px;margin-top:16px;padding:16px 20px;background:var(--sf);border-radius:8px">
                    <label style="display:flex;align-items:center;gap:8px;color:var(--tm);font-size:13px;cursor:pointer">
                        <input type="checkbox" name="is_cod"> الدفع عند الاستلام (COD)
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;color:var(--tm);font-size:13px;cursor:pointer">
                        <input type="checkbox" name="is_insured"> تأمين الشحنة
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;color:var(--tm);font-size:13px;cursor:pointer">
                        <input type="checkbox" name="has_dangerous_goods"> مواد خطرة (DG)
                    </label>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ═══ FORM ACTIONS ═══ --}}
    <div style="display:flex;justify-content:space-between;margin-top:24px">
        <div>
            @if($portalType === 'b2b')
                <button type="button" class="btn btn-s">حفظ كمسودة</button>
            @else
                <a href="{{ route('shipments.index') }}" class="btn btn-s">← إلغاء</a>
            @endif
        </div>
        <button type="submit" class="btn btn-pr"
                @if($portalType === 'b2c') style="background:#0D9488" @endif>
            ✓ إنشاء الشحنة
        </button>
    </div>
</form>

@if ($errors->any())
    <div style="margin-top:16px;padding:16px;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px">
        <ul style="margin:0;padding:0 20px;color:var(--dg);font-size:13px">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
