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

    public function __invoke(Community $community, Violation $violation, ViolationNotice $notice): Response
    {
        $this->authorize('view', $violation);
        abort_unless($notice->violation_id === $violation->id, 404);

        $violation->loadMissing(['rule', 'unit.building', 'unit.residencies.resident']);

        return Pdf::loadView('pdf.violation-notice', ['community' => $community, 'violation' => $violation, 'notice' => $notice->loadMissing('invoice')])
            ->download("notice-{$violation->id}-{$notice->stage->value}-{$notice->issued_on->toDateString()}.pdf");
    }
}
