<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Models\ContentDeclaration;
use App\Models\User;
use App\Services\DgComplianceService;
use App\Support\Internal\InternalControlPlane;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InternalComplianceActionsController extends Controller
{
    public function __construct(
        private readonly DgComplianceService $dgComplianceService,
        private readonly InternalControlPlane $controlPlane,
    ) {}

    public function requestCorrection(Request $request, string $declaration): RedirectResponse
    {
        $payload = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $declarationModel = ContentDeclaration::query()
            ->withoutGlobalScopes()
            ->findOrFail($declaration);

        try {
            $actor = $this->currentUser($request);

            $this->dgComplianceService->requestCorrection(
                (string) $declarationModel->id,
                (string) $actor->id,
                (string) $payload['reason'],
                $this->controlPlane->displayRoleName($actor),
                $request->ip(),
            );
        } catch (BusinessException $exception) {
            return redirect()
                ->route('internal.compliance.show', $declarationModel)
                ->with('error', $exception->getMessage())
                ->withInput();
        }

        return redirect()
            ->route('internal.compliance.show', $declarationModel)
            ->with('success', 'Compliance case marked as requires action and the internal review reason was recorded.');
    }

    private function currentUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
