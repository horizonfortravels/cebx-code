<form method="POST" action="{{ route('pricing.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">اسم قاعدة التسعير *</label>
            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="200" placeholder="مثال: تسعير الناقل DHL">
            @error('name') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label">الحالة</label>
            <select name="status" class="form-control">
                <option value="draft" {{ old('status', 'draft') === 'draft' ? 'selected' : '' }}>مسودة</option>
                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>نشط</option>
            </select>
            @error('status') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group" style="grid-column: 1 / -1">
            <label class="form-label">الوصف (اختياري)</label>
            <textarea name="description" class="form-control" rows="2" maxlength="1000" placeholder="وصف مختصر لقاعدة التسعير">{{ old('description') }}</textarea>
            @error('description') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
        </div>
        <div class="form-group">
            <label class="form-label" style="display:flex;align-items:center;gap:8px">
                <input type="hidden" name="is_default" value="0">
                <input type="checkbox" name="is_default" value="1" {{ old('is_default') ? 'checked' : '' }}>
                اعتبارها القاعدة الافتراضية
            </label>
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء قاعدة التسعير</button>
</form>
