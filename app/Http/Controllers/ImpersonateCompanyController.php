<?php

namespace App\Http\Controllers;

use App\Actions\Platform\ImpersonateCompany;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gated by {@see EnsureSuperAdmin} at the route level.
 */
class ImpersonateCompanyController extends Controller
{
    public function __invoke(Request $request, Company $company, ImpersonateCompany $impersonateCompany): RedirectResponse
    {
        $superAdmin = $request->user();
        abort_unless($superAdmin instanceof User, 403);

        $impersonateCompany->handle($superAdmin, $company);

        return redirect()->route('dashboard');
    }
}
