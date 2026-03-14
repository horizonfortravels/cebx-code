<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Phase2FormsServiceProvider — Injects createForm HTML into Phase 2 views
 *
 * This provider uses View::composer to inject the `createForm` variable
 * into each Phase 2 Blade view. The existing views check for `$createForm`
 * inside their create modal: @if(isset($createForm)) {!! $createForm !!} @endif
 *
 * Without this, the Phase 2 create modals are empty (Dead Buttons).
 *
 * INTEGRATION: Add to config/app.php providers array:
 *   App\Providers\Phase2FormsServiceProvider::class,
 *
 * Or in bootstrap/app.php (Laravel 11):
 *   ->withProviders([
 *       App\Providers\Phase2FormsServiceProvider::class,
 *   ])
 */
class Phase2FormsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ── Containers ──
        View::composer('pages.containers.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $view->with('createForm', $this->containerForm());
            }
        });

        // ── Customs ──
        View::composer('pages.customs.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $view->with('createForm', $this->customsForm());
            }
        });

        // ── Claims ──
        View::composer('pages.claims.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $view->with('createForm', $this->claimForm());
            }
        });

        // ── Drivers ──
        View::composer('pages.drivers.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $view->with('createForm', $this->driverForm());
            }
        });

        // ── Schedules ──
        View::composer('pages.schedules.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $vessels = \App\Models\Vessel::select('id', 'vessel_name')->orderBy('vessel_name')->get();
                $view->with('createForm', $this->scheduleForm($vessels));
            }
        });

        // ── Branches ──
        View::composer('pages.branches.index', function ($view) {
            if (!isset($view->getData()['createForm'])) {
                $view->with('createForm', $this->branchForm());
            }
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // FORM HTML GENERATORS
    // ═══════════════════════════════════════════════════════════════

    private function containerForm(): string
    {
        return '<form method="POST" action="' . route('containers.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">رقم الحاوية *</label>
                    <input name="container_number" class="form-control" required placeholder="MSKU1234567">
                </div>
                <div class="form-group">
                    <label class="form-label">الحجم *</label>
                    <select name="size" class="form-control" required>
                        <option value="20ft">20 قدم</option>
                        <option value="40ft">40 قدم</option>
                        <option value="40ft_hc">40 قدم HC</option>
                        <option value="45ft">45 قدم</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">النوع *</label>
                    <select name="type" class="form-control" required>
                        <option value="dry">جاف (Dry)</option>
                        <option value="reefer">مبرد (Reefer)</option>
                        <option value="open_top">مفتوح (Open Top)</option>
                        <option value="flat_rack">سطحي (Flat Rack)</option>
                        <option value="tank">صهريج (Tank)</option>
                        <option value="special">خاص</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الختم</label>
                    <input name="seal_number" class="form-control" placeholder="SEAL-12345">
                </div>
                <div class="form-group">
                    <label class="form-label">الموقع</label>
                    <input name="location" class="form-control" placeholder="ميناء جدة">
                </div>
                <div class="form-group">
                    <label class="form-label">الحمولة القصوى (طن)</label>
                    <input name="max_payload" type="number" step="0.1" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إنشاء الحاوية</button>
        </form>';
    }

    private function customsForm(): string
    {
        $shipments = \App\Models\Shipment::where('account_id', auth()->user()->account_id)
            ->latest()
            ->take(50)
            ->get(['id', 'tracking_number']);

        $options = $shipments->map(fn($s) => '<option value="' . $s->id . '">' . e($s->tracking_number ?? $s->id) . '</option>')->implode('');

        return '<form method="POST" action="' . route('customs.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الشحنة *</label>
                    <select name="shipment_id" class="form-control" required>' . $options . '</select>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع الإقرار *</label>
                    <select name="type" class="form-control" required>
                        <option value="export">تصدير</option>
                        <option value="import">استيراد</option>
                        <option value="transit">عبور</option>
                        <option value="re_export">إعادة تصدير</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">بلد المنشأ *</label>
                    <input name="origin_country" class="form-control" required placeholder="SA" maxlength="3">
                </div>
                <div class="form-group">
                    <label class="form-label">بلد الوصول *</label>
                    <input name="destination_country" class="form-control" required placeholder="AE" maxlength="3">
                </div>
                <div class="form-group">
                    <label class="form-label">القيمة المصرح بها *</label>
                    <input name="declared_value" type="number" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">مكتب الجمارك</label>
                    <input name="customs_office" class="form-control" placeholder="جمرك ميناء جدة الإسلامي">
                </div>
            </div>
            <div class="form-group" style="margin-top:8px">
                <label class="form-label">الوصف</label>
                <textarea name="description" class="form-control" rows="2" maxlength="1000"></textarea>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إنشاء الإقرار</button>
        </form>';
    }

    private function claimForm(): string
    {
        $shipments = \App\Models\Shipment::where('account_id', auth()->user()->account_id)
            ->latest()
            ->take(50)
            ->get(['id', 'tracking_number']);

        $options = $shipments->map(fn($s) => '<option value="' . $s->id . '">' . e($s->tracking_number ?? $s->id) . '</option>')->implode('');

        return '<form method="POST" action="' . route('claims.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الشحنة *</label>
                    <select name="shipment_id" class="form-control" required>' . $options . '</select>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع المطالبة *</label>
                    <select name="type" class="form-control" required>
                        <option value="damage">تلف</option>
                        <option value="loss">فقدان</option>
                        <option value="delay">تأخير</option>
                        <option value="overcharge">رسوم زائدة</option>
                        <option value="other">أخرى</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">المبلغ المطالب به *</label>
                    <input name="amount" type="number" step="0.01" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الأولوية</label>
                    <select name="priority" class="form-control">
                        <option value="low">منخفضة</option>
                        <option value="medium" selected>متوسطة</option>
                        <option value="high">عالية</option>
                        <option value="critical">حرجة</option>
                    </select>
                </div>
            </div>
            <div class="form-group" style="margin-top:8px">
                <label class="form-label">الوصف *</label>
                <textarea name="description" class="form-control" rows="3" required maxlength="1000" placeholder="وصف تفصيلي للمطالبة..."></textarea>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إنشاء المطالبة</button>
        </form>';
    }

    private function driverForm(): string
    {
        return '<form method="POST" action="' . route('drivers.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">اسم السائق *</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف *</label>
                    <input name="phone" class="form-control" required placeholder="+966">
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input name="email" type="email" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الرخصة *</label>
                    <input name="license_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">انتهاء الرخصة *</label>
                    <input name="license_expiry" type="date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">نوع المركبة *</label>
                    <input name="vehicle_type" class="form-control" required placeholder="شاحنة / فان">
                </div>
                <div class="form-group">
                    <label class="form-label">رقم اللوحة *</label>
                    <input name="plate_number" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">المنطقة</label>
                    <input name="zone" class="form-control" placeholder="الرياض - شمال">
                </div>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إضافة السائق</button>
        </form>';
    }

    private function scheduleForm($vessels): string
    {
        $vesselOptions = $vessels->map(fn($v) => '<option value="' . $v->id . '">' . e($v->vessel_name) . '</option>')->implode('');

        return '<form method="POST" action="' . route('schedules.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">السفينة *</label>
                    <select name="vessel_id" class="form-control" required>' . $vesselOptions . '</select>
                </div>
                <div class="form-group">
                    <label class="form-label">رقم الرحلة</label>
                    <input name="voyage_number" class="form-control" placeholder="يُولَّد تلقائياً إن فارغ">
                </div>
                <div class="form-group">
                    <label class="form-label">ميناء التحميل * (UN/LOCODE)</label>
                    <input name="port_of_loading" class="form-control" required maxlength="5" placeholder="SAJED">
                </div>
                <div class="form-group">
                    <label class="form-label">ميناء التفريغ * (UN/LOCODE)</label>
                    <input name="port_of_discharge" class="form-control" required maxlength="5" placeholder="AEJEA">
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ المغادرة (ETD) *</label>
                    <input name="etd" type="date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">تاريخ الوصول (ETA) *</label>
                    <input name="eta" type="date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">أيام العبور</label>
                    <input name="transit_days" type="number" class="form-control" min="1">
                </div>
                <div class="form-group">
                    <label class="form-label">آخر موعد تحميل</label>
                    <input name="cut_off_date" type="date" class="form-control">
                </div>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إضافة الجدول</button>
        </form>';
    }

    private function branchForm(): string
    {
        return '<form method="POST" action="' . route('branches.store') . '">
            ' . csrf_field() . '
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">اسم الفرع *</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الكود</label>
                    <input name="code" class="form-control" placeholder="يُولَّد تلقائياً">
                </div>
                <div class="form-group">
                    <label class="form-label">المدينة *</label>
                    <input name="city" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الهاتف</label>
                    <input name="phone" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input name="email" type="email" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">اسم المدير</label>
                    <input name="manager_name" class="form-control">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">العنوان</label>
                    <input name="address" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">ساعات العمل</label>
                    <input name="working_hours" class="form-control" placeholder="8:00 - 17:00">
                </div>
            </div>
            <button type="submit" class="btn btn-pr" style="margin-top:10px">إنشاء الفرع</button>
        </form>';
    }
}
