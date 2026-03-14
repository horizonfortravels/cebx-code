<div class="card">
    <div class="card-title">إعدادات الحساب</div>
    <form method="POST" action="{{ route('settings.update') }}">
        @csrf @method('PUT')
        <div class="form-grid">
            <div class="form-group"><label class="form-label">اسم الشركة</label><input name="company_name" class="form-control" value="{{ auth()->user()->account?->name ?? '' }}"></div>
            <div class="form-group"><label class="form-label">البريد</label><input name="email" type="email" class="form-control" value="{{ auth()->user()->email }}"></div>
            <div class="form-group"><label class="form-label">الدولة</label>
                <select name="country" class="form-control"><option value="sa">السعودية</option><option value="ae">الإمارات</option><option value="kw">الكويت</option></select>
            </div>
            <div class="form-group"><label class="form-label">العملة</label>
                <select name="currency" class="form-control"><option value="sar">SAR ريال</option><option value="usd">USD دولار</option></select>
            </div>
        </div>
        <button type="submit" class="btn btn-pr" style="margin-top:12px">💾 حفظ الإعدادات</button>
    </form>
</div>
