<form method="POST" action="{{ route('roles.store') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">اسم الدور *</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required maxlength="100" placeholder="مثال: مدير الفرع">
        @error('name') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء الدور</button>
</form>
