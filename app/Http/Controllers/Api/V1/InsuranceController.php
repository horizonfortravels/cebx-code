<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Claim;
use App\Models\Shipment;
use App\Models\ShipmentCharge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InsuranceController extends Controller
{
    protected array $rates = [
        'basic' => ['rate' => 1.5, 'max_coverage' => 50000, 'deductible' => 500],
        'premium' => ['rate' => 2.5, 'max_coverage' => 200000, 'deductible' => 250],
        'full' => ['rate' => 4.0, 'max_coverage' => 1000000, 'deductible' => 0],
    ];

    public function quote(Request $request): JsonResponse
    {
        $this->authorize('quote', self::class);

        $data = $request->validate([
            'declared_value' => 'required|numeric|min:1',
            'shipment_type' => 'required|in:air,sea,land',
            'destination_country' => 'required|string|size:2',
            'dangerous_goods' => 'boolean',
        ]);

        $value = (float) $data['declared_value'];
        $dgMultiplier = ($data['dangerous_goods'] ?? false) ? 1.5 : 1.0;
        $typeMultiplier = match ($data['shipment_type']) {
            'sea' => 1.3,
            'land' => 1.1,
            default => 1.0,
        };

        $quotes = [];
        foreach ($this->rates as $plan => $config) {
            $premium = round($value * ($config['rate'] / 100) * $dgMultiplier * $typeMultiplier, 2);
            $coverage = min($value, $config['max_coverage']);

            $quotes[] = [
                'plan' => $plan,
                'plan_label' => match ($plan) {
                    'basic' => 'تأمين أساسي',
                    'premium' => 'تأمين متقدم',
                    'full' => 'تأمين شامل',
                },
                'premium' => $premium,
                'coverage' => $coverage,
                'deductible' => $config['deductible'],
                'rate_percent' => round($config['rate'] * $dgMultiplier * $typeMultiplier, 2),
                'currency' => 'SAR',
            ];
        }

        return response()->json(['data' => ['quotes' => $quotes, 'declared_value' => $value]]);
    }

    public function purchase(Request $request, string $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'plan' => 'required|in:basic,premium,full',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('purchase', [self::class, $shipment]);

        if (!(float) ($shipment->declared_value ?? 0) > 0) {
            return response()->json(['message' => 'يجب تحديد القيمة المصرح بها أولاً'], 422);
        }

        $config = $this->rates[$data['plan']];
        $premium = round((float) $shipment->declared_value * ($config['rate'] / 100), 2);
        $coverage = min((float) $shipment->declared_value, $config['max_coverage']);

        ShipmentCharge::create(array_filter([
            'shipment_id' => $shipment->id,
            'charge_type' => 'insurance',
            'description' => "تأمين شحنة - خطة {$data['plan']}",
            'amount' => $premium,
            'currency' => 'SAR',
            'status' => Schema::hasColumn('shipment_charges', 'status') ? 'pending' : null,
        ], static fn ($value) => $value !== null));

        $updates = [];
        foreach ([
            'insurance_flag' => true,
            'insurance_plan' => $data['plan'],
            'insurance_coverage' => $coverage,
            'insurance_premium' => $premium,
            'is_insured' => true,
            'insurance_amount' => $premium,
        ] as $column => $value) {
            if (Schema::hasColumn('shipments', $column)) {
                $updates[$column] = $value;
            }
        }

        if ($updates !== []) {
            $shipment->update($updates);
        }

        return response()->json([
            'data' => [
                'plan' => $data['plan'],
                'premium' => $premium,
                'coverage' => $coverage,
                'deductible' => $config['deductible'],
            ],
            'message' => 'تم تفعيل التأمين بنجاح',
        ]);
    }

    public function fileClaim(Request $request, string $shipmentId): JsonResponse
    {
        $data = $request->validate([
            'claim_type' => 'required|in:damage,loss,delay,theft',
            'description' => 'required|string|max:2000',
            'claimed_amount' => 'required|numeric|min:1',
        ]);

        $shipment = $this->findShipmentForCurrentAccount($shipmentId);
        $this->authorize('fileClaim', [self::class, $shipment]);

        $insured = (bool) ($shipment->insurance_flag ?? $shipment->is_insured ?? false);
        if (!$insured) {
            return response()->json(['message' => 'الشحنة غير مؤمنة'], 422);
        }

        $maxCoverage = (float) ($shipment->insurance_coverage ?? $shipment->declared_value ?? $data['claimed_amount']);

        $claim = Claim::create([
            'account_id' => $this->currentAccountId(),
            'shipment_id' => $shipment->id,
            'claim_number' => 'CLM-' . strtoupper(Str::random(8)),
            'claim_type' => $data['claim_type'],
            'description' => $data['description'],
            'claim_amount' => min((float) $data['claimed_amount'], $maxCoverage),
            'status' => 'submitted',
            'filed_by' => $request->user()->id,
        ]);

        return response()->json([
            'data' => $claim,
            'message' => "تم تقديم المطالبة #{$claim->claim_number}",
        ], 201);
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
}
