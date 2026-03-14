<form method="POST" action="{{ route('customs.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">رقم الإقرار</label>
            <input type="text" name="declaration_number" class="form-control" value="{{ old('declaration_number') }}" placeholder="CD-2026-XXXX">
        </div>
        <div class="form-group">
            <label class="form-label">النوع *</label>
            <select name="declaration_type" class="form-control" required>
                <option value="import">استيراد</option>
                <option value="export">تصدير</option>
                <option value="transit">عبور</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">القيمة المصرّح بها (ر.س) *</label>
            <input type="number" name="declared_value" step="0.01" class="form-control" value="{{ old('declared_value') }}" required placeholder="0.00">
        </div>
        <div class="form-group">
            <label class="form-label">بلد المنشأ</label>
            <input type="text" name="origin_country" class="form-control" value="{{ old('origin_country', 'SA') }}" maxlength="2" placeholder="SA">
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء إقرار</button>
</form>
