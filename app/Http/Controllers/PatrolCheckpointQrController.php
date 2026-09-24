<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\PatrolCheckpoint;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;

class PatrolCheckpointQrController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Community $community, PatrolCheckpoint $patrolCheckpoint): Response
    {
        $this->authorize('view', $patrolCheckpoint->patrolRoute);

        $qrCode = new QrCode(route('patrol-scan', $patrolCheckpoint->qr_token));
        $result = (new SvgWriter)->write($qrCode);

        return response($result->getString(), 200, ['Content-Type' => $result->getMimeType()]);
    }
}
