<form method="POST" action="{{ route('support.store') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">الموضوع *</label>
        <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required placeholder="موضوع التذكرة">
        @error('subject') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
    </div>
    <div class="form-group">
        <label class="form-label">الأولوية</label>
        <select name="priority" class="form-control">
            <option value="low" {{ old('priority', 'medium') === 'low' ? 'selected' : '' }}>منخفض</option>
            <option value="medium" {{ old('priority', 'medium') === 'medium' ? 'selected' : '' }}>متوسط</option>
            <option value="high" {{ old('priority') === 'high' ? 'selected' : '' }}>عالي</option>
        </select>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء تذكرة</button>
</form>
