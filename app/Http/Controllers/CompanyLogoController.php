<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a company's logo. Public and unauthenticated: the logo also appears on the community's
 * public website (Phase 10b), which has no login.
 */
class CompanyLogoController extends Controller
{
    public function __invoke(Company $company): StreamedResponse
    {
        abort_if($company->logo_disk_path === null, 404);
        abort_unless(Storage::disk('local')->exists($company->logo_disk_path), 404);

        return Storage::disk('local')->response($company->logo_disk_path);
    }
}
