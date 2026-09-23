<?php

namespace App\Http\Middleware;

use App\Models\Community;
use App\Support\Tenancy\CurrentCommunity;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the community in the URL the one the user is working in.
 */
class RememberCurrentCommunity
{
    public function __construct(private readonly CurrentCommunity $currentCommunity) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $community = $request->route('community');

        if ($community instanceof Community && $request->user()?->can('view', $community)) {
            $this->currentCommunity->set($community);
        }

        return $next($request);
    }
}
