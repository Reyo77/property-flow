<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Engagement\ForumActions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ForumPostResource;
use App\Models\Community;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;

/**
 * @group Community engagement
 */
class ForumPostController extends Controller
{
    /**
     * Reply to a post
     *
     * Not on a locked, closed or hidden post.
     *
     * @bodyParam body string required Example: Is it still available?
     */
    #[ResponseFromApiResource(ForumPostResource::class, ForumPost::class, status: 201, with: ['author'])]
    public function store(Request $request, Community $community, ForumTopic $forumTopic, ForumActions $forum): JsonResponse
    {
        Gate::authorize('reply', $forumTopic);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        /** @var User $user */
        $user = $request->user();
        $post = $forum->reply($forumTopic, $user, $validated['body']);

        return (new ForumPostResource($post->load('author')))->response()->setStatusCode(201);
    }
}
