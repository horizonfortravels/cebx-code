<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incoterm;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class IncotermController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Incoterm::active()->get()]);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => Incoterm::findOrFail($id)]);
    }

    public function store(Request $r): JsonResponse
    {
        $v = $r->validate([
            'code' => 'required|string|size:3|unique:incoterms,code',
            'name' => 'required|string|max:100',
            'name_ar' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'description_ar' => 'nullable|string',
            'transport_mode' => 'required|in:any,sea_inland',
            'seller_pays_freight' => 'required|boolean',
            'seller_pays_insurance' => 'required|boolean',
            'seller_pays_import_duty' => 'required|boolean',
            'seller_handles_export_clearance' => 'nullable|boolean',
            'buyer_handles_import_clearance' => 'nullable|boolean',
            'risk_transfer_point' => 'nullable|string|max:200',
        ]);
        return response()->json(['data' => Incoterm::create($v), 'message' => 'تم إضافة شرط التجارة'], 201);
    }

    public function update(Request $r, string $id): JsonResponse
    {
        $inc = Incoterm::findOrFail($id);
        $inc->update($r->all());
        return response()->json(['data' => $inc, 'message' => 'تم التحديث']);
    }

    public function destroy(string $id): JsonResponse
    {
        Incoterm::findOrFail($id)->delete();
        return response()->json(['message' => 'تم الحذف']);
    }

    /**
     * Responsibility Matrix — مصفوفة المسؤوليات لكل Incoterm
     */
    public function matrix(): JsonResponse
    {
        $incoterms = Incoterm::active()->get()->map(fn($i) => [
            'code' => $i->code,
            'name' => $i->name,
            'name_ar' => $i->name_ar,
            'transport_mode' => $i->transport_mode,
            'seller_responsibilities' => [
                'export_clearance' => $i->seller_handles_export_clearance,
                'freight' => $i->seller_pays_freight,
                'insurance' => $i->seller_pays_insurance,
                'import_duty' => $i->seller_pays_import_duty,
            ],
            'buyer_responsibilities' => [
                'import_clearance' => $i->buyer_handles_import_clearance,
                'freight' => !$i->seller_pays_freight,
                'insurance' => !$i->seller_pays_insurance,
                'import_duty' => !$i->seller_pays_import_duty,
            ],
            'risk_transfer_point' => $i->risk_transfer_point,
        ]);
        return response()->json(['data' => $incoterms]);
    }

    /**
     * Seed standard Incoterms 2020
     */
    public function seed(): JsonResponse
    {
        $data = [
            ['code'=>'EXW','name'=>'Ex Works','name_ar'=>'تسليم أرض المصنع','transport_mode'=>'any','seller_pays_freight'=>false,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>false,'risk_transfer_point'=>'مباني البائع'],
            ['code'=>'FCA','name'=>'Free Carrier','name_ar'=>'تسليم الناقل','transport_mode'=>'any','seller_pays_freight'=>false,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'تسليم الناقل المحدد'],
            ['code'=>'CPT','name'=>'Carriage Paid To','name_ar'=>'أجرة النقل مدفوعة إلى','transport_mode'=>'any','seller_pays_freight'=>true,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'تسليم الناقل الأول'],
            ['code'=>'CIP','name'=>'Carriage & Insurance Paid To','name_ar'=>'أجرة النقل والتأمين مدفوعة إلى','transport_mode'=>'any','seller_pays_freight'=>true,'seller_pays_insurance'=>true,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'تسليم الناقل الأول'],
            ['code'=>'DAP','name'=>'Delivered At Place','name_ar'=>'التسليم في المكان','transport_mode'=>'any','seller_pays_freight'=>true,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'مكان الوصول المحدد'],
            ['code'=>'DPU','name'=>'Delivered at Place Unloaded','name_ar'=>'التسليم في المكان مفرّغ','transport_mode'=>'any','seller_pays_freight'=>true,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'مكان الوصول بعد التفريغ'],
            ['code'=>'DDP','name'=>'Delivered Duty Paid','name_ar'=>'التسليم مع دفع الرسوم','transport_mode'=>'any','seller_pays_freight'=>true,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>true,'seller_handles_export_clearance'=>true,'buyer_handles_import_clearance'=>false,'risk_transfer_point'=>'مكان الوصول المحدد'],
            ['code'=>'FAS','name'=>'Free Alongside Ship','name_ar'=>'تسليم بجانب السفينة','transport_mode'=>'sea_inland','seller_pays_freight'=>false,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'بجانب السفينة في ميناء الشحن'],
            ['code'=>'FOB','name'=>'Free On Board','name_ar'=>'تسليم على ظهر السفينة','transport_mode'=>'sea_inland','seller_pays_freight'=>false,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'على ظهر السفينة في ميناء الشحن'],
            ['code'=>'CFR','name'=>'Cost and Freight','name_ar'=>'التكلفة والشحن','transport_mode'=>'sea_inland','seller_pays_freight'=>true,'seller_pays_insurance'=>false,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'على ظهر السفينة في ميناء الشحن'],
            ['code'=>'CIF','name'=>'Cost, Insurance & Freight','name_ar'=>'التكلفة والتأمين والشحن','transport_mode'=>'sea_inland','seller_pays_freight'=>true,'seller_pays_insurance'=>true,'seller_pays_import_duty'=>false,'seller_handles_export_clearance'=>true,'risk_transfer_point'=>'على ظهر السفينة في ميناء الشحن'],
        ];

        $created = 0;
        foreach ($data as $i => $row) {
            $row['sort_order'] = $i;
            $row['buyer_handles_import_clearance'] = $row['buyer_handles_import_clearance'] ?? true;
            Incoterm::updateOrCreate(['code' => $row['code']], $row);
            $created++;
        }
        return response()->json(['message' => "تم إضافة {$created} Incoterm", 'data' => Incoterm::active()->get()]);
    }
}
