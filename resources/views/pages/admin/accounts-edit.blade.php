@extends('layouts.app')
@section('title', 'تحرير حساب العميل')

@section('content')
<div class="header-wrap" style="margin-bottom:24px">
    <div class="header-main">
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route('internal.home') }}" style="color:inherit;text-decoration:none">المساحة الداخلية</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.accounts.index') }}" style="color:inherit;text-decoration:none">حسابات العملاء</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route('internal.accounts.show', $account) }}" style="color:inherit;text-decoration:none">{{ $account->name }}</a>
            <span style="margin:0 6px">/</span>
            <span>تحرير</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">تحرير حساب العميل</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">
            عدّل الحقول الأساسية للحساب الخارجي دون تغيير نوع الحساب أو الدخول في إدارة الأعضاء والدعوات في هذه الخطوة.
        </p>
    </div>
    <div class="header-actions">
        <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s">العودة إلى الملخص</a>
    </div>
</div>

@if($errors->any())
    <x-toast type="error" :message="$errors->first()" />
@endif

<form method="POST" action="{{ route('internal.accounts.update', $account) }}" class="grid-main-sidebar">
    @csrf
    @method('PUT')

    <x-card title="بيانات الحساب">
        <div class="form-grid-2">
            <div style="grid-column:1 / -1">
                <label for="name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">اسم الحساب</label>
                <input id="name" name="name" type="text" class="input" value="{{ old('name', $account->name) }}" required>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">نوع الحساب</label>
                <div class="input" style="display:flex;align-items:center">{{ $account->isOrganization() ? 'منظمة' : 'فردي' }}</div>
            </div>
            <div>
                <label style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الحالة الحالية</label>
                <div class="input" style="display:flex;align-items:center">{{ $account->status }}</div>
            </div>
            <div>
                <label for="language" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">اللغة</label>
                <input id="language" name="language" type="text" class="input" value="{{ old('language', $account->language) }}">
            </div>
            <div>
                <label for="currency" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">العملة</label>
                <input id="currency" name="currency" type="text" class="input" value="{{ old('currency', $account->currency) }}">
            </div>
            <div style="grid-column:1 / -1">
                <label for="timezone" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المنطقة الزمنية</label>
                <input id="timezone" name="timezone" type="text" class="input" value="{{ old('timezone', $account->timezone) }}">
            </div>
            <div>
                <label for="country" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الدولة</label>
                <input id="country" name="country" type="text" class="input" value="{{ old('country', $account->country) }}">
            </div>
            <div>
                <label for="contact_phone" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">هاتف التواصل</label>
                <input id="contact_phone" name="contact_phone" type="text" class="input" value="{{ old('contact_phone', $account->contact_phone) }}">
            </div>
            <div style="grid-column:1 / -1">
                <label for="contact_email" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بريد التواصل</label>
                <input id="contact_email" name="contact_email" type="email" class="input" value="{{ old('contact_email', $account->contact_email) }}">
            </div>
        </div>
    </x-card>

    <x-card title="المالك الأساسي">
        @if($owner)
            <div class="form-grid-2">
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">الاسم</div>
                    <div class="input" style="display:flex;align-items:center">{{ $owner->name }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">البريد الإلكتروني</div>
                    <div class="input" style="display:flex;align-items:center">{{ $owner->email }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">الهاتف</div>
                    <div class="input" style="display:flex;align-items:center">{{ $owner->phone ?: '—' }}</div>
                </div>
                <div>
                    <div style="font-size:12px;color:var(--tm);margin-bottom:6px">حالة المستخدم</div>
                    <div class="input" style="display:flex;align-items:center">{{ $owner->status ?: 'active' }}</div>
                </div>
            </div>
        @else
            <div class="empty-state">لا يوجد مالك خارجي واضح لهذا الحساب حتى الآن.</div>
        @endif
    </x-card>

    <x-card title="العنوان" style="grid-column:1 / -1">
        <div class="field-grid-compact">
            <div style="grid-column:1 / -1">
                <label for="address_line_1" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">العنوان 1</label>
                <input id="address_line_1" name="address_line_1" type="text" class="input" value="{{ old('address_line_1', $account->address_line_1) }}">
            </div>
            <div style="grid-column:1 / -1">
                <label for="address_line_2" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">العنوان 2</label>
                <input id="address_line_2" name="address_line_2" type="text" class="input" value="{{ old('address_line_2', $account->address_line_2) }}">
            </div>
            <div>
                <label for="city" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">المدينة</label>
                <input id="city" name="city" type="text" class="input" value="{{ old('city', $account->city) }}">
            </div>
            <div>
                <label for="postal_code" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الرمز البريدي</label>
                <input id="postal_code" name="postal_code" type="text" class="input" value="{{ old('postal_code', $account->postal_code) }}">
            </div>
        </div>
    </x-card>

    @if($account->isOrganization())
        @php($organization = $account->organizationProfile)
        <x-card title="ملف المنظمة" style="grid-column:1 / -1">
            <div class="field-grid-compact">
                <div>
                    <label for="legal_name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الاسم القانوني</label>
                    <input id="legal_name" name="legal_name" type="text" class="input" value="{{ old('legal_name', $organization?->legal_name ?? $account->name) }}" required>
                </div>
                <div>
                    <label for="trade_name" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الاسم التجاري</label>
                    <input id="trade_name" name="trade_name" type="text" class="input" value="{{ old('trade_name', $organization?->trade_name) }}">
                </div>
                <div>
                    <label for="registration_number" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">رقم السجل</label>
                    <input id="registration_number" name="registration_number" type="text" class="input" value="{{ old('registration_number', $organization?->registration_number) }}">
                </div>
                <div>
                    <label for="tax_id" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الرقم الضريبي</label>
                    <input id="tax_id" name="tax_id" type="text" class="input" value="{{ old('tax_id', $organization?->tax_id) }}">
                </div>
                <div>
                    <label for="industry" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">القطاع</label>
                    <input id="industry" name="industry" type="text" class="input" value="{{ old('industry', $organization?->industry) }}">
                </div>
                <div>
                    <label for="company_size" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">حجم الشركة</label>
                    <select id="company_size" name="company_size" class="input">
                        <option value="">اختر</option>
                        @foreach(['small' => 'صغيرة', 'medium' => 'متوسطة', 'large' => 'كبيرة', 'enterprise' => 'مؤسسية'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('company_size', $organization?->company_size) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="org_country" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">دولة المنظمة</label>
                    <input id="org_country" name="org_country" type="text" class="input" value="{{ old('org_country', $organization?->country) }}">
                </div>
                <div>
                    <label for="org_city" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">مدينة المنظمة</label>
                    <input id="org_city" name="org_city" type="text" class="input" value="{{ old('org_city', $organization?->city) }}">
                </div>
                <div>
                    <label for="website" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الموقع الإلكتروني</label>
                    <input id="website" name="website" type="url" class="input" value="{{ old('website', $organization?->website) }}">
                </div>
                <div style="grid-column:1 / -1">
                    <label for="org_address_line_1" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">عنوان المنظمة 1</label>
                    <input id="org_address_line_1" name="org_address_line_1" type="text" class="input" value="{{ old('org_address_line_1', $organization?->address_line_1) }}">
                </div>
                <div style="grid-column:1 / -1">
                    <label for="org_address_line_2" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">عنوان المنظمة 2</label>
                    <input id="org_address_line_2" name="org_address_line_2" type="text" class="input" value="{{ old('org_address_line_2', $organization?->address_line_2) }}">
                </div>
                <div>
                    <label for="org_postal_code" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">الرمز البريدي</label>
                    <input id="org_postal_code" name="org_postal_code" type="text" class="input" value="{{ old('org_postal_code', $organization?->postal_code) }}">
                </div>
                <div>
                    <label for="org_phone" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">هاتف المنظمة</label>
                    <input id="org_phone" name="org_phone" type="text" class="input" value="{{ old('org_phone', $organization?->phone) }}">
                </div>
                <div>
                    <label for="org_email" style="display:block;font-size:12px;color:var(--tm);margin-bottom:6px">بريد المنظمة</label>
                    <input id="org_email" name="org_email" type="email" class="input" value="{{ old('org_email', $organization?->email) }}">
                </div>
            </div>
        </x-card>
    @endif

    <div style="grid-column:1 / -1;display:flex;justify-content:flex-end;gap:12px">
        <a href="{{ route('internal.accounts.show', $account) }}" class="btn btn-s">إلغاء</a>
        <button type="submit" class="btn btn-pr">حفظ التعديلات</button>
    </div>
</form>
@endsection
