<?php

namespace App\Http\Controllers\Web;

use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use League\Csv\Writer;

class ShipmentWebController extends WebController
{
    public function index(Request $request)
    {
        $query = Shipment::query()
            ->with(['order.store']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', '%' . $search . '%')
                  ->orWhere('carrier_shipment_id', 'like', '%' . $search . '%')
                  ->orWhere('recipient_name', 'like', '%' . $search . '%')
                  ->orWhere('reference_number', 'like', '%' . $search . '%');
            });
        }

        $shipments = $query->latest()->paginate(20)->withQueryString();
        $totalCount = Shipment::count();

        return view('pages.shipments.index', compact('shipments', 'totalCount'));
    }

    public function show(Shipment $shipment)
    {
        $statusToStep = [
            'draft' => 0, 'validated' => 0, 'rated' => 0, 'payment_pending' => 0,
            'purchased' => 0, 'ready_for_pickup' => 0, 'pending' => 0,
            'picked_up' => 1, 'in_transit' => 2, 'out_for_delivery' => 3,
            'delivered' => 4, 'returned' => 4, 'exception' => 2,
            'cancelled' => 0, 'failed' => 0,
        ];
        $currentIdx = $statusToStep[$shipment->status] ?? 0;

        $timeline = collect([
            ['title' => 'تم إنشاء الشحنة', 'date' => $shipment->created_at->format('Y-m-d H:i'), 'done' => true],
            ['title' => 'تم الاستلام', 'date' => $shipment->picked_up_at ?? '—', 'done' => $currentIdx >= 1],
            ['title' => 'في الطريق', 'date' => '—', 'done' => $currentIdx >= 2],
            ['title' => 'خارج للتسليم', 'date' => '—', 'done' => $currentIdx >= 3],
            ['title' => 'تم التسليم', 'date' => $shipment->actual_delivery_at ?? '—', 'done' => $currentIdx >= 4],
        ]);

        return view('pages.shipments.show', compact('shipment', 'timeline'));
    }

    /**
     * Store — يدعم نموذجين:
     * 1) النموذج الكامل (sender + recipient) من صفحة /shipments/create
     * 2) النموذج المختصر (modal) من صفحة index
     */
    public function store(Request $request)
    {
        // ═══ النموذج الكامل (صفحة create) ═══
        if ($request->has('sender_name')) {
            $v = $request->validate([
                'sender_name'         => 'required|string|max:200',
                'sender_phone'        => 'required|string|max:30',
                'sender_country'      => 'nullable|string|size:2',
                'sender_city'         => 'required|string|max:100',
                'sender_address_1'    => 'nullable|string|max:300',
                'recipient_name'      => 'required|string|max:200',
                'recipient_phone'     => 'required|string|max:30',
                'recipient_country'   => 'nullable|string|size:2',
                'recipient_city'      => 'required|string|max:100',
                'recipient_address_1' => 'nullable|string|max:300',
                'weight'              => 'nullable|numeric|min:0.1',
                'length'              => 'nullable|numeric',
                'width'               => 'nullable|numeric',
                'height'              => 'nullable|numeric',
                'description'         => 'nullable|string|max:500',
                'pieces'              => 'nullable|integer|min:1',
                'carrier_code'        => 'nullable|string',
            ]);

            $weight   = (float) ($v['weight'] ?? 1);
            $cost     = max($weight * 6.5, 18);
            $vat      = round($cost * 0.15, 2);

            $shipment = Shipment::create([
                'account_id'          => auth()->user()->account_id,
                'created_by'          => auth()->id(),
                'reference_number'    => Shipment::generateReference(),
                'source'              => Shipment::SOURCE_DIRECT ?? 'direct',
                'status'              => 'pending',
                'carrier_code'        => $v['carrier_code'] ?? 'manual',
                'carrier_name'        => $v['carrier_code'] ?? 'يدوي',
                'service_code'        => 'standard',
                'service_name'        => 'Standard',
                'tracking_number'     => 'SHP-' . str_pad(Shipment::count() + 1, 8, '0', STR_PAD_LEFT),
                'carrier_shipment_id' => 'CS' . rand(10000000, 99999999),
                'sender_name'         => $v['sender_name'],
                'sender_phone'        => $v['sender_phone'],
                'sender_address_1'    => $v['sender_address_1'] ?? '—',
                'sender_city'         => $v['sender_city'],
                'sender_country'      => $v['sender_country'] ?? 'SA',
                'recipient_name'      => $v['recipient_name'],
                'recipient_phone'     => $v['recipient_phone'],
                'recipient_address_1' => $v['recipient_address_1'] ?? '—',
                'recipient_city'      => $v['recipient_city'],
                'recipient_country'   => $v['recipient_country'] ?? 'SA',
                'total_weight'        => $weight,
                'parcels_count'       => (int) ($v['pieces'] ?? 1),
                'total_charge'        => $cost + $vat,
                'shipping_rate'       => $cost,
                'currency'            => 'SAR',
                'delivery_instructions' => $v['description'] ?? null,
                'metadata'            => array_filter([
                    'length' => $v['length'] ?? null,
                    'width'  => $v['width'] ?? null,
                    'height' => $v['height'] ?? null,
                ]) ?: null,
            ]);

            return redirect()->route('shipments.index')->with('success', 'تم إنشاء الشحنة بنجاح — ' . $shipment->tracking_number);
        }

        // ═══ النموذج المختصر (modal) ═══
        $data = $request->validate([
            'recipient_name'   => 'required|string|max:255',
            'carrier_code'     => 'required|string',
            'origin_city'      => 'required|string',
            'destination_city' => 'required|string',
            'weight'           => 'nullable|numeric',
            'total_cost'       => 'nullable|numeric',
            'service_type'     => 'nullable|string',
            'dimensions'       => 'nullable|string',
        ]);

        Shipment::create([
            'account_id'          => auth()->user()->account_id,
            'created_by'          => auth()->id(),
            'reference_number'    => Shipment::generateReference(),
            'source'              => Shipment::SOURCE_DIRECT ?? 'direct',
            'status'              => 'draft',
            'carrier_code'        => $data['carrier_code'],
            'carrier_name'        => $data['carrier_code'],
            'service_code'        => $data['service_type'] ?? 'standard',
            'service_name'        => $data['service_type'] ?? 'Standard',
            'tracking_number'     => 'SH-' . strtoupper(uniqid()),
            'carrier_shipment_id' => 'JD0060' . rand(1000000, 9999999),
            'sender_name'         => auth()->user()->name ?? '—',
            'sender_phone'        => '—',
            'sender_address_1'    => '—',
            'sender_city'         => $data['origin_city'],
            'sender_country'      => 'SA',
            'recipient_name'      => $data['recipient_name'],
            'recipient_phone'     => '—',
            'recipient_address_1' => '—',
            'recipient_city'      => $data['destination_city'],
            'recipient_country'   => 'SA',
            'total_weight'        => (float) ($data['weight'] ?? 0),
            'total_charge'        => (float) ($data['total_cost'] ?? 0),
            'currency'            => 'SAR',
            'metadata'            => !empty($data['dimensions']) ? ['dimensions' => $data['dimensions']] : null,
        ]);

        return redirect()->route('shipments.index')->with('success', 'تم إنشاء الشحنة بنجاح');
    }

    public function cancel(Shipment $shipment)
    {
        $shipment->update(['status' => 'cancelled']);
        return back()->with('warning', 'تم إلغاء الشحنة ' . $shipment->tracking_number);
    }

    public function createReturn(Shipment $shipment)
    {
        $return = Shipment::create([
            'account_id'          => auth()->user()->account_id,
            'created_by'          => auth()->id(),
            'reference_number'    => Shipment::generateReference(),
            'source'              => 'return',
            'status'              => 'draft',
            'carrier_code'        => $shipment->carrier_code,
            'carrier_name'        => $shipment->carrier_name ?? $shipment->carrier_code,
            'service_code'        => 'standard',
            'service_name'        => 'Standard',
            'tracking_number'     => 'RT-' . strtoupper(uniqid()),
            'carrier_shipment_id' => 'RT0060' . rand(1000000, 9999999),
            'sender_name'         => $shipment->recipient_name,
            'sender_phone'        => $shipment->recipient_phone ?? '—',
            'sender_address_1'    => $shipment->recipient_address_1 ?? '—',
            'sender_city'         => $shipment->recipient_city,
            'sender_country'      => $shipment->recipient_country ?? 'SA',
            'recipient_name'      => $shipment->sender_name ?? $shipment->recipient_name,
            'recipient_phone'     => $shipment->sender_phone ?? '—',
            'recipient_address_1' => $shipment->sender_address_1 ?? '—',
            'recipient_city'      => $shipment->sender_city,
            'recipient_country'   => $shipment->sender_country ?? 'SA',
            'total_weight'        => $shipment->total_weight ?? 0,
            'total_charge'        => 0,
            'currency'            => $shipment->currency ?? 'SAR',
            'metadata'            => ['notes' => 'مرتجع — ' . $shipment->tracking_number],
        ]);

        return redirect()->route('shipments.show', $return)->with('success', 'تم إنشاء المرتجع');
    }

    public function label(Shipment $shipment)
    {
        return view('pages.shipments.show', compact('shipment'))
            ->with('printMode', true);
    }

    public function export(Request $request): Response
    {
        $query = Shipment::query()
            ->where('account_id', auth()->user()->account_id);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->get('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', '%' . $search . '%')
                    ->orWhere('carrier_shipment_id', 'like', '%' . $search . '%')
                    ->orWhere('recipient_name', 'like', '%' . $search . '%');
            });
        }

        $shipments = $query->orderByDesc('created_at')->get();

        $writer = Writer::createFromString('');
        $writer->insertOne([
            'الرقم', 'المرجع', 'التتبع', 'الناقل', 'الحالة', 'المستلم',
            'مدينة المرسل', 'مدينة المستلم', 'الوزن', 'التكلفة', 'العملة', 'التاريخ',
        ]);
        foreach ($shipments as $s) {
            $writer->insertOne([
                $s->tracking_number, $s->reference_number,
                $s->carrier_shipment_id ?? '', $s->carrier_code ?? '',
                $s->status, $s->recipient_name,
                $s->sender_city ?? '', $s->recipient_city ?? '',
                $s->total_weight ?? '', $s->total_charge ?? '',
                $s->currency ?? 'SAR',
                $s->created_at?->format('Y-m-d H:i') ?? '',
            ]);
        }

        $csvUtf8  = $writer->toString();
        $csvExcel = "\xFF\xFE" . mb_convert_encoding($csvUtf8, 'UTF-16LE', 'UTF-8');
        $filename = 'shipments-' . now()->format('Y-m-d-His') . '.csv';

        return response($csvExcel, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-16LE',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
