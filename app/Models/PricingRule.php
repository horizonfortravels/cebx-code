<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class PricingRule extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    public function scopeActive(Builder $query): Builder
    {
        if (Schema::hasColumn($this->getTable(), 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }

    public function scopeForAccountOrDefault(Builder $query, ?string $accountId): Builder
    {
        if (!Schema::hasColumn($this->getTable(), 'account_id')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($accountId): void {
            if ($accountId !== null && $accountId !== '') {
                $builder->where('account_id', $accountId)->orWhereNull('account_id');
                return;
            }

            $builder->whereNull('account_id');
        });
    }

    /**
     * Match legacy RT-style pricing rules against a shipment/rating context.
     *
     * @param  array<string, mixed>  $context
     */
    public function matches(array $context): bool
    {
        $carrierCode = strtoupper((string) ($context['carrier_code'] ?? ''));
        $serviceCode = strtoupper((string) ($context['service_code'] ?? ''));
        $originCountry = strtoupper((string) ($context['origin_country'] ?? ''));
        $destinationCountry = strtoupper((string) ($context['destination_country'] ?? ''));
        $zone = strtoupper((string) ($context['zone'] ?? ''));
        $shipmentType = strtolower((string) ($context['shipment_type'] ?? ''));
        $laneType = $originCountry !== '' && $destinationCountry !== '' && $originCountry !== $destinationCountry
            ? 'international'
            : 'domestic';
        $storeId = (string) ($context['store_id'] ?? '');
        $weight = (float) ($context['chargeable_weight'] ?? $context['weight'] ?? 0);
        $isCod = array_key_exists('is_cod', $context) ? (bool) $context['is_cod'] : null;

        if (filled($this->carrier_code) && strtoupper((string) $this->carrier_code) !== $carrierCode) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'service_code') && filled($this->service_code)
            && strtoupper((string) $this->service_code) !== $serviceCode) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'origin_country') && filled($this->origin_country)
            && strtoupper((string) $this->origin_country) !== $originCountry) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'destination_country') && filled($this->destination_country)
            && strtoupper((string) $this->destination_country) !== $destinationCountry) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'destination_zone') && filled($this->destination_zone)
            && strtoupper((string) $this->destination_zone) !== $zone) {
            return false;
        }

        if (filled($this->zone_name) && $zone !== '' && strtoupper((string) $this->zone_name) !== $zone) {
            return false;
        }

        $ruleShipmentType = strtolower((string) ($this->shipment_type ?? ''));
        if ($ruleShipmentType !== '' && $ruleShipmentType !== 'any' && !in_array($ruleShipmentType, array_filter([$shipmentType, $laneType]), true)) {
            return false;
        }

        $legacyServiceType = strtolower((string) ($this->service_type ?? ''));
        $hasModernShipmentType = Schema::hasColumn($this->getTable(), 'shipment_type') && $ruleShipmentType !== '';
        if (!$hasModernShipmentType && $legacyServiceType !== '' && $legacyServiceType !== $laneType) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'min_weight') && $this->min_weight !== null && $weight < (float) $this->min_weight) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'max_weight') && $this->max_weight !== null && $weight > (float) $this->max_weight) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'store_id') && filled($this->store_id) && $storeId !== '' && (string) $this->store_id !== $storeId) {
            return false;
        }

        if (Schema::hasColumn($this->getTable(), 'is_cod') && $this->is_cod !== null && $isCod !== null && (bool) $this->is_cod !== $isCod) {
            return false;
        }

        return true;
    }
}
