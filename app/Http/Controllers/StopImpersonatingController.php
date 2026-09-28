<?php

namespace App\Http\Controllers;

use App\Actions\Platform\StopImpersonating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StopImpersonatingController extends Controller
{
    public function __invoke(Request $request, StopImpersonating $stopImpersonating): RedirectResponse
    {
        $impersonatedUser = $request->user();
        abort_unless($impersonatedUser instanceof User, 403);

        $stopImpersonating->handle($impersonatedUser);

        return redirect()->route('platform.companies.index');
    }
}
