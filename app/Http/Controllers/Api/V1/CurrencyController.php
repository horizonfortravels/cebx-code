<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurrencyController extends Controller
{
    public function rates(Request $request): JsonResponse
    {
        $query = DB::table('exchange_rates')->where('is_active', true);
        if ($request->filled('from')) $query->where('from_currency', strtoupper($request->from));
        if ($request->filled('to')) $query->where('to_currency', strtoupper($request->to));
        return response()->json(['data' => $query->orderByDesc('effective_date')->paginate(50)]);
    }

    public function setRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_currency' => 'required|string|size:3', 'to_currency' => 'required|string|size:3',
            'rate' => 'required|numeric|min:0.00000001', 'effective_date' => 'required|date',
            'source' => 'nullable|in:manual,api,bank',
        ]);
        // Deactivate old rates for same pair
        DB::table('exchange_rates')
            ->where('from_currency', strtoupper($data['from_currency']))
            ->where('to_currency', strtoupper($data['to_currency']))
            ->update(['is_active' => false]);

        $record = [
            'id' => Str::uuid(), 'from_currency' => strtoupper($data['from_currency']),
            'to_currency' => strtoupper($data['to_currency']), 'rate' => $data['rate'],
            'source' => $data['source'] ?? 'manual', 'effective_date' => $data['effective_date'],
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('exchange_rates')->insert($record);
        return response()->json(['data' => $record], 201);
    }

    public function convert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'from_currency' => 'required|string|size:3', 'to_currency' => 'required|string|size:3',
        ]);
        $rate = DB::table('exchange_rates')
            ->where('from_currency', strtoupper($data['from_currency']))
            ->where('to_currency', strtoupper($data['to_currency']))
            ->where('is_active', true)->orderByDesc('effective_date')->value('rate');
        if (!$rate) return response()->json(['message' => 'Exchange rate not found'], 404);

        $converted = round($data['amount'] * $rate, 2);
        return response()->json(['data' => [
            'original' => $data['amount'], 'from' => strtoupper($data['from_currency']),
            'converted' => $converted, 'to' => strtoupper($data['to_currency']),
            'rate' => $rate, 'timestamp' => now()->toIso8601String(),
        ]]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $query = DB::table('currency_transactions')->where('account_id', $request->user()->account_id);
        if ($request->filled('entity_type')) $query->where('entity_type', $request->entity_type);
        return response()->json($query->orderByDesc('converted_at')->paginate($request->per_page ?? 25));
    }

    public function fxReport(Request $request): JsonResponse
    {
        $accountId = $request->user()->account_id;
        $base = DB::table('currency_transactions')->where('account_id', $accountId);
        return response()->json(['data' => [
            'total_fx_gain' => round((clone $base)->where('fx_gain_loss', '>', 0)->sum('fx_gain_loss'), 2),
            'total_fx_loss' => round((clone $base)->where('fx_gain_loss', '<', 0)->sum('fx_gain_loss'), 2),
            'net_fx' => round((clone $base)->sum('fx_gain_loss'), 2),
            'by_currency' => (clone $base)->selectRaw("original_currency, count(*) as txns, sum(original_amount) as volume, sum(fx_gain_loss) as fx")
                ->groupBy('original_currency')->get(),
        ]]);
    }
}
