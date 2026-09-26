<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Maintenance\PostServiceRequestComment;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ServiceRequestCommentResource;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Maintenance
 */
class ServiceRequestCommentController extends Controller
{
    /**
     * Comment on a request
     *
     * Team members may set `internal` to keep the note from the resident.
     *
     * @bodyParam body string required Example: A plumber is booked for Thursday morning.
     * @bodyParam internal boolean Team only: hide from the resident. Example: false
     *
     * @apiResource 201 App\Http\Resources\Api\V1\ServiceRequestCommentResource
     *
     * @apiResourceModel App\Models\ServiceRequestComment with=author
     */
    public function store(Request $request, Community $community, ServiceRequest $serviceRequest, PostServiceRequestComment $postComment): JsonResponse
    {
        Gate::authorize('comment', $serviceRequest);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'internal' => ['boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $comment = $postComment->handle($serviceRequest, $user, $validated['body'], (bool) ($validated['internal'] ?? false));

        return (new ServiceRequestCommentResource($comment->load('author')))->response()->setStatusCode(201);
    }
}
