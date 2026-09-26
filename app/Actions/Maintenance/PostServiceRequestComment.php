<?php

namespace App\Actions\Maintenance;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class PostServiceRequestComment
{
    /**
     * Adds a comment to the request. An internal note (hidden from residents) needs the team's
     * permission to write one.
     *
     * @throws AuthorizationException
     */
    public function handle(ServiceRequest $serviceRequest, User $author, string $body, bool $internal = false): ServiceRequestComment
    {
        Gate::forUser($author)->authorize('comment', $serviceRequest);

        if ($internal) {
            Gate::forUser($author)->authorize('addInternalComment', $serviceRequest);
        }

        return $serviceRequest->comments()->create([
            'author_id' => $author->id,
            'body' => $body,
            'visible_to_resident' => ! $internal,
        ]);
    }
}
