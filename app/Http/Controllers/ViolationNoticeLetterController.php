<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Violation;
use App\Models\ViolationNotice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

class ViolationNoticeLetterController extends Controller
{
    use AuthorizesRequests;

    /**
     * Download a violation notice letter (PDF)
     *
     * The letter sent to the owners for one notice. Also linked from `notices.*.letter_url`.
     *
     * @group Violations and renovations
     *
     * @response 200 scenario="The file" [Binary application/pdf data]
     */
    public function __invoke(Community $community, Violation $violation, ViolationNotice $notice): Response
    {
        $this->authorize('view', $violation);
        abort_unless($notice->violation_id === $violation->id, 404);

        $violation->loadMissing(['rule', 'unit.building', 'unit.residencies.resident']);

        return Pdf::loadView('pdf.violation-notice', ['community' => $community, 'violation' => $violation, 'notice' => $notice->loadMissing('invoice')])
            ->download("notice-{$violation->id}-{$notice->stage->value}-{$notice->issued_on->toDateString()}.pdf");
    }
}
