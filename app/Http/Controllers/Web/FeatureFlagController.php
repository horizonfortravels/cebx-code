<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * FeatureFlagController — A-2: Feature Flags Admin UI
 *
 * Web interface for admins to view and toggle feature flags.
 * Reads defaults from config/features.php, writes runtime overrides
 * to a JSON file in storage/. Falls back gracefully on any I/O error.
 *
 * ZERO MODIFICATION to existing code. Purely additive.
 *
 * Routes to add (inside auth+tenant+permission group):
 *   Route::get('/admin/features', [FeatureFlagController::class, 'index'])->name('admin.features');
 *   Route::post('/admin/features', [FeatureFlagController::class, 'update'])->name('admin.features.update');
 */
class FeatureFlagController extends WebController
{
    // ─── Overrides storage path ──────────────────────────────
    private function overridesPath(): string
    {
        return storage_path('app/feature_flags_overrides.json');
    }

    // ═════════════════════════════════════════════════════════
    // INDEX — Show all flags grouped by category
    // ═════════════════════════════════════════════════════════

    public function index()
    {
        $flags = $this->resolvedFlags();

        $categories = [
            'الناقلات — Carriers'          => $this->filterKeys($flags, 'carrier_'),
            'بوابات الدفع — Payments'       => $this->filterKeys($flags, 'payment_'),
            'الجمارك — Customs'            => $this->filterKeys($flags, 'customs_'),
            'التجارة الإلكترونية — E-com'    => $this->filterKeys($flags, 'ecommerce_'),
            'الإشعارات — Notifications'     => $this->filterKeys($flags, 'notify_'),
            'الوحدات — Modules'            => $this->filterKeys($flags, 'module_'),
            'الذكاء الاصطناعي — AI'          => $this->filterKeys($flags, 'ai_'),
            'المالية — Financial'           => array_intersect_key($flags, array_flip(['auto_invoice', 'credit_limit', 'refund_workflow'])),
            'البيئة — Environment'          => array_intersect_key($flags, array_flip(['sandbox_mode', 'demo_data', 'maintenance'])),
        ];

        $overrides = $this->loadOverrides();

        $html = $this->renderPage($categories, $overrides);

        return view('pages.admin.index', [
            'subtitle' => 'إدارة Feature Flags',
            'content'  => $html,
        ]);
    }

    // ═════════════════════════════════════════════════════════
    // UPDATE — Toggle flags, save overrides
    // ═════════════════════════════════════════════════════════

    public function update(Request $request)
    {
        $allKeys = array_keys(config('features', []));
        $enabled = $request->input('flags', []);
        $overrides = [];

        foreach ($allKeys as $key) {
            $submitted = in_array($key, $enabled);
            $default   = (bool) config("features.{$key}", false);

            // Only store deltas (what differs from config default)
            if ($submitted !== $default) {
                $overrides[$key] = $submitted;
            }
        }

        $this->saveOverrides($overrides);

        Log::info('Feature flags updated', [
            'user_id'   => auth()->id(),
            'overrides' => array_keys($overrides),
            'count'     => count($overrides),
        ]);

        return redirect()->route('admin.features')
            ->with('success', 'تم تحديث Feature Flags بنجاح (' . count($overrides) . ' override)');
    }

    // ═════════════════════════════════════════════════════════
    // HELPERS
    // ═════════════════════════════════════════════════════════

    /**
     * Merge config defaults with runtime overrides.
     * Fallback: if overrides can't be read, use config defaults.
     */
    private function resolvedFlags(): array
    {
        $defaults  = config('features', []);
        $overrides = $this->loadOverrides();

        $result = [];
        foreach ($defaults as $key => $val) {
            $result[$key] = array_key_exists($key, $overrides) ? (bool) $overrides[$key] : (bool) $val;
        }

        return $result;
    }

    private function loadOverrides(): array
    {
        $path = $this->overridesPath();

        if (!file_exists($path)) {
            return [];
        }

        try {
            return json_decode(file_get_contents($path), true) ?: [];
        } catch (\Throwable $e) {
            Log::warning('Feature flag overrides read failed — using defaults', ['error' => $e->getMessage()]);
            return []; // Fallback: no overrides
        }
    }

    private function saveOverrides(array $overrides): void
    {
        try {
            $dir = dirname($this->overridesPath());
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $this->overridesPath(),
                json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            Log::error('Feature flag overrides write failed', ['error' => $e->getMessage()]);
        }
    }

    private function filterKeys(array $flags, string $prefix): array
    {
        return array_filter($flags, fn($k) => str_starts_with($k, $prefix), ARRAY_FILTER_USE_KEY);
    }

    // ═════════════════════════════════════════════════════════
    // HTML RENDER
    // ═════════════════════════════════════════════════════════

    private function renderPage(array $categories, array $overrides): string
    {
        $html = '<form method="POST" action="' . route('admin.features.update') . '">';
        $html .= csrf_field();

        $html .= '<div style="margin-bottom:16px;padding:12px 16px;background:#f0f7ff;border:1px solid #c3dafe;border-radius:10px;font-size:13px">';
        $html .= '💡 التغييرات تُحفظ كـ overrides. القيم الافتراضية تبقى في <code>config/features.php</code>.';
        if (!empty($overrides)) {
            $html .= ' <span style="color:#e67e22">(' . count($overrides) . ' override نشط)</span>';
        }
        $html .= '</div>';

        foreach ($categories as $catName => $flags) {
            if (empty($flags)) continue;

            $html .= '<div style="margin-bottom:24px">';
            $html .= '<h3 style="margin-bottom:12px;font-size:15px;font-weight:700;color:#1a202c">' . e($catName) . '</h3>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">';

            foreach ($flags as $key => $enabled) {
                $label     = $this->flagLabel($key);
                $checked   = $enabled ? 'checked' : '';
                $isOverridden = array_key_exists($key, $overrides);
                $badgeColor   = $enabled ? '#22c55e' : '#94a3b8';
                $badgeText    = $enabled ? 'مفعّل' : 'معطّل';
                $overrideBadge = $isOverridden
                    ? '<span style="font-size:9px;color:#e67e22;margin-right:4px">● override</span>'
                    : '';

                $html .= '<label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:all .15s">';
                $html .= '<input type="checkbox" name="flags[]" value="' . e($key) . '" ' . $checked . ' style="width:18px;height:18px;accent-color:#3b82f6">';
                $html .= '<div style="flex:1;min-width:0">';
                $html .= '<div style="font-weight:600;font-size:13px">' . e($label) . '</div>';
                $html .= '<div style="font-size:10px;color:#94a3b8;font-family:monospace">' . e($key) . ' ' . $overrideBadge . '</div>';
                $html .= '</div>';
                $html .= '<span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:600;color:#fff;background:' . $badgeColor . '">' . $badgeText . '</span>';
                $html .= '</label>';
            }

            $html .= '</div></div>';
        }

        $html .= '<div style="margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;display:flex;gap:10px">';
        $html .= '<button type="submit" style="padding:10px 24px;background:#3b82f6;color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px">💾 حفظ التغييرات</button>';
        $html .= '<a href="' . url('/admin') . '" style="padding:10px 24px;background:#f1f5f9;color:#475569;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500">← العودة للإدارة</a>';
        $html .= '</div>';
        $html .= '</form>';

        return $html;
    }

    private function flagLabel(string $key): string
    {
        return match ($key) {
            'carrier_aramex'     => 'Aramex — أرامكس',
            'carrier_dhl'        => 'DHL Express',
            'carrier_smsa'       => 'SMSA Express',
            'carrier_fedex'      => 'FedEx',
            'carrier_jnt'        => 'J&T Express',
            'carrier_spl'        => 'البريد السعودي SPL',
            'carrier_ups'        => 'UPS',
            'payment_moyasar'    => 'Moyasar — بوابة الدفع',
            'payment_stripe'     => 'Stripe',
            'payment_stcpay'     => 'STC Pay',
            'payment_applepay'   => 'Apple Pay',
            'customs_fasah'      => 'فسح — الجمارك السعودية',
            'customs_zatca'      => 'ZATCA — هيئة الزكاة',
            'ecommerce_salla'    => 'سلة — تكامل المتاجر',
            'ecommerce_zid'      => 'زد — تكامل المتاجر',
            'ecommerce_shopify'  => 'Shopify',
            'notify_email'       => 'إشعارات البريد',
            'notify_sms'         => 'إشعارات SMS',
            'notify_push'        => 'إشعارات Push',
            'module_sea_freight'       => 'الشحن البحري',
            'module_air_freight'       => 'الشحن الجوي',
            'module_land_transport'    => 'النقل البري',
            'module_customs_clearance' => 'التخليص الجمركي',
            'module_phase2_crud'       => 'وحدات Phase 2',
            'ai_anomaly_detection' => 'كشف الشذوذ بالذكاء الاصطناعي',
            'ai_delay_prediction'  => 'توقع التأخير',
            'ai_risk_scoring'      => 'تسجيل المخاطر',
            'auto_invoice'     => 'الفوترة التلقائية',
            'credit_limit'     => 'حد الائتمان',
            'refund_workflow'   => 'سير عمل الاسترداد',
            'sandbox_mode'     => 'وضع Sandbox',
            'demo_data'        => 'بيانات تجريبية',
            'maintenance'      => 'وضع الصيانة',
            default => str_replace('_', ' ', $key),
        };
    }
}
