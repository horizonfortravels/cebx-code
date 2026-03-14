<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AddressController — FR-SH-004: Address Book management
 */
class AddressController extends Controller
{
    public function __construct(protected ShipmentService $shipmentService) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $addresses = $this->shipmentService->listAddresses($request->user()->account_id, $type);
        return response()->json(['data' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'              => 'nullable|in:sender,recipient,both',
            'is_default_sender' => 'nullable|boolean',
            'label'             => 'nullable|string|max:100',
            'contact_name'      => 'required|string|max:200',
            'company_name'      => 'nullable|string|max:200',
            'phone'             => 'required|string|max:30',
            'email'             => 'nullable|email|max:255',
            'address_line_1'    => 'required|string|max:300',
            'address_line_2'    => 'nullable|string|max:300',
            'city'              => 'required|string|max:100',
            'state'             => 'nullable|string|max:100',
            'postal_code'       => 'nullable|string|max:20',
            'country'           => 'required|string|size:2',
        ]);

        $address = $this->shipmentService->saveAddress(
            $request->user()->account_id, $data, $request->user()
        );

        return response()->json(['data' => $address], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->shipmentService->deleteAddress($request->user()->account_id, $id);
        return response()->json(['message' => 'تم حذف العنوان بنجاح.']);
    }
}
