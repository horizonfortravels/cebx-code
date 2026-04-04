@extends('layouts.app')
@section('title', 'الإعدادات')

@section('content')
<h1 style="font-size:24px;font-weight:800;color:var(--tx);margin:0 0 24px">⚙️ الإعدادات</h1>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
    {{-- معلومات المنظمة --}}
    <x-card title="🏢 معلومات المنظمة">
        <form method="POST" action="{{ route('settings.update') }}">
            @csrf @method('PUT')
            @foreach([
                ['name', 'اسم الشركة', $account->name ?? ''],
                ['cr_number', 'السجل التجاري', $account->cr_number ?? ''],
                ['vat_number', 'الرقم الضريبي', $account->vat_number ?? ''],
                ['email', 'البريد الإلكتروني', $account->email ?? ''],
                ['phone', 'رقم الهاتف', $account->phone ?? ''],
            ] as [$field, $label, $val])
                <div style="margin-bottom:14px">
                    <label class="form-label">{{ $label }}</label>
                    <input type="{{ $field === 'email' ? 'email' : 'text' }}" name="{{ $field }}" class="form-input" value="{{ $val }}">
                </div>
            @endforeach
            <button type="submit" class="btn btn-pr">حفظ التعديلات</button>
        </form>
    </x-card>

    {{-- مفاتيح واجهة البرمجة --}}
    <x-card title="🔑 مفاتيح واجهة البرمجة">
        <div style="background:var(--sf);border-radius:10px;padding:14px;margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:13px;font-weight:600">مفتاح الإنتاج</span>
                <span style="color:var(--ac);font-size:12px">● نشط</span>
            </div>
            <code style="font-size:12px;color:var(--td);background:var(--bd);padding:4px 8px;border-radius:6px">sk_live_****...a8f2</code>
        </div>
        <div style="background:var(--sf);border-radius:10px;padding:14px;margin-bottom:14px">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                <span style="font-size:13px;font-weight:600">مفتاح الاختبار</span>
                <span style="color:var(--wn);font-size:12px">● اختبار</span>
            </div>
            <code style="font-size:12px;color:var(--td);background:var(--bd);padding:4px 8px;border-radius:6px">sk_test_****...b3c1</code>
        </div>
        <button type="button" class="btn btn-pr">+ مفتاح جديد</button>

        <div style="margin-top:24px;padding-top:18px;border-top:1px solid var(--bg)">
            <h4 style="font-weight:700;font-size:14px;margin-bottom:12px">🔒 تغيير كلمة المرور</h4>
            <form method="POST" action="{{ route('settings.password') }}">
                @csrf
                <div style="margin-bottom:12px"><label class="form-label">كلمة المرور الحالية</label><input type="password" name="current_password" class="form-input" required></div>
                <div style="margin-bottom:12px"><label class="form-label">كلمة المرور الجديدة</label><input type="password" name="password" class="form-input" required></div>
                <div style="margin-bottom:12px"><label class="form-label">تأكيد كلمة المرور</label><input type="password" name="password_confirmation" class="form-input" required></div>
                <button type="submit" class="btn btn-s">تحديث كلمة المرور</button>
            </form>
        </div>
    </x-card>
</div>
@endsection
