<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\Package;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a package's pickup signature inline, after checking the viewer may see the package.
 */
class PackageSignatureController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Community $community, Package $package): StreamedResponse
    {
        $this->authorize('view', $package);

        abort_if($package->signature_disk_path === null, 404);
        abort_unless(Storage::disk('local')->exists($package->signature_disk_path), 404);

        return Storage::disk('local')->response($package->signature_disk_path);
    }
}
