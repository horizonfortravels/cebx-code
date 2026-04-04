<form method="POST" action="{{ route('containers.store') }}">
    @csrf
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">رقم الحاوية *</label>
            <input type="text" name="container_number" class="form-control" value="{{ old('container_number') }}" required placeholder="MSKU1234567">
        </div>
        <div class="form-group">
            <label class="form-label">النوع</label>
            <select name="type" class="form-control">
                <option value="dry">جاف</option>
                <option value="reefer">مبرد</option>
                <option value="open_top">مفتوح السقف</option>
                <option value="flat_rack">منصة مسطحة</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الحجم</label>
            <select name="size" class="form-control">
                <option value="20ft">20 قدم قياسي</option>
                <option value="40ft">40 قدم قياسي</option>
                <option value="40hc">40 قدم مكعب مرتفع</option>
                <option value="45ft">45 قدم</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الموقع</label>
            <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="ميناء جدة الإسلامي">
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء حاوية</button>
</form>
