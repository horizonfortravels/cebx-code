<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait BuildsHighRiskDomainFixtures
{
    protected function createTenantAccount(array $overrides = []): Account
    {
        return Account::factory()->create(array_merge([
            'name' => 'Account ' . Str::random(8),
            'type' => 'organization',
            'status' => 'active',
        ], $overrides));
    }

    /**
     * @param array<int, string> $permissionKeys
     * @param array<string, mixed> $attributes
     */
    protected function createExternalUser(string $accountId, array $permissionKeys = [], array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'account_id' => $accountId,
            'user_type' => 'external',
            'status' => 'active',
            'locale' => 'en',
            'timezone' => 'UTC',
        ], $attributes));

        if ($permissionKeys !== []) {
            $this->grantTenantPermissions($user, $permissionKeys, 'test_' . Str::lower(Str::random(8)));
        }

        return $user;
    }

    protected function createCompanyRecord(string $accountId): object
    {
        $payload = [
            'name' => 'Company ' . Str::random(8),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('companies', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('companies', 'country')) {
            $payload['country'] = 'SA';
        }
        if (Schema::hasColumn('companies', 'code')) {
            $payload['code'] = 'CMP-' . strtoupper(Str::random(6));
        }
        if (Schema::hasColumn('companies', 'type')) {
            $payload['type'] = 'carrier';
        }
        if (Schema::hasColumn('companies', 'base_currency')) {
            $payload['base_currency'] = 'SAR';
        }
        if (Schema::hasColumn('companies', 'timezone')) {
            $payload['timezone'] = 'UTC';
        }
        if (Schema::hasColumn('companies', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('companies', 'is_active')) {
            $payload['is_active'] = true;
        }

        $companyId = $this->insertRowAndReturnId('companies', $payload);

        return DB::table('companies')->where('id', $companyId)->firstOrFail();
    }

    protected function createBranchRecord(string $accountId, string $companyId): object
    {
        $payload = [
            'name' => 'Branch ' . Str::random(8),
            'code' => 'BR-' . strtoupper(Str::random(6)),
            'city' => 'Riyadh',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('branches', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('branches', 'company_id')) {
            $payload['company_id'] = $companyId;
        }
        if (Schema::hasColumn('branches', 'country')) {
            $payload['country'] = 'SA';
        }
        if (Schema::hasColumn('branches', 'branch_type')) {
            $payload['branch_type'] = 'office';
        }
        if (Schema::hasColumn('branches', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('branches', 'is_active')) {
            $payload['is_active'] = true;
        }

        $branchId = $this->insertRowAndReturnId('branches', $payload);

        return DB::table('branches')->where('id', $branchId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createShipmentRecord(string $accountId, string $userId, array $overrides = []): object
    {
        $payload = [];

        if (Schema::hasColumn('shipments', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('shipments', 'user_id')) {
            $payload['user_id'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'created_by')) {
            $payload['created_by'] = $userId;
        }
        if (Schema::hasColumn('shipments', 'reference_number')) {
            $payload['reference_number'] = 'SHP-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('shipments', 'tracking_number')) {
            $payload['tracking_number'] = 'TRK-' . strtoupper(Str::random(10));
        }
        if (Schema::hasColumn('shipments', 'carrier_name')) {
            $payload['carrier_name'] = 'FedEx';
        }
        if (Schema::hasColumn('shipments', 'type')) {
            $payload['type'] = 'domestic';
        }
        if (Schema::hasColumn('shipments', 'shipment_type')) {
            $payload['shipment_type'] = 'air';
        }
        if (Schema::hasColumn('shipments', 'source')) {
            $payload['source'] = 'direct';
        }
        if (Schema::hasColumn('shipments', 'status')) {
            $payload['status'] = 'draft';
        }
        if (Schema::hasColumn('shipments', 'status_updated_at')) {
            $payload['status_updated_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'chargeable_weight')) {
            $payload['chargeable_weight'] = 10;
        }
        if (Schema::hasColumn('shipments', 'declared_value')) {
            $payload['declared_value'] = 500;
        }
        if (Schema::hasColumn('shipments', 'is_international')) {
            $payload['is_international'] = false;
        }
        if (Schema::hasColumn('shipments', 'has_dangerous_goods')) {
            $payload['has_dangerous_goods'] = false;
        }
        if (Schema::hasColumn('shipments', 'is_insured')) {
            $payload['is_insured'] = false;
        }
        if (Schema::hasColumn('shipments', 'is_cod')) {
            $payload['is_cod'] = false;
        }
        if (Schema::hasColumn('shipments', 'total_charge')) {
            $payload['total_charge'] = 25;
        }
        if (Schema::hasColumn('shipments', 'sender_name')) {
            $payload['sender_name'] = 'Sender';
        }
        if (Schema::hasColumn('shipments', 'sender_phone')) {
            $payload['sender_phone'] = '+966500000001';
        }
        if (Schema::hasColumn('shipments', 'sender_city')) {
            $payload['sender_city'] = 'Riyadh';
        }
        if (Schema::hasColumn('shipments', 'sender_country')) {
            $payload['sender_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'sender_address')) {
            $payload['sender_address'] = 'Street 1';
        }
        if (Schema::hasColumn('shipments', 'sender_address_1')) {
            $payload['sender_address_1'] = 'Street 1';
        }
        if (Schema::hasColumn('shipments', 'recipient_name')) {
            $payload['recipient_name'] = 'Recipient';
        }
        if (Schema::hasColumn('shipments', 'recipient_phone')) {
            $payload['recipient_phone'] = '+966500000002';
        }
        if (Schema::hasColumn('shipments', 'recipient_city')) {
            $payload['recipient_city'] = 'Jeddah';
        }
        if (Schema::hasColumn('shipments', 'recipient_country')) {
            $payload['recipient_country'] = 'SA';
        }
        if (Schema::hasColumn('shipments', 'recipient_address')) {
            $payload['recipient_address'] = 'Street 2';
        }
        if (Schema::hasColumn('shipments', 'recipient_address_1')) {
            $payload['recipient_address_1'] = 'Street 2';
        }
        if (Schema::hasColumn('shipments', 'currency')) {
            $payload['currency'] = 'SAR';
        }
        if (Schema::hasColumn('shipments', 'created_at')) {
            $payload['created_at'] = now();
        }
        if (Schema::hasColumn('shipments', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $payload = array_merge($payload, $overrides);
        $shipmentId = $this->insertRowAndReturnId('shipments', $payload);

        return DB::table('shipments')->where('id', $shipmentId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createContentDeclarationRecord(
        string $accountId,
        string $shipmentId,
        ?string $declaredBy = null,
        array $overrides = []
    ): object {
        $payload = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('content_declarations', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('content_declarations', 'shipment_id')) {
            $payload['shipment_id'] = $shipmentId;
        }
        if (Schema::hasColumn('content_declarations', 'contains_dangerous_goods')) {
            $payload['contains_dangerous_goods'] = false;
        }
        if (Schema::hasColumn('content_declarations', 'status')) {
            $payload['status'] = 'pending';
        }
        if (Schema::hasColumn('content_declarations', 'waiver_accepted')) {
            $payload['waiver_accepted'] = false;
        }
        if (Schema::hasColumn('content_declarations', 'declared_by')) {
            $payload['declared_by'] = $declaredBy ?? (string) Str::uuid();
        }
        if (Schema::hasColumn('content_declarations', 'locale')) {
            $payload['locale'] = 'en';
        }
        if (Schema::hasColumn('content_declarations', 'declared_at')) {
            $payload['declared_at'] = now();
        }

        $payload = array_merge($payload, $overrides);

        $declarationId = $this->insertRowAndReturnId('content_declarations', $payload);

        return DB::table('content_declarations')->where('id', $declarationId)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function createRiskScoreRecord(string $shipmentId, array $overrides = []): object
    {
        $payload = array_merge([
            'shipment_id' => $shipmentId,
            'overall_score' => 12,
            'delay_probability' => 10,
            'damage_probability' => 5,
            'customs_risk' => 2,
            'fraud_risk' => 1,
            'financial_risk' => 3,
            'risk_level' => 'low',
            'risk_factors' => json_encode(['delay' => 10]),
            'recommendations' => json_encode(['Monitor shipment']),
            'model_version' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        $riskId = $this->insertRowAndReturnId('risk_scores', $payload);

        return DB::table('risk_scores')->where('id', $riskId)->firstOrFail();
    }

    protected function createClaimRecord(string $accountId, string $shipmentId): object
    {
        $payload = [
            'description' => 'Damage claim for test coverage',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('claims', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('claims', 'shipment_id')) {
            $payload['shipment_id'] = $shipmentId;
        }
        if (Schema::hasColumn('claims', 'claim_number')) {
            $payload['claim_number'] = 'CLM-' . strtoupper(Str::random(8));
        }
        if (Schema::hasColumn('claims', 'claim_type')) {
            $payload['claim_type'] = 'damage';
        }
        if (Schema::hasColumn('claims', 'type')) {
            $payload['type'] = 'damage';
        }
        if (Schema::hasColumn('claims', 'claimed_amount')) {
            $payload['claimed_amount'] = 100;
        }
        if (Schema::hasColumn('claims', 'amount')) {
            $payload['amount'] = 100;
        }
        if (Schema::hasColumn('claims', 'claimed_currency')) {
            $payload['claimed_currency'] = 'SAR';
        }
        if (Schema::hasColumn('claims', 'status')) {
            $payload['status'] = 'draft';
        }
        if (Schema::hasColumn('claims', 'incident_date')) {
            $payload['incident_date'] = now()->subDay()->toDateString();
        }
        if (Schema::hasColumn('claims', 'sla_deadline')) {
            $payload['sla_deadline'] = now()->addDays(14)->toDateString();
        }

        $claimId = $this->insertRowAndReturnId('claims', $payload);

        return DB::table('claims')->where('id', $claimId)->firstOrFail();
    }

    protected function createCustomsBrokerRecord(string $accountId): object
    {
        $payload = [
            'account_id' => $accountId,
            'name' => 'Broker ' . Str::random(8),
            'license_number' => 'LIC-' . strtoupper(Str::random(6)),
            'country' => 'SA',
            'status' => 'active',
            'currency' => 'SAR',
            'commission_rate' => 5,
            'fixed_fee' => 50,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $brokerId = $this->insertRowAndReturnId('customs_brokers', $payload);

        return DB::table('customs_brokers')->where('id', $brokerId)->firstOrFail();
    }

    protected function createCustomsDeclarationRecord(string $accountId, string $shipmentId, ?string $brokerId = null, ?string $branchId = null): object
    {
        $payload = [
            'shipment_id' => $shipmentId,
            'declaration_number' => 'DEC-' . strtoupper(Str::random(8)),
            'declared_value' => 1200,
            'duty_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('customs_declarations', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('customs_declarations', 'broker_id')) {
            $payload['broker_id'] = $brokerId;
        }
        if (Schema::hasColumn('customs_declarations', 'branch_id')) {
            $payload['branch_id'] = $branchId;
        }
        if (Schema::hasColumn('customs_declarations', 'declaration_type')) {
            $payload['declaration_type'] = 'import';
        }
        if (Schema::hasColumn('customs_declarations', 'type')) {
            $payload['type'] = 'import';
        }
        if (Schema::hasColumn('customs_declarations', 'origin_country')) {
            $payload['origin_country'] = 'CN';
        }
        if (Schema::hasColumn('customs_declarations', 'destination_country')) {
            $payload['destination_country'] = 'SA';
        }
        if (Schema::hasColumn('customs_declarations', 'customs_status')) {
            $payload['customs_status'] = 'draft';
        }
        if (Schema::hasColumn('customs_declarations', 'status')) {
            $payload['status'] = 'draft';
        }
        if (Schema::hasColumn('customs_declarations', 'declared_currency')) {
            $payload['declared_currency'] = 'SAR';
        }
        if (Schema::hasColumn('customs_declarations', 'vat_amount')) {
            $payload['vat_amount'] = 0;
        }
        if (Schema::hasColumn('customs_declarations', 'excise_amount')) {
            $payload['excise_amount'] = 0;
        }
        if (Schema::hasColumn('customs_declarations', 'other_fees')) {
            $payload['other_fees'] = 0;
        }
        if (Schema::hasColumn('customs_declarations', 'total_customs_charges')) {
            $payload['total_customs_charges'] = 0;
        }
        if (Schema::hasColumn('customs_declarations', 'broker_fee')) {
            $payload['broker_fee'] = 0;
        }
        if (Schema::hasColumn('customs_declarations', 'inspection_flag')) {
            $payload['inspection_flag'] = false;
        }
        if (Schema::hasColumn('customs_declarations', 'notes')) {
            $payload['notes'] = 'Test declaration';
        }
        if (Schema::hasColumn('customs_declarations', 'port_name')) {
            $payload['port_name'] = 'JED';
        }

        $declarationId = $this->insertRowAndReturnId('customs_declarations', $payload);

        return DB::table('customs_declarations')->where('id', $declarationId)->firstOrFail();
    }

    protected function createVesselRecord(string $accountId): object
    {
        $payload = [
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('vessels', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('vessels', 'vessel_name')) {
            $payload['vessel_name'] = 'Vessel ' . Str::random(6);
        }
        if (Schema::hasColumn('vessels', 'name')) {
            $payload['name'] = 'Vessel ' . Str::random(6);
        }
        if (Schema::hasColumn('vessels', 'vessel_type')) {
            $payload['vessel_type'] = 'container';
        }
        if (Schema::hasColumn('vessels', 'type')) {
            $payload['type'] = 'Container Ship';
        }
        if (Schema::hasColumn('vessels', 'status')) {
            $payload['status'] = 'active';
        }
        if (Schema::hasColumn('vessels', 'imo_number')) {
            $payload['imo_number'] = 'IMO' . random_int(1000000, 9999999);
        }
        if (Schema::hasColumn('vessels', 'capacity_teu')) {
            $payload['capacity_teu'] = 1000;
        }

        $vesselId = $this->insertRowAndReturnId('vessels', $payload);

        return DB::table('vessels')->where('id', $vesselId)->firstOrFail();
    }

    protected function createVesselScheduleRecord(string $accountId, string $vesselId): object
    {
        $payload = [
            'account_id' => $accountId,
            'vessel_id' => $vesselId,
            'voyage_number' => 'VOY-' . strtoupper(Str::random(6)),
            'port_of_loading' => 'JEDDA',
            'port_of_discharge' => 'DAMAM',
            'etd' => now()->addDays(2),
            'eta' => now()->addDays(8),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $scheduleId = $this->insertRowAndReturnId('vessel_schedules', $payload);

        return DB::table('vessel_schedules')->where('id', $scheduleId)->firstOrFail();
    }

    protected function createContainerRecord(string $accountId, string $scheduleId, ?string $originBranchId = null, ?string $destinationBranchId = null): object
    {
        $payload = [
            'container_number' => 'MSCU' . random_int(1000000, 9999999),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('containers', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('containers', 'vessel_schedule_id')) {
            $payload['vessel_schedule_id'] = $scheduleId;
        }
        if (Schema::hasColumn('containers', 'vessel_id')) {
            $payload['vessel_id'] = $scheduleId;
        }
        if (Schema::hasColumn('containers', 'size')) {
            $payload['size'] = '20ft';
        }
        if (Schema::hasColumn('containers', 'type')) {
            $payload['type'] = Schema::getColumnType('containers', 'type') === 'string' ? 'dry' : 'dry';
        }
        if (Schema::hasColumn('containers', 'status')) {
            $payload['status'] = Schema::getColumnType('containers', 'status') === 'string' ? 'empty' : 'empty';
        }
        if (Schema::hasColumn('containers', 'origin_branch_id')) {
            $payload['origin_branch_id'] = $originBranchId;
        }
        if (Schema::hasColumn('containers', 'destination_branch_id')) {
            $payload['destination_branch_id'] = $destinationBranchId;
        }
        if (Schema::hasColumn('containers', 'origin_port')) {
            $payload['origin_port'] = 'JED';
        }
        if (Schema::hasColumn('containers', 'destination_port')) {
            $payload['destination_port'] = 'DMM';
        }

        $containerId = $this->insertRowAndReturnId('containers', $payload);

        return DB::table('containers')->where('id', $containerId)->firstOrFail();
    }

    protected function createDriverRecord(string $accountId, ?string $branchId = null): object
    {
        $payload = [
            'name' => 'Driver ' . Str::random(6),
            'phone' => '+9665' . random_int(10000000, 99999999),
            'license_number' => 'DRV-' . strtoupper(Str::random(6)),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('drivers', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('drivers', 'branch_id')) {
            $payload['branch_id'] = $branchId;
        }
        if (Schema::hasColumn('drivers', 'license_expiry')) {
            $payload['license_expiry'] = now()->addYear()->toDateString();
        }
        if (Schema::hasColumn('drivers', 'status')) {
            $payload['status'] = 'available';
        }
        if (Schema::hasColumn('drivers', 'rating')) {
            $payload['rating'] = 5;
        }
        if (Schema::hasColumn('drivers', 'total_deliveries')) {
            $payload['total_deliveries'] = 0;
        }
        if (Schema::hasColumn('drivers', 'successful_deliveries')) {
            $payload['successful_deliveries'] = 0;
        }
        if (Schema::hasColumn('drivers', 'deliveries_count')) {
            $payload['deliveries_count'] = 0;
        }
        if (Schema::hasColumn('drivers', 'employee_id')) {
            $payload['employee_id'] = 'EMP-' . strtoupper(Str::random(6));
        }

        $driverId = $this->insertRowAndReturnId('drivers', $payload);

        return DB::table('drivers')->where('id', $driverId)->firstOrFail();
    }

    protected function createTariffRuleRecord(string $accountId): object
    {
        $payload = [
            'account_id' => $accountId,
            'name' => 'Tariff ' . Str::random(6),
            'origin_country' => 'SA',
            'destination_country' => 'AE',
            'shipment_type' => 'air',
            'pricing_unit' => 'kg',
            'base_price' => 10,
            'price_per_unit' => 2,
            'minimum_charge' => 10,
            'fuel_surcharge_percent' => 0,
            'security_surcharge' => 0,
            'peak_season_surcharge' => 0,
            'insurance_rate' => 0,
            'currency' => 'SAR',
            'valid_from' => now()->subDay()->toDateString(),
            'is_active' => true,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $tariffId = $this->insertRowAndReturnId('tariff_rules', $payload);

        return DB::table('tariff_rules')->where('id', $tariffId)->firstOrFail();
    }

    protected function createBranchStaffRecord(string $branchId, string $userId): object
    {
        $payload = [
            'branch_id' => $branchId,
            'user_id' => $userId,
            'role' => 'agent',
            'assigned_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('branch_staff', 'is_primary')) {
            $payload['is_primary'] = true;
        }

        $id = $this->insertRowAndReturnId('branch_staff', $payload);

        return DB::table('branch_staff')->where('id', $id)->firstOrFail();
    }

    protected function createContainerShipmentRecord(string $containerId, string $shipmentId): object
    {
        $payload = [
            'container_id' => $containerId,
            'shipment_id' => $shipmentId,
            'packages_count' => 1,
            'loaded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = $this->insertRowAndReturnId('container_shipments', $payload);

        return DB::table('container_shipments')->where('id', $id)->firstOrFail();
    }

    protected function createDeliveryAssignmentRecord(
        string $accountId,
        string $shipmentId,
        string $driverId,
        ?string $branchId = null
    ): object {
        $payload = [
            'shipment_id' => $shipmentId,
            'driver_id' => $driverId,
            'type' => 'delivery',
            'status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('delivery_assignments', 'account_id')) {
            $payload['account_id'] = $accountId;
        }
        if (Schema::hasColumn('delivery_assignments', 'branch_id')) {
            $payload['branch_id'] = $branchId;
        }
        if (Schema::hasColumn('delivery_assignments', 'assignment_number')) {
            $payload['assignment_number'] = 'DLV-' . strtoupper(Str::random(8));
        }
        if (Schema::hasColumn('delivery_assignments', 'attempt_number')) {
            $payload['attempt_number'] = 1;
        }
        if (Schema::hasColumn('delivery_assignments', 'max_attempts')) {
            $payload['max_attempts'] = 3;
        }

        $id = $this->insertRowAndReturnId('delivery_assignments', $payload);

        return DB::table('delivery_assignments')->where('id', $id)->firstOrFail();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function insertRowAndReturnId(string $table, array $payload): string|int
    {
        if (!array_key_exists('id', $payload) && !$this->tableUsesNumericId($table)) {
            $payload['id'] = (string) Str::uuid();
        }

        if ($this->tableUsesNumericId($table)) {
            unset($payload['id']);

            return DB::table($table)->insertGetId($payload);
        }

        DB::table($table)->insert($payload);

        return $payload['id'];
    }

    protected function tableUsesNumericId(string $table): bool
    {
        if (!Schema::hasColumn($table, 'id')) {
            return false;
        }

        $type = strtolower((string) Schema::getColumnType($table, 'id'));

        return in_array($type, [
            'integer',
            'int',
            'tinyint',
            'smallint',
            'mediumint',
            'bigint',
            'biginteger',
            'unsignedinteger',
            'unsignedbiginteger',
        ], true);
    }
}
