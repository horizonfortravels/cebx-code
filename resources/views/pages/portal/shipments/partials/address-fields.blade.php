<div class="shipment-form-grid">
    <label><span class="f-label">الاسم</span><input class="f-input" name="{{ $prefix }}_name" value="{{ old($prefix . '_name', $prefill($prefix . '_name')) }}" required></label>
    <label><span class="f-label">المنشأة</span><input class="f-input" name="{{ $prefix }}_company" value="{{ old($prefix . '_company', $prefill($prefix . '_company')) }}"></label>
    <label><span class="f-label">الهاتف</span><input class="f-input" name="{{ $prefix }}_phone" value="{{ old($prefix . '_phone', $prefill($prefix . '_phone')) }}" required></label>
    <label><span class="f-label">البريد الإلكتروني</span><input class="f-input" type="email" name="{{ $prefix }}_email" value="{{ old($prefix . '_email', $prefill($prefix . '_email')) }}"></label>
    <label class="shipment-form-grid__full"><span class="f-label">العنوان</span><input class="f-input" name="{{ $prefix }}_address_1" value="{{ old($prefix . '_address_1', $prefill($prefix . '_address_1')) }}" required></label>
    <label class="shipment-form-grid__full"><span class="f-label">العنوان الإضافي</span><input class="f-input" name="{{ $prefix }}_address_2" value="{{ old($prefix . '_address_2', $prefill($prefix . '_address_2')) }}"></label>
    <label><span class="f-label">المدينة</span><input class="f-input" name="{{ $prefix }}_city" value="{{ old($prefix . '_city', $prefill($prefix . '_city')) }}" required></label>
    <label><span class="f-label">الولاية / المقاطعة</span><input class="f-input" name="{{ $prefix }}_state" value="{{ old($prefix . '_state', $prefill($prefix . '_state')) }}" placeholder="{{ $statePlaceholder }}"></label>
    <label><span class="f-label">الرمز البريدي</span><input class="f-input" name="{{ $prefix }}_postal_code" value="{{ old($prefix . '_postal_code', $prefill($prefix . '_postal_code')) }}"></label>
    <label><span class="f-label">الدولة (ISO-2)</span><input class="f-input" name="{{ $prefix }}_country" maxlength="2" value="{{ old($prefix . '_country', $prefill($prefix . '_country', $countryDefault)) }}" required></label>
</div>
<div class="shipment-inline-meta">أدخل رمز الولاية أو المقاطعة عندما يكون العنوان داخل الولايات المتحدة.</div>
