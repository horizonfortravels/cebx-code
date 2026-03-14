<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HsCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HsCodeController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $q = HsCode::query();
        if ($r->country) $q->forCountry($r->country);
        if ($r->chapter) $q->where('chapter', $r->chapter);
        if ($r->is_restricted) $q->restricted();
        if ($r->is_dangerous_goods) $q->where('is_dangerous_goods', true);
        if ($r->search) $q->where(fn($q2) => $q2->where('code', 'like', "%{$r->search}%")->orWhere('description', 'like', "%{$r->search}%")->orWhere('description_ar', 'like', "%{$r->search}%"));
        return response()->json(['data' => $q->active()->orderBy('code')->paginate($r->per_page ?? 50)]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => HsCode::findOrFail($id)]);
    }

    public function store(Request $r): JsonResponse
    {
        $v = $r->validate([
            'code' => 'required|string|max:12', 'description' => 'required|string|max:500',
            'description_ar' => 'nullable|string|max:500', 'country' => 'nullable|string|max:3',
            'duty_rate' => 'required|numeric|min:0|max:100', 'vat_rate' => 'nullable|numeric|min:0|max:100',
            'excise_rate' => 'nullable|numeric|min:0', 'is_restricted' => 'nullable|boolean',
            'is_prohibited' => 'nullable|boolean', 'requires_license' => 'nullable|boolean',
            'is_dangerous_goods' => 'nullable|boolean', 'restriction_notes' => 'nullable|string|max:500',
        ]);
        $v['chapter'] = substr($r->code, 0, 2);
        $v['heading'] = substr($r->code, 0, 4);
        $v['subheading'] = substr($r->code, 0, 6);
        return response()->json(['data' => HsCode::create($v), 'message' => 'تم إضافة رمز HS'], 201);
    }

    public function update(Request $r, string $id): JsonResponse
    {
        $hs = HsCode::findOrFail($id);
        $hs->update($r->only(['description','description_ar','duty_rate','vat_rate','excise_rate','is_restricted','is_prohibited','requires_license','is_dangerous_goods','restriction_notes','is_active']));
        return response()->json(['data' => $hs, 'message' => 'تم تحديث رمز HS']);
    }

    public function destroy(string $id): JsonResponse
    {
        HsCode::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف رمز HS']);
    }

    public function lookup(Request $r): JsonResponse
    {
        $r->validate(['code' => 'required|string|max:12', 'country' => 'nullable|string|size:2']);
        return response()->json(['data' => HsCode::where('code', 'like', $r->code . '%')->when($r->country, fn($q) => $q->forCountry($r->country))->active()->orderBy('code')->limit(20)->get()]);
    }

    public function calculateDuty(Request $r): JsonResponse
    {
        $r->validate(['hs_code' => 'required|string|max:12', 'value' => 'required|numeric|min:0', 'country' => 'nullable|string|size:2']);
        $hs = HsCode::where('code', $r->hs_code)->forCountry($r->country ?? 'SA')->active()->first();
        if (!$hs) return response()->json(['message' => 'رمز HS غير موجود'], 404);
        $d = $hs->calculateDuty($r->value);
        return response()->json(['data' => array_merge($d, ['hs_code'=>$hs->code,'description'=>$hs->description,'total'=>$d['duty']+$d['vat']+$d['excise'],'is_restricted'=>$hs->is_restricted,'is_prohibited'=>$hs->is_prohibited])]);
    }

    public function bulkCheck(Request $r): JsonResponse
    {
        $r->validate(['items'=>'required|array|min:1|max:100','items.*.hs_code'=>'required|string|max:12','items.*.value'=>'required|numeric|min:0','country'=>'nullable|string|size:2']);
        $country = $r->country ?? 'SA'; $results = []; $totals = ['duty'=>0,'vat'=>0,'excise'=>0]; $flags = [];
        foreach ($r->items as $item) {
            $hs = HsCode::where('code', $item['hs_code'])->forCountry($country)->active()->first();
            if (!$hs) { $results[] = ['hs_code'=>$item['hs_code'],'error'=>'not_found']; continue; }
            $d = $hs->calculateDuty($item['value']);
            $totals['duty'] += $d['duty']; $totals['vat'] += $d['vat']; $totals['excise'] += $d['excise'];
            if ($hs->is_restricted) $flags[] = 'restricted'; if ($hs->is_prohibited) $flags[] = 'prohibited';
            $results[] = ['hs_code'=>$hs->code,'description'=>$hs->description,'value'=>$item['value'],'duty'=>$d['duty'],'vat'=>$d['vat'],'excise'=>$d['excise'],'total'=>$d['duty']+$d['vat']+$d['excise'],'restricted'=>$hs->is_restricted,'prohibited'=>$hs->is_prohibited];
        }
        return response()->json(['data'=>['items'=>$results,'summary'=>['total_duty'=>round($totals['duty'],2),'total_vat'=>round($totals['vat'],2),'total_excise'=>round($totals['excise'],2),'grand_total'=>round($totals['duty']+$totals['vat']+$totals['excise'],2),'has_restricted'=>in_array('restricted',$flags),'has_prohibited'=>in_array('prohibited',$flags)]]]);
    }

    public function seed(): JsonResponse
    {
        $codes = [
            ['code'=>'6109.10.00','description'=>'T-shirts, cotton','description_ar'=>'تيشيرتات قطنية','duty_rate'=>5,'vat_rate'=>15],
            ['code'=>'8471.30.00','description'=>'Portable computers','description_ar'=>'حواسيب محمولة','duty_rate'=>0,'vat_rate'=>15],
            ['code'=>'8517.12.00','description'=>'Smartphones','description_ar'=>'هواتف ذكية','duty_rate'=>0,'vat_rate'=>15],
            ['code'=>'8528.72.00','description'=>'TV receivers','description_ar'=>'أجهزة تلفزيون','duty_rate'=>5,'vat_rate'=>15],
            ['code'=>'0901.21.00','description'=>'Coffee, roasted','description_ar'=>'قهوة محمصة','duty_rate'=>5,'vat_rate'=>15],
            ['code'=>'3004.90.00','description'=>'Medicaments','description_ar'=>'أدوية','duty_rate'=>0,'vat_rate'=>0],
            ['code'=>'8703.23.00','description'=>'Motor vehicles','description_ar'=>'سيارات','duty_rate'=>5,'vat_rate'=>15],
        ];
        $n = 0;
        foreach ($codes as $c) {
            $c['chapter']=substr($c['code'],0,2); $c['heading']=substr($c['code'],0,4); $c['subheading']=substr($c['code'],0,6); $c['country']='*';
            HsCode::updateOrCreate(['code'=>$c['code'],'country'=>'*'], $c); $n++;
        }
        return response()->json(['message' => "تم إضافة {$n} رمز HS"]);
    }
}
