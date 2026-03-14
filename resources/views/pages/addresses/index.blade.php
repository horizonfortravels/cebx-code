@extends('layouts.app')
@section('title', 'دفتر العناوين')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0">📒 دفتر العناوين</h1>
    <button type="button" class="btn btn-pr" data-modal-open="addAddress">+ عنوان جديد</button>
</div>

<div style="display:grid;grid-template-columns:repeat(2,1fr);gap:16px">
    @forelse($addresses as $addr)
        <x-card>
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
                <div>
                    <span style="font-weight:700;font-size:15px">{{ $addr->name }}</span>
                    @if($addr->label)
                        <span class="badge badge-in" style="margin-right:8px">{{ $addr->label }}</span>
                    @endif
                    @if($addr->is_default)
                        <span class="badge badge-ac">افتراضي</span>
                    @endif
                </div>
                <div style="display:flex;gap:6px">
                    @if(!$addr->is_default)
                        <form method="POST" action="{{ route('addresses.default', $addr) }}" style="display:inline">@csrf @method('PATCH')
                            <button type="submit" class="btn btn-s" style="font-size:11px;padding:3px 10px">تعيين افتراضي</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('addresses.destroy', $addr) }}" style="display:inline">@csrf @method('DELETE')
                        <button type="submit" class="btn btn-dg" style="font-size:11px;padding:3px 10px">حذف</button>
                    </form>
                </div>
            </div>
            <x-info-row label="الهاتف" :value="$addr->phone" />
            <x-info-row label="المدينة" :value="$addr->city" />
            @if($addr->district)<x-info-row label="الحي" :value="$addr->district" />@endif
            @if($addr->street)<x-info-row label="الشارع" :value="$addr->street" />@endif
            @if($addr->postal_code)<x-info-row label="الرمز البريدي" :value="$addr->postal_code" />@endif
        </x-card>
    @empty
        <div class="empty-state" style="grid-column:1/-1;padding:60px">لا توجد عناوين محفوظة</div>
    @endforelse
</div>

<x-modal id="addAddress" title="إضافة عنوان جديد">
    <form method="POST" action="{{ route('addresses.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label class="form-label">التسمية</label><input type="text" name="label" class="form-input" placeholder="المنزل, المكتب"></div>
            <div><label class="form-label">الاسم</label><input type="text" name="name" class="form-input" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label class="form-label">الهاتف</label><input type="text" name="phone" class="form-input" required></div>
            <div><label class="form-label">المدينة</label><input type="text" name="city" class="form-input" required></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div><label class="form-label">الحي</label><input type="text" name="district" class="form-input"></div>
            <div><label class="form-label">الرمز البريدي</label><input type="text" name="postal_code" class="form-input"></div>
        </div>
        <div style="margin-bottom:16px"><label class="form-label">الشارع</label><input type="text" name="street" class="form-input"></div>
        <button type="submit" class="btn btn-pr" style="width:100%">حفظ العنوان</button>
    </form>
</x-modal>
@endsection
