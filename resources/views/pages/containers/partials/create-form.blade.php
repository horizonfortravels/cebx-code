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
                <option value="dry">Dry (جاف)</option>
                <option value="reefer">Reefer (مبرّد)</option>
                <option value="open_top">Open Top</option>
                <option value="flat_rack">Flat Rack</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الحجم</label>
            <select name="size" class="form-control">
                <option value="20ft">20ft Standard</option>
                <option value="40ft">40ft Standard</option>
                <option value="40hc">40ft High Cube</option>
                <option value="45ft">45ft</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">الموقع</label>
            <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="ميناء جدة الإسلامي">
        </div>
    </div>
    <button type="submit" class="btn btn-pr" style="margin-top:12px">إنشاء حاوية</button>
</form>
