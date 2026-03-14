<form method="POST" action="{{ route('claims.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">نوع المطالبة *</label>
            <select name="type" class="form-control" required>
                <option value="damage">تلف</option>
                <option value="loss">فقدان</option>
                <option value="delay">تأخير</option>
                <option value="overcharge">رسوم زائدة</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">المبلغ المطالب به (ر.س) *</label>
            <input type="number" name="amount" step="0.01" class="form-control" value="{{ old('amount') }}" required placeholder="0.00">
        </div>
        <div class="form-group">
            <label class="form-label">اسم العميل *</label>
            <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name') }}" required>
        </div>
        <div class="form-group">
            <label class="form-label">الوصف</label>
            <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="وصف المطالبة">
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء مطالبة</button>
</form>
