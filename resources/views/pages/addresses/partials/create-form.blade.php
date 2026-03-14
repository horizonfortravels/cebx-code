<form method="POST" action="{{ route('addresses.store') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">اسم العنوان *</label>
        <input type="text" name="label" class="form-control" value="{{ old('label') }}" required placeholder="مثال: المكتب الرئيسي">
        @error('label') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
    </div>
    <div class="form-group">
        <label class="form-label">العنوان الكامل *</label>
        <input type="text" name="full_address" class="form-control" value="{{ old('full_address') }}" required placeholder="المدينة، الحي، الشارع">
        @error('full_address') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إضافة العنوان</button>
</form>
