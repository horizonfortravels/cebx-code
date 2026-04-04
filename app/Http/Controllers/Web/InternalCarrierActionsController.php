<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CarrierSettingsService;
use App\Services\InternalCarrierIntegrationActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalCarrierActionsController extends Controller
{
    public function __construct(
        private readonly InternalCarrierIntegrationActionService $actionService,
        private readonly CarrierSettingsService $carrierSettingsService,
    ) {}

    public function toggle(Request $request, string $carrier): RedirectResponse
    {
        $payload = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->actionService->toggle(
                $carrier,
                $this->currentUser($request),
                (bool) $payload['is_enabled'],
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.carriers.show', $carrier)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.carriers.show', $carrier)
            ->with('success', sprintf(
                'Carrier integration is now %s and the change was recorded in the internal audit trail.',
                (bool) $payload['is_enabled'] ? 'enabled' : 'disabled'
            ));
    }

    public function test(Request $request, string $carrier): RedirectResponse
    {
        try {
            $log = $this->actionService->testConnection(
                $carrier,
                $this->currentUser($request),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.carriers.show', $carrier)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.carriers.show', $carrier)
            ->with('success', sprintf(
                'Connection test recorded a %s result and updated the carrier health snapshot.',
                (string) $log->status
            ));
    }

    public function updateCredentials(Request $request, string $carrier): RedirectResponse
    {
        try {
            $payload = $request->validate($this->credentialRules($carrier, false));
            $result = $this->actionService->updateCredentials(
                $carrier,
                $this->currentUser($request),
                $payload,
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.carriers.show', $carrier)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('internal.carriers.show', $carrier)
            ->with('success', sprintf(
                'Carrier credentials were updated, remained masked in the portal, and a fresh connection test recorded a %s result.',
                (string) $result['connection_log']->status
            ));
    }

    public function rotateCredentials(Request $request, string $carrier): RedirectResponse
    {
        try {
            $payload = $request->validate($this->credentialRules($carrier, true));
            $result = $this->actionService->rotateCredentials(
                $carrier,
                $this->currentUser($request),
                $payload,
                (string) $payload['reason'],
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.carriers.show', $carrier)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('internal.carriers.show', $carrier)
            ->with('success', sprintf(
                'Carrier credentials were rotated through the stored integration contract, stayed masked in the portal, and the follow-up connection test recorded a %s result.',
                (string) $result['connection_log']->status
            ));
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function credentialRules(string $carrier, bool $rotationOnly): array
    {
        $fieldNames = $rotationOnly
            ? $this->carrierSettingsService->rotationFieldNames($carrier)
            : $this->carrierSettingsService->editableFieldNames($carrier);

        $rules = [
            'reason' => ['required', 'string', 'max:500'],
        ];

        foreach ($fieldNames as $fieldName) {
            $rules[$fieldName] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }
}
