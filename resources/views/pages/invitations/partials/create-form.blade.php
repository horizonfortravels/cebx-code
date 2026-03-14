<form method="POST" action="{{ route('invitations.store') }}">
    @csrf
    <div class="form-group">
        <label class="form-label">البريد الإلكتروني *</label>
        <input type="email" name="email" class="form-control" value="{{ old('email') }}" required placeholder="user@example.com">
        @error('email') <span class="text-danger" style="font-size:11px">{{ $message }}</span> @enderror
    </div>
    <div class="form-group">
        <label class="form-label">الدور</label>
        <select name="role_id" class="form-control">
            <option value="">— اختياري —</option>
            @foreach($roles ?? [] as $role)
                <option value="{{ $role->id }}" {{ old('role_id') === $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إرسال الدعوة</button>
</form>
