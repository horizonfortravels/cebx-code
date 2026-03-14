<?php
namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class ShipmentPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $this->allowsTenantAction($user, 'shipments.read');
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.read', $shipment->account_id);
    }

    public function create(User $user): bool
    {
        return $this->allowsTenantAction($user, 'shipments.create')
            || $this->allowsTenantAction($user, 'shipments.manage');
    }

    public function updateDraft(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.update_draft', $shipment->account_id)
            || $this->allowsTenantResourceAction($user, 'shipments.manage', $shipment->account_id);
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.manage', $shipment->account_id);
    }

    public function cancel(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.manage', $shipment->account_id);
    }

    public function printLabel(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.print_label', $shipment->account_id);
    }

    public function createReturn(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'shipments.manage', $shipment->account_id);
    }

    public function paymentPreflight(User $user, Shipment $shipment): bool
    {
        return $this->allowsTenantResourceAction($user, 'billing.manage', $shipment->account_id);
    }

    public function bulkImport(User $user): bool
    {
        return $this->allowsTenantAction($user, 'shipments.manage');
    }
}
