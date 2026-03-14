<?php
namespace App\Http\Controllers\Web;

use App\Models\Container;
use App\Models\CustomsDeclaration;
use App\Models\Claim;
use App\Models\Driver;
use App\Models\VesselSchedule;
use App\Models\Vessel;
use App\Models\Branch;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ═══════════════════════════════════════════════════════════
 * Phase2WebController — CRUD Operations for Phase 2 Modules
 * ═══════════════════════════════════════════════════════════
 * Resolves: Dead Buttons (createRoute=true without POST route)
 * Modules: Containers, Customs, Claims, Drivers, Schedules, Branches
 * Pattern: Additive only — existing PageController GET routes untouched
 */
class Phase2WebController extends WebController
{
    // ═══════════════════════════════════════════════════════
    // CONTAINERS — CRUD
    // ═══════════════════════════════════════════════════════

    public function containerStore(Request $r)
    {
        $data = $r->validate([
            'container_number' => 'required|string|max:15',
            'size' => 'required|in:20ft,40ft,40ft_hc,45ft',
            'type' => 'required|in:dry,reefer,open_top,flat_rack,tank,special',
            'seal_number' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:200',
            'max_payload' => 'nullable|numeric|min:0',
            'tare_weight' => 'nullable|numeric|min:0',
        ]);

        Container::create([
            'id' => Str::uuid(),
            'account_id' => auth()->user()->account_id,
            'container_number' => $data['container_number'],
            'size' => $data['size'],
            'type' => $data['type'],
            'seal_number' => $data['seal_number'] ?? null,
            'location' => $data['location'] ?? null,
            'max_payload' => $data['max_payload'] ?? null,
            'tare_weight' => $data['tare_weight'] ?? null,
            'status' => 'empty',
        ]);

        return back()->with('success', 'تم إنشاء الحاوية ' . $data['container_number']);
    }

    public function containerEdit(Container $container)
    {
        if ($container->account_id !== auth()->user()->account_id) abort(403);

        return view('pages.containers.edit', compact('container'));
    }

    public function containerUpdate(Request $r, Container $container)
    {
        if ($container->account_id !== auth()->user()->account_id) abort(403);

        $data = $r->validate([
            'seal_number' => 'nullable|string|max:50',
            'location' => 'nullable|string|max:200',
            'status' => 'nullable|in:empty,loading,loaded,in_transit,at_port,delivered,returned',
        ]);

        $container->update(array_filter($data, fn($v) => $v !== null));
        return redirect()->route('containers.index')->with('success', 'تم تحديث الحاوية');
    }

    public function containerDestroy(Container $container)
    {
        if ($container->account_id !== auth()->user()->account_id) abort(403);
        $container->delete();
        return back()->with('warning', 'تم حذف الحاوية');
    }

    // ═══════════════════════════════════════════════════════
    // CUSTOMS DECLARATIONS — CRUD
    // ═══════════════════════════════════════════════════════

    public function customsStore(Request $r)
    {
        $data = $r->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'declaration_type' => 'required|in:export,import,transit,re_export',
            'origin_country' => 'required|string|size:2',
            'destination_country' => 'required|string|size:2',
            'declared_value' => 'required|numeric|min:0',
            'customs_office' => 'nullable|string|max:200',
            'notes' => 'nullable|string|max:500',
        ]);

        $accountId = auth()->user()->account_id;

        // Verify shipment belongs to account
        $shipment = Shipment::where('id', $data['shipment_id'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $declNumber = 'CD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

        CustomsDeclaration::create([
            'id' => Str::uuid(),
            'account_id' => $accountId,
            'shipment_id' => $shipment->id,
            'declaration_number' => $declNumber,
            'declaration_type' => $data['declaration_type'],
            'origin_country' => $data['origin_country'],
            'destination_country' => $data['destination_country'],
            'declared_value' => $data['declared_value'],
            'declared_currency' => 'SAR',
            'customs_office' => $data['customs_office'] ?? null,
            'customs_status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'تم إنشاء إقرار جمركي ' . $declNumber);
    }

    public function customsUpdateStatus(Request $r, CustomsDeclaration $declaration)
    {
        if ($declaration->account_id !== auth()->user()->account_id) abort(403);

        $data = $r->validate([
            'customs_status' => 'required|in:draft,documents_pending,submitted,under_review,inspection_required,inspecting,duty_assessment,payment_pending,duty_paid,cleared,held,rejected,cancelled',
        ]);

        $declaration->update([
            'customs_status' => $data['customs_status'],
            'submitted_at' => $data['customs_status'] === 'submitted' ? now() : $declaration->submitted_at,
            'cleared_at' => $data['customs_status'] === 'cleared' ? now() : $declaration->cleared_at,
        ]);

        return back()->with('success', 'تم تحديث حالة الإقرار');
    }

    // ═══════════════════════════════════════════════════════
    // CLAIMS — CRUD
    // ═══════════════════════════════════════════════════════

    public function claimStore(Request $r)
    {
        $data = $r->validate([
            'shipment_id' => 'required|exists:shipments,id',
            'type' => 'required|in:damage,loss,delay,overcharge,other',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:1000',
            'priority' => 'nullable|in:low,medium,high,critical',
        ]);

        $accountId = auth()->user()->account_id;

        // Verify shipment belongs to account
        Shipment::where('id', $data['shipment_id'])
            ->where('account_id', $accountId)
            ->firstOrFail();

        $claimNumber = 'CLM-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        Claim::create([
            'id' => Str::uuid(),
            'account_id' => $accountId,
            'shipment_id' => $data['shipment_id'],
            'claim_number' => $claimNumber,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'currency' => 'SAR',
            'description' => $data['description'],
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'open',
            'filed_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم تقديم المطالبة ' . $claimNumber);
    }

    public function claimResolve(Claim $claim)
    {
        if ($claim->account_id !== auth()->user()->account_id) abort(403);

        $claim->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return back()->with('success', 'تم حل المطالبة ' . $claim->claim_number);
    }

    // ═══════════════════════════════════════════════════════
    // DRIVERS — CRUD
    // ═══════════════════════════════════════════════════════

    public function driverStore(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:200',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:255',
            'license_number' => 'required|string|max:50',
            'license_expiry' => 'required|date|after:today',
            'vehicle_type' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:30',
            'zone' => 'nullable|string|max:100',
        ]);

        Driver::create([
            'id' => Str::uuid(),
            'account_id' => auth()->user()->account_id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'license_number' => $data['license_number'],
            'license_expiry' => $data['license_expiry'],
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'vehicle_plate' => $data['vehicle_plate'] ?? null,
            'zones' => $data['zone'] ? json_encode([$data['zone']]) : null,
            'status' => 'available',
        ]);

        return back()->with('success', 'تم إضافة السائق ' . $data['name']);
    }

    public function driverToggle(Driver $driver)
    {
        if ($driver->account_id !== auth()->user()->account_id) abort(403);

        $newStatus = $driver->status === 'suspended' ? 'available' : 'suspended';
        $driver->update(['status' => $newStatus]);

        return back()->with('success', 'تم تحديث حالة السائق');
    }

    // ═══════════════════════════════════════════════════════
    // VESSEL SCHEDULES — CRUD
    // ═══════════════════════════════════════════════════════

    public function scheduleStore(Request $r)
    {
        $data = $r->validate([
            'vessel_id' => 'required|exists:vessels,id',
            'voyage_number' => 'nullable|string|max:30',
            'port_of_loading' => 'required|string|max:5',
            'port_of_loading_name' => 'nullable|string|max:200',
            'port_of_discharge' => 'required|string|max:5',
            'port_of_discharge_name' => 'nullable|string|max:200',
            'etd' => 'required|date',
            'eta' => 'required|date|after:etd',
            'transit_days' => 'nullable|integer|min:0',
        ]);

        VesselSchedule::create([
            'id' => Str::uuid(),
            'account_id' => auth()->user()->account_id,
            'vessel_id' => $data['vessel_id'],
            'voyage_number' => $data['voyage_number'] ?? 'VY-' . strtoupper(Str::random(6)),
            'port_of_loading' => $data['port_of_loading'],
            'port_of_loading_name' => $data['port_of_loading_name'] ?? null,
            'port_of_discharge' => $data['port_of_discharge'],
            'port_of_discharge_name' => $data['port_of_discharge_name'] ?? null,
            'etd' => $data['etd'],
            'eta' => $data['eta'],
            'transit_days' => $data['transit_days'] ?? null,
            'status' => 'scheduled',
        ]);

        return back()->with('success', 'تم إضافة جدول الرحلة');
    }

    // ═══════════════════════════════════════════════════════
    // BRANCHES — CRUD
    // ═══════════════════════════════════════════════════════

    public function branchStore(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:200',
            'code' => 'nullable|string|max:20',
            'city' => 'required|string|max:100',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'manager_name' => 'nullable|string|max:200',
            'working_hours' => 'nullable|string|max:100',
        ]);

        Branch::create([
            'id' => Str::uuid(),
            'account_id' => auth()->user()->account_id,
            'company_id' => null, // Can be linked later
            'name' => $data['name'],
            'code' => $data['code'] ?? 'BR-' . strtoupper(Str::random(4)),
            'city' => $data['city'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'manager_name' => $data['manager_name'] ?? null,
            'working_hours' => $data['working_hours'] ?? null,
            'status' => 'active',
            'country' => 'SA',
        ]);

        return back()->with('success', 'تم إضافة الفرع ' . $data['name']);
    }

    public function branchUpdate(Request $r, Branch $branch)
    {
        if ($branch->account_id !== auth()->user()->account_id) abort(403);

        $data = $r->validate([
            'name' => 'nullable|string|max:200',
            'city' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'manager_name' => 'nullable|string|max:200',
            'status' => 'nullable|in:active,inactive',
        ]);

        $branch->update(array_filter($data, fn($v) => $v !== null));
        return back()->with('success', 'تم تحديث الفرع');
    }
}
