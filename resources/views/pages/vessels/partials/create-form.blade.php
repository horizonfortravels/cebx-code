<form method="POST" action="{{ route('vessels.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">اسم السفينة *</label>
            <input type="text" name="vessel_name" class="form-control" value="{{ old('vessel_name') }}" required placeholder="اسم السفينة">
        </div>
        <div class="form-group">
            <label class="form-label">الرقم الدولي للسفينة</label>
            <input type="text" name="imo_number" class="form-control" value="{{ old('imo_number') }}" placeholder="9811000">
        </div>
        <div class="form-group">
            <label class="form-label">العلم</label>
            <input type="text" name="flag" class="form-control" value="{{ old('flag', 'SA') }}" maxlength="3" placeholder="SA">
        </div>
        <div class="form-group">
            <label class="form-label">نوع السفينة</label>
            <select name="vessel_type" class="form-control">
                <option value="container">حاويات</option>
                <option value="bulk">صب</option>
                <option value="tanker">ناقلة</option>
                <option value="roro">سفينة دحرجة</option>
            </select>
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء سفينة</button>
</form>
