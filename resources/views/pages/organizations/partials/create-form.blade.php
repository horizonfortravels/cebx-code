<form method="POST" action="{{ route('organizations.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">الاسم القانوني *</label>
            <input type="text" name="legal_name" class="form-control" value="{{ old('legal_name') }}" required maxlength="300">
            @error('legal_name') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">الاسم التجاري</label>
            <input type="text" name="trade_name" class="form-control" value="{{ old('trade_name') }}" maxlength="300">
            @error('trade_name') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">رقم السجل التجاري</label>
            <input type="text" name="registration_number" class="form-control" value="{{ old('registration_number') }}" maxlength="100">
            @error('registration_number') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">الرقم الضريبي</label>
            <input type="text" name="tax_number" class="form-control" value="{{ old('tax_number') }}" maxlength="100">
            @error('tax_number') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">الدولة</label>
            <select name="country_code" class="form-control">
                <option value="SA" {{ old('country_code', 'SA') === 'SA' ? 'selected' : '' }}>السعودية</option>
                <option value="AE" {{ old('country_code') === 'AE' ? 'selected' : '' }}>الإمارات</option>
                <option value="EG" {{ old('country_code') === 'EG' ? 'selected' : '' }}>مصر</option>
                <option value="KW" {{ old('country_code') === 'KW' ? 'selected' : '' }}>الكويت</option>
                <option value="BH" {{ old('country_code') === 'BH' ? 'selected' : '' }}>البحرين</option>
                <option value="OM" {{ old('country_code') === 'OM' ? 'selected' : '' }}>عمان</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الهاتف</label>
            <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="20">
            @error('phone') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">البريد الإلكتروني للفوترة</label>
            <input type="email" name="billing_email" class="form-control" value="{{ old('billing_email') }}" maxlength="200">
            @error('billing_email') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">الموقع الإلكتروني</label>
            <input type="url" name="website" class="form-control" value="{{ old('website') }}" placeholder="https://" maxlength="300">
            @error('website') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group" style="grid-column: 1 / -1">
            <label class="form-label">عنوان الفوترة</label>
            <textarea name="billing_address" class="form-control" rows="2" maxlength="500">{{ old('billing_address') }}</textarea>
            @error('billing_address') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء المنظمة</button>
</form>
