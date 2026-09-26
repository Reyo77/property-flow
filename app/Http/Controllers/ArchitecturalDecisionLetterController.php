<?php

namespace App\Http\Controllers;

use App\Models\ArchitecturalRequest;
use App\Models\Community;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

class ArchitecturalDecisionLetterController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download a renovation decision letter (PDF)
     *
     * Available once decided. Also linked from `decision_letter_url`.
     *
     * @group Violations and renovations
     *
     * @response 200 scenario="The file" [Binary application/pdf data]
     */
    public function __invoke(Community $community, ArchitecturalRequest $architecturalRequest): Response
    {
        $this->authorize('view', $architecturalRequest);
        abort_unless($architecturalRequest->status->isDecided(), 404);

        $architecturalRequest->loadMissing(['unit.building', 'submittedBy', 'decidedBy']);

        return Pdf::loadView('pdf.architectural-decision', ['community' => $community, 'architecturalRequest' => $architecturalRequest])
            ->download("renovation-decision-{$architecturalRequest->id}.pdf");
    }
}
