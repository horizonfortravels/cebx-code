<?php

namespace App\Http\Controllers\Web;

use App\Models\{Container, CustomsDeclaration, Claim, Driver, VesselSchedule, Branch, Shipment, Vessel};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase2CrudController — Completes all "Dead Button" Phase 2 modules
 *
 * Each module follows Execution Methodology:
 *   1. Validation Logic (strict $request->validate)
 *   2. Backend Logic (Controller → Model with DB::transaction)
 *   3. Database Safety (DB::transaction for multi-step ops)
 *   4. UI Completion (Button → Route → Controller → View → Response)
 *   5. Security (account_id tenant isolation + ownership checks)
 *   6. Error Handling (try/catch + user-facing Arabic messages + log)
 *
 * NEW FILE — zero modification to existing controllers.
 */
class Phase2CrudController extends WebController
{
    // ═══════════════════════════════════════════════════════════════
    // CONTAINERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /containers — Create container
     */
    public function containerStore(Request $request)
    {
        $data = $request->validate([
            'container_number' => 'required|string|max:20',
            'size'             => 'required|in:20ft,40ft,40ft_hc,45ft',
            'type'             => 'required|in:dry,reefer,open_top,flat_rack,tank,special',
            'seal_number'      => 'nullable|string|max:30',
            'location'         => 'nullable|string|max:200',
            'port'             => 'nullable|string|max:100',
            'max_payload'      => 'nullable|numeric|min:0',
            'tare_weight'      => 'nullable|numeric|min:0',
            'vessel_schedule_id' => 'nullable|exists:vessel_schedules,id',
        ]);

        try {
            DB::transaction(function () use ($data) {
                Container::create(array_merge($data, [
                    'account_id' => auth()->user()->account_id,
                    'status'     => 'empty',
                ]));
            });
            return redirect()->route('containers.index')->with('success', 'تم إنشاء الحاوية بنجاح');
        } catch (\Exception $e) {
            Log::error('Container create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إنشاء الحاوية')->withInput();
        }
    }

    /**
     * PUT /containers/{container} — Update container
     */
    public function containerUpdate(Request $request, Container $container)
    {
        $this->authorizeOwnership($container);

        $data = $request->validate([
            'seal_number' => 'nullable|string|max:30',
            'location'    => 'nullable|string|max:200',
            'port'        => 'nullable|string|max:100',
            'status'      => 'nullable|in:empty,loading,loaded,in_transit,at_port,delivered',
        ]);

        $container->update(array_filter($data, fn($v) => $v !== null));
        return redirect()->route('containers.index')->with('success', 'تم تحديث الحاوية');
    }

    /**
     * DELETE /containers/{container} — Soft delete container
     */
    public function containerDestroy(Container $container)
    {
        $this->authorizeOwnership($container);

        if (method_exists($container, 'trashed')) {
            $container->delete();
        } else {
            $container->update(['status' => 'deleted']);
        }
        return redirect()->route('containers.index')->with('success', 'تم حذف الحاوية');
    }

    // ═══════════════════════════════════════════════════════════════
    // CUSTOMS DECLARATIONS
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /customs — Create customs declaration
     */
    public function customsStore(Request $request)
    {
        $data = $request->validate([
            'shipment_id'        => 'required|exists:shipments,id',
            'type'               => 'required|in:export,import,transit,re_export',
            'origin_country'     => 'required|string|max:3',
            'destination_country'=> 'required|string|max:3',
            'declared_value'     => 'required|numeric|min:0',
            'currency'           => 'nullable|string|max:3',
            'customs_office'     => 'nullable|string|max:200',
            'description'        => 'nullable|string|max:1000',
        ]);

        // Verify shipment belongs to same account
        $shipment = Shipment::where('id', $data['shipment_id'])
            ->where('account_id', auth()->user()->account_id)
            ->first();

        if (!$shipment) {
            return back()->with('error', 'الشحنة غير موجودة أو ليست تابعة لحسابك')->withInput();
        }

        try {
            DB::transaction(function () use ($data) {
                CustomsDeclaration::create(array_merge($data, [
                    'account_id'         => auth()->user()->account_id,
                    'declaration_number' => 'CD-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                    'status'             => 'draft',
                    'currency'           => $data['currency'] ?? 'SAR',
                ]));
            });
            return redirect()->route('customs.index')->with('success', 'تم إنشاء الإقرار الجمركي');
        } catch (\Exception $e) {
            Log::error('Customs create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إنشاء الإقرار')->withInput();
        }
    }

    /**
     * PATCH /customs/{declaration}/status — Update customs status
     */
    public function customsUpdateStatus(Request $request, CustomsDeclaration $declaration)
    {
        $this->authorizeOwnership($declaration);

        $data = $request->validate([
            'status' => 'required|in:draft,documents_pending,submitted,under_review,inspection_required,inspecting,duty_assessment,payment_pending,duty_paid,cleared,held,rejected,cancelled',
        ]);

        $updateData = ['status' => $data['status']];

        // Auto-fill timestamps
        if ($data['status'] === 'submitted' && !$declaration->submitted_at) {
            $updateData['submitted_at'] = now();
        }
        if ($data['status'] === 'cleared' && !$declaration->cleared_at) {
            $updateData['cleared_at'] = now();
        }

        $declaration->update($updateData);
        return redirect()->route('customs.index')->with('success', 'تم تحديث حالة الإقرار');
    }

    // ═══════════════════════════════════════════════════════════════
    // CLAIMS
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /claims — Create claim
     */
    public function claimStore(Request $request)
    {
        $data = $request->validate([
            'shipment_id'  => 'required|exists:shipments,id',
            'type'         => 'required|in:damage,loss,delay,overcharge,other',
            'amount'       => 'required|numeric|min:0',
            'description'  => 'required|string|max:1000',
            'priority'     => 'nullable|in:low,medium,high,critical',
        ]);

        // Verify shipment ownership
        $shipment = Shipment::where('id', $data['shipment_id'])
            ->where('account_id', auth()->user()->account_id)
            ->first();

        if (!$shipment) {
            return back()->with('error', 'الشحنة غير موجودة أو ليست تابعة لحسابك')->withInput();
        }

        try {
            DB::transaction(function () use ($data) {
                Claim::create(array_merge($data, [
                    'account_id'   => auth()->user()->account_id,
                    'claim_number' => 'CLM-' . date('Ymd') . '-' . strtoupper(Str::random(5)),
                    'status'       => 'open',
                    'priority'     => $data['priority'] ?? 'medium',
                    'filed_by'     => auth()->id(),
                ]));
            });
            return redirect()->route('claims.index')->with('success', 'تم إنشاء المطالبة بنجاح');
        } catch (\Exception $e) {
            Log::error('Claim create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إنشاء المطالبة')->withInput();
        }
    }

    /**
     * PATCH /claims/{claim}/resolve — Resolve a claim
     */
    public function claimResolve(Request $request, Claim $claim)
    {
        $this->authorizeOwnership($claim);

        $data = $request->validate([
            'resolution_notes' => 'nullable|string|max:1000',
            'resolved_amount'  => 'nullable|numeric|min:0',
        ]);

        $claim->update(array_merge($data, [
            'status'      => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]));

        return redirect()->route('claims.index')->with('success', 'تم حل المطالبة');
    }

    // ═══════════════════════════════════════════════════════════════
    // DRIVERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /drivers — Create driver
     */
    public function driverStore(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'phone'           => 'required|string|max:20',
            'email'           => 'nullable|email|max:200',
            'license_number'  => 'required|string|max:50',
            'license_expiry'  => 'required|date|after:today',
            'vehicle_type'    => 'required|string|max:50',
            'plate_number'    => 'required|string|max:20',
            'zone'            => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($data) {
                Driver::create(array_merge($data, [
                    'account_id' => auth()->user()->account_id,
                    'status'     => 'available',
                ]));
            });
            return redirect()->route('drivers.index')->with('success', 'تم إضافة السائق بنجاح');
        } catch (\Exception $e) {
            Log::error('Driver create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إضافة السائق')->withInput();
        }
    }

    /**
     * PATCH /drivers/{driver}/toggle — Toggle driver status
     */
    public function driverToggle(Driver $driver)
    {
        $this->authorizeOwnership($driver);

        $newStatus = ($driver->status ?? 'available') === 'available' ? 'suspended' : 'available';
        $driver->update(['status' => $newStatus]);

        return redirect()->route('drivers.index')->with('success', 'تم تحديث حالة السائق');
    }

    // ═══════════════════════════════════════════════════════════════
    // VESSEL SCHEDULES
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /schedules — Create vessel schedule
     */
    public function scheduleStore(Request $request)
    {
        $data = $request->validate([
            'vessel_id'          => 'required|exists:vessels,id',
            'voyage_number'      => 'nullable|string|max:50',
            'port_of_loading'    => 'required|string|max:5',
            'port_of_discharge'  => 'required|string|max:5',
            'etd'                => 'required|date',
            'eta'                => 'required|date|after:etd',
            'transit_days'       => 'nullable|integer|min:1',
            'cut_off_date'       => 'nullable|date|before:etd',
            'service_route'      => 'nullable|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($data) {
                VesselSchedule::create(array_merge($data, [
                    'account_id'    => auth()->user()->account_id,
                    'voyage_number' => $data['voyage_number'] ?? ('VY-' . strtoupper(Str::random(6))),
                    'status'        => 'scheduled',
                ]));
            });
            return redirect()->route('schedules.index')->with('success', 'تم إضافة جدول الرحلة');
        } catch (\Exception $e) {
            Log::error('Schedule create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إنشاء الجدول')->withInput();
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // BRANCHES
    // ═══════════════════════════════════════════════════════════════

    /**
     * POST /branches — Create branch
     */
    public function branchStore(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'code'          => 'nullable|string|max:20',
            'city'          => 'required|string|max:100',
            'address'       => 'nullable|string|max:500',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:200',
            'manager_name'  => 'nullable|string|max:100',
            'working_hours' => 'nullable|string|max:200',
            'company_id'    => 'nullable|exists:companies,id',
        ]);

        try {
            DB::transaction(function () use ($data) {
                Branch::create(array_merge($data, [
                    'account_id' => auth()->user()->account_id,
                    'code'       => $data['code'] ?? ('BR-' . strtoupper(Str::random(4))),
                    'status'     => 'active',
                ]));
            });
            return redirect()->route('branches.index')->with('success', 'تم إنشاء الفرع بنجاح');
        } catch (\Exception $e) {
            Log::error('Branch create failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'حدث خطأ أثناء إنشاء الفرع')->withInput();
        }
    }

    /**
     * PUT /branches/{branch} — Update branch
     */
    public function branchUpdate(Request $request, Branch $branch)
    {
        $this->authorizeOwnership($branch);

        $data = $request->validate([
            'name'          => 'nullable|string|max:200',
            'city'          => 'nullable|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'manager_name'  => 'nullable|string|max:100',
            'status'        => 'nullable|in:active,inactive',
        ]);

        $branch->update(array_filter($data, fn($v) => $v !== null));
        return redirect()->route('branches.index')->with('success', 'تم تحديث الفرع');
    }

    // ═══════════════════════════════════════════════════════════════
    // SHARED: Tenant Ownership Authorization
    // ═══════════════════════════════════════════════════════════════

    /**
     * Verify the model belongs to the authenticated user's account
     */
    private function authorizeOwnership($model): void
    {
        if ($model->account_id !== auth()->user()->account_id) {
            abort(403, 'ليس لديك صلاحية للوصول لهذا العنصر');
        }
    }
}
