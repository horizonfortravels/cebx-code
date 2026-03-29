<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\PublicTrackingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\View\View;

class PublicTrackingPortalController extends Controller
{
    public function __construct(
        private PublicTrackingService $publicTracking,
    ) {}

    public function show(string $token): View
    {
        try {
            $shipment = $this->publicTracking->resolveShipment($token);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        app()->setLocale('ar');

        return view('pages.tracking.public', [
            'tracking' => $this->publicTracking->present($shipment),
        ]);
    }
}
