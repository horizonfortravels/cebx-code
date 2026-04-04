<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RiskScore;
use App\Models\RouteSuggestion;
use App\Models\Shipment;
use App\Models\TariffRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RiskController extends Controller
{
    public function score(Request $request, string $shipmentId): JsonResponse
    {
        $this->authorize('create', RiskScore::class);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $risk = RiskScore::calculateForShipment($shipment);

        return response()->json([
            'data' => $risk,
            'message' => 'تم تقييم المخاطر',
        ]);
    }

    public function shipmentRisk(Request $request, string $shipmentId): JsonResponse
    {
        $this->authorize('viewAny', RiskScore::class);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $risk = RiskScore::query()
            ->where('shipment_id', $shipment->id)
            ->latest()
            ->first();

        if (!$risk) {
            $risk = RiskScore::calculateForShipment($shipment);
        }

        $this->authorize('view', $risk);

        return response()->json(['data' => $risk]);
    }

    public function show(string $shipmentId): JsonResponse
    {
        $this->authorize('viewAny', RiskScore::class);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $risk = RiskScore::query()
            ->where('shipment_id', $shipment->id)
            ->latest()
            ->first();

        if (!$risk) {
            return response()->json(['message' => 'لم يتم تقييم المخاطر بعد'], 404);
        }

        $this->authorize('view', $risk);

        return response()->json(['data' => $risk]);
    }

    public function batchScore(Request $request): JsonResponse
    {
        $this->authorize('batchScore', RiskScore::class);

        $request->validate([
            'shipment_ids' => 'required|array|min:1|max:50',
            'shipment_ids.*' => 'uuid|exists:shipments,id',
        ]);

        $shipments = Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->whereIn('id', $request->shipment_ids)
            ->get();

        if ($shipments->count() !== count($request->shipment_ids)) {
            abort(404);
        }

        $results = $shipments->map(static fn (Shipment $shipment) => RiskScore::calculateForShipment($shipment));

        return response()->json([
            'data' => $results->values(),
            'message' => 'تم تقييم ' . $results->count() . ' شحنة',
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('dashboard', RiskScore::class);

        $shipmentIds = Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->pluck('id');

        $query = RiskScore::query()->whereIn('shipment_id', $shipmentIds);

        return response()->json(['data' => [
            'total_scored' => (clone $query)->count(),
            'by_level' => (clone $query)->selectRaw('risk_level, count(*) as count')->groupBy('risk_level')->pluck('count', 'risk_level'),
            'critical_shipments' => (clone $query)->where('risk_level', 'critical')->with('shipment:id,tracking_number,status,carrier_name')->orderByDesc('overall_score')->limit(10)->get(),
            'high_risk_shipments' => (clone $query)->where('risk_level', 'high')->with('shipment:id,tracking_number,status')->orderByDesc('overall_score')->limit(10)->get(),
            'avg_scores' => [
                'overall' => round((clone $query)->avg('overall_score') ?? 0, 1),
                'delay' => round((clone $query)->avg('delay_probability') ?? 0, 1),
                'damage' => round((clone $query)->avg('damage_probability') ?? 0, 1),
                'customs' => round((clone $query)->avg('customs_risk') ?? 0, 1),
                'fraud' => round((clone $query)->avg('fraud_risk') ?? 0, 1),
            ],
        ]]);
    }

    public function stats(Request $request): JsonResponse
    {
        return $this->dashboard($request);
    }

    public function suggestRoutes(Request $request): JsonResponse
    {
        $this->authorize('suggestRoutes', RiskScore::class);

        $request->validate([
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'origin_city' => 'nullable|string|max:100',
            'destination_city' => 'nullable|string|max:100',
            'weight' => 'required|numeric|min:0.001',
            'volume' => 'nullable|numeric',
            'declared_value' => 'nullable|numeric|min:0',
            'priority' => 'nullable|in:cost,speed,reliability',
            'shipment_id' => 'nullable|uuid',
        ]);

        $shipment = null;
        if ($request->filled('shipment_id')) {
            $shipment = $this->findShipmentForCurrentAccount((string) $request->shipment_id);
        }

        $priority = $request->priority ?? 'cost';

        $tariffs = TariffRule::query()
            ->where('account_id', $this->currentAccountId())
            ->active()
            ->forRoute($request->origin_country, $request->destination_country)
            ->where('min_weight', '<=', $request->weight)
            ->where('max_weight', '>=', $request->weight)
            ->orderBy('priority', 'desc')
            ->get();

        $suggestions = $tariffs->map(function (TariffRule $tariff, int $index) use ($priority, $request) {
            $calculation = $tariff->calculate((float) $request->weight, $request->volume, (float) ($request->declared_value ?? 0));

            $transitDays = match ($tariff->shipment_type) {
                'air' => rand(2, 5),
                'express' => rand(1, 3),
                'sea' => rand(15, 35),
                'land' => rand(5, 14),
                default => rand(3, 10),
            };

            $reliability = match ($tariff->shipment_type) {
                'air' => rand(85, 97),
                'express' => rand(90, 99),
                'sea' => rand(70, 90),
                'land' => rand(75, 92),
                default => rand(80, 95),
            };

            $distanceFactor = match ($tariff->shipment_type) {
                'air' => 0.6,
                'sea' => 0.015,
                'land' => 0.06,
                'express' => 0.7,
                default => 0.1,
            };

            $carbon = round(((float) $request->weight / 1000) * 5000 * $distanceFactor, 2);

            $score = match ($priority) {
                'cost' => 100 - ($calculation['total'] / max($calculation['total'], 1) * 50),
                'speed' => 100 - ($transitDays * 3),
                'reliability' => $reliability,
            };

            return [
                'rank' => $index + 1,
                'carrier_code' => $tariff->carrier_code ?? 'multi',
                'service_code' => $tariff->service_level ?? 'standard',
                'transport_mode' => $tariff->shipment_type,
                'tariff_name' => $tariff->name,
                'route_legs' => [[
                    'from' => $request->origin_country,
                    'to' => $request->destination_country,
                    'mode' => $tariff->shipment_type,
                    'carrier' => $tariff->carrier_code,
                    'days' => $transitDays,
                ]],
                'estimated_days' => $transitDays,
                'estimated_cost' => $calculation['total'],
                'cost_breakdown' => $calculation,
                'currency' => $calculation['currency'],
                'reliability_score' => $reliability,
                'carbon_footprint_kg' => $carbon,
                'priority_score' => round($score, 1),
                'is_recommended' => $index === 0,
            ];
        })->sortByDesc('priority_score')->values();

        if ($shipment) {
            RouteSuggestion::query()->where('shipment_id', $shipment->id)->delete();

            foreach ($suggestions as $index => $suggestion) {
                RouteSuggestion::create([
                    'shipment_id' => $shipment->id,
                    'rank' => $index + 1,
                    'carrier_code' => $suggestion['carrier_code'],
                    'service_code' => $suggestion['service_code'],
                    'transport_mode' => $suggestion['transport_mode'],
                    'route_legs' => $suggestion['route_legs'],
                    'estimated_days' => $suggestion['estimated_days'],
                    'estimated_cost' => $suggestion['estimated_cost'],
                    'currency' => $suggestion['currency'],
                    'reliability_score' => $suggestion['reliability_score'],
                    'carbon_footprint_kg' => $suggestion['carbon_footprint_kg'],
                    'is_recommended' => $suggestion['is_recommended'],
                ]);
            }
        }

        return response()->json(['data' => $suggestions]);
    }

    public function selectRoute(Request $request, string $shipmentId, string $suggestionId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);

        $route = RouteSuggestion::query()
            ->where('shipment_id', $shipment->id)
            ->where('id', $suggestionId)
            ->firstOrFail();
        $this->authorize('selectRoute', $route);

        RouteSuggestion::query()
            ->where('shipment_id', $shipment->id)
            ->update(['is_selected' => false]);

        $route->update(['is_selected' => true]);

        $shipment->update([
            'carrier_code' => $route->carrier_code,
            'service_code' => $route->service_code,
            'shipment_type' => $route->transport_mode,
            'estimated_delivery_at' => now()->addDays($route->estimated_days),
        ]);

        return response()->json([
            'data' => $route,
            'message' => 'تم اختيار المسار',
        ]);
    }

    public function predictDelay(Request $request, string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('viewAny', RiskScore::class);

        $factors = [];
        $delayProbability = 0;

        if ($shipment->shipment_type === 'sea') {
            $delayProbability += 25;
            $factors[] = ['factor' => 'sea_freight', 'impact' => 25, 'description_ar' => 'الشحن البحري عرضة لتأخيرات الطقس'];
        }

        if ($shipment->is_international) {
            $delayProbability += 15;
            $factors[] = ['factor' => 'international', 'impact' => 15, 'description_ar' => 'شحنة دولية - إجراءات جمركية'];
        }

        if ($shipment->has_dangerous_goods) {
            $delayProbability += 20;
            $factors[] = ['factor' => 'dangerous_goods', 'impact' => 20, 'description_ar' => 'بضائع خطرة تحتاج تصاريح إضافية'];
        }

        if ($shipment->chargeable_weight > 1000) {
            $delayProbability += 10;
            $factors[] = ['factor' => 'heavy_shipment', 'impact' => 10, 'description_ar' => 'شحنة ثقيلة - احتمال تأخير التحميل'];
        }

        if (now()->isWeekend()) {
            $delayProbability += 5;
            $factors[] = ['factor' => 'weekend', 'impact' => 5, 'description_ar' => 'عطلة نهاية الأسبوع'];
        }

        $delayProbability = min(100, $delayProbability);
        $predictedDays = max(0, round($delayProbability / 20));
        $eta = $shipment->estimated_delivery_at;
        $newEta = $eta ? $eta->copy()->addDays($predictedDays) : now()->addDays($predictedDays + 3);

        return response()->json(['data' => [
            'shipment_id' => $shipmentId,
            'delay_probability' => $delayProbability,
            'predicted_delay_days' => $predictedDays,
            'original_eta' => $eta?->toDateString(),
            'predicted_eta' => $newEta->toDateString(),
            'risk_level' => match (true) {
                $delayProbability >= 60 => 'high',
                $delayProbability >= 30 => 'medium',
                default => 'low',
            },
            'factors' => $factors,
            'recommendations' => $this->delayRecommendations($factors),
        ]]);
    }

    public function fraudCheck(Request $request, string $shipmentId): JsonResponse
    {
        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('viewAny', RiskScore::class);

        $flags = [];
        $score = 0;

        if ($shipment->declared_value > 50000 && $shipment->chargeable_weight < 5) {
            $score += 30;
            $flags[] = ['flag' => 'value_weight_mismatch', 'severity' => 'high', 'ar' => 'تناقض بين القيمة المعلنة والوزن'];
        }

        if ($shipment->declared_value > 200000) {
            $score += 20;
            $flags[] = ['flag' => 'high_value', 'severity' => 'medium', 'ar' => 'قيمة مرتفعة جدًا'];
        }

        if ($shipment->is_cod && $shipment->is_international) {
            $score += 25;
            $flags[] = ['flag' => 'international_cod', 'severity' => 'high', 'ar' => 'الدفع عند الاستلام لشحنة دولية - مخاطر عالية'];
        }

        $customerShipments = Shipment::query()
            ->where('account_id', $shipment->account_id)
            ->count();

        if ($customerShipments <= 3 && $shipment->declared_value > 10000) {
            $score += 15;
            $flags[] = ['flag' => 'new_customer_high_value', 'severity' => 'medium', 'ar' => 'عميل جديد بشحنة ذات قيمة عالية'];
        }

        $level = match (true) {
            $score >= 50 => 'critical',
            $score >= 30 => 'high',
            $score >= 15 => 'medium',
            default => 'low',
        };

        return response()->json(['data' => [
            'shipment_id' => $shipmentId,
            'fraud_score' => min(100, $score),
            'risk_level' => $level,
            'flags' => $flags,
            'action_required' => in_array($level, ['critical', 'high'], true),
            'recommendations' => match ($level) {
                'critical' => ['حجب الشحنة للمراجعة اليدوية', 'التحقق من هوية العميل', 'التحقق من عنوان المستلم'],
                'high' => ['مراجعة يدوية قبل الشحن', 'التحقق من بيانات الدفع'],
                'medium' => ['مراقبة الشحنة', 'تسجيل ملاحظة في الملف'],
                default => ['لا حاجة لإجراء إضافي'],
            },
        ]]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('analytics', RiskScore::class);

        $shipments = Shipment::query()->where('account_id', $this->currentAccountId());
        $totalShipments = (clone $shipments)->count();
        $delivered = (clone $shipments)->where('status', 'delivered')->count();
        $delayed = (clone $shipments)->where('status', 'exception')->count();
        $onTime = (clone $shipments)
            ->where('status', 'delivered')
            ->whereNotNull('estimated_delivery_at')
            ->whereNotNull('actual_delivery_at')
            ->whereColumn('actual_delivery_at', '<=', 'estimated_delivery_at')
            ->count();

        return response()->json(['data' => [
            'performance' => [
                'total_shipments' => $totalShipments,
                'delivered' => $delivered,
                'on_time_delivery_rate' => $delivered > 0 ? round(($onTime / $delivered) * 100, 1) : 0,
                'exception_rate' => $totalShipments > 0 ? round(($delayed / $totalShipments) * 100, 1) : 0,
            ],
            'by_type' => (clone $shipments)->selectRaw('shipment_type, count(*) as count, avg(total_charge) as avg_cost')->groupBy('shipment_type')->get(),
            'by_carrier' => (clone $shipments)->selectRaw('carrier_code, count(*) as count')->whereNotNull('carrier_code')->groupBy('carrier_code')->orderByDesc('count')->limit(10)->get(),
            'monthly_trend' => (clone $shipments)->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as count")->groupBy('month')->orderBy('month')->limit(12)->get(),
        ]]);
    }

    private function findShipmentForCurrentAccount(string $id): Shipment
    {
        return Shipment::query()
            ->where('account_id', $this->currentAccountId())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function currentAccountId(): string
    {
        return trim((string) app('current_account_id'));
    }

    /**
     * @param array<int, array<string, mixed>> $factors
     * @return array<int, string>
     */
    private function delayRecommendations(array $factors): array
    {
        $recommendations = [];

        foreach ($factors as $factor) {
            $recommendations[] = match ($factor['factor']) {
                'sea_freight' => 'فكّر في الشحن الجوي للشحنات العاجلة',
                'international' => 'جهّز المستندات الجمركية مسبقًا',
                'dangerous_goods' => 'تأكد من استكمال جميع التصاريح',
                'heavy_shipment' => 'اطلب حجزًا مسبقًا مع الناقل',
                'weekend' => 'اختر التسليم في أيام العمل',
                default => 'راقب الشحنة بانتظام',
            };
        }

        return array_values(array_unique($recommendations));
    }
}
