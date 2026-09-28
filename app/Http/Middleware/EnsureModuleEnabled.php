<?php

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Models\Community;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a community's optional feature areas (App\Enums\Module) once the company has switched
 * them off, so a disabled module's routes behave as if they do not exist.
 */
class EnsureModuleEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $community = $request->route('community');

        abort_if(
            $community instanceof Community && ! $community->moduleEnabled(Module::from($module)),
            404,
        );

        return $next($request);
    }
}
