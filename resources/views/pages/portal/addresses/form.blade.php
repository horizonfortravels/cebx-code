@extends('layouts.app')
@section('title', ($portalConfig['label'] ?? __('portal_addresses.common.address_book')) . ' | ' . $copy['title'])

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:24px">
    <div>
        <div style="font-size:12px;color:var(--tm);margin-bottom:8px">
            <a href="{{ route($portalConfig['dashboard_route']) }}" style="color:inherit;text-decoration:none">{{ $portalConfig['label'] }}</a>
            <span style="margin:0 6px">/</span>
            <a href="{{ route($portalConfig['index_route']) }}" style="color:inherit;text-decoration:none">{{ __('portal_addresses.common.address_book') }}</a>
            <span style="margin:0 6px">/</span>
            <span>{{ $copy['title'] }}</span>
        </div>
        <h1 style="font-size:28px;font-weight:800;color:var(--tx);margin:0">{{ $copy['title'] }}</h1>
        <p style="color:var(--td);font-size:14px;margin:8px 0 0;max-width:760px">{{ $copy['description'] }}</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.back_to_index') }}</a>
    </div>
</div>

<div class="grid-2">
    <x-card :title="$copy['title']">
        <form method="POST" action="{{ $formRoute }}" style="display:flex;flex-direction:column;gap:18px">
            @csrf
            @if($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.type') }}</span>
                    <select class="f-input" name="type" required data-testid="address-type-input">
                        @foreach($typeOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $address?->type ?? 'both') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.label') }}</span>
                    <input class="f-input" name="label" value="{{ old('label', $address?->label) }}" required data-testid="address-label-input">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.contact_name') }}</span>
                    <input class="f-input" name="contact_name" value="{{ old('contact_name', $address?->contact_name) }}" required data-testid="address-contact-input">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.company_name') }}</span>
                    <input class="f-input" name="company_name" value="{{ old('company_name', $address?->company_name) }}">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.phone') }}</span>
                    <input class="f-input" name="phone" value="{{ old('phone', $address?->phone) }}" required data-testid="address-phone-input">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.email') }}</span>
                    <input class="f-input" type="email" name="email" value="{{ old('email', $address?->email) }}">
                </label>
                <label style="grid-column:1/-1">
                    <span class="f-label">{{ __('portal_addresses.common.address_line_1') }}</span>
                    <input class="f-input" name="address_line_1" value="{{ old('address_line_1', $address?->address_line_1) }}" required data-testid="address-line1-input">
                </label>
                <label style="grid-column:1/-1">
                    <span class="f-label">{{ __('portal_addresses.common.address_line_2') }}</span>
                    <input class="f-input" name="address_line_2" value="{{ old('address_line_2', $address?->address_line_2) }}">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.city') }}</span>
                    <input class="f-input" name="city" value="{{ old('city', $address?->city) }}" required data-testid="address-city-input">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.state') }}</span>
                    <input class="f-input" name="state" value="{{ old('state', $address?->state) }}">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.postal_code') }}</span>
                    <input class="f-input" name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}">
                </label>
                <label>
                    <span class="f-label">{{ __('portal_addresses.common.country') }}</span>
                    <input class="f-input" name="country" maxlength="2" value="{{ old('country', $address?->country ?? 'SA') }}" required data-testid="address-country-input">
                </label>
            </div>

            @if ($errors->any())
                <div style="padding:14px;border-radius:14px;background:rgba(185,28,28,.06);border:1px solid rgba(185,28,28,.18)">
                    <div style="font-weight:700;color:#991b1b;margin-bottom:6px">تعذّر حفظ العنوان</div>
                    <ul style="margin:0;padding-right:18px;color:#7f1d1d;font-size:13px">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="submit" class="btn btn-pr" data-testid="address-form-submit">{{ $copy['submit'] }}</button>
                <a href="{{ route($portalConfig['index_route']) }}" class="btn btn-s">{{ __('portal_addresses.common.back_to_index') }}</a>
            </div>
        </form>
    </x-card>

    <x-card :title="__('portal_addresses.common.manage_link')">
        <div style="display:flex;flex-direction:column;gap:12px">
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px">
                <div style="font-weight:700;color:var(--tx)">{{ __('portal_addresses.common.use_saved_cta') }}</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">{{ __('portal_addresses.common.picker_help') }}</div>
            </div>
            <div style="padding:14px;border:1px solid var(--bd);border-radius:14px;background:rgba(37,99,235,.06)">
                <div style="font-weight:700;color:var(--tx)">{{ __('portal_addresses.common.use_for_shipment') }}</div>
                <div style="color:var(--td);font-size:13px;margin-top:4px">بعد حفظ العنوان، يمكنك فتح صفحة إنشاء الشحنة واستخدامه لتعبئة بيانات المرسل أو المستلم بسرعة.</div>
            </div>
        </div>
    </x-card>
</div>
@endsection
