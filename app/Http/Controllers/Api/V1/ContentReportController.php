<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Engagement\ForumActions;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Community engagement
 */
class ContentReportController extends Controller
{
    /**
     * Report a post or reply
     *
     * Flags it for the moderators. Once per person; not your own.
     *
     * @bodyParam reason string required Example: Looks like a scam
     * @bodyParam post_id integer A reply's id, to report the reply instead of the post. Example: 12
     *
     * @response 201 {"data": {"reported": true}}
     */
    public function store(Request $request, Community $community, ForumTopic $forumTopic, ForumActions $forum): JsonResponse
    {
        Gate::authorize('view', $forumTopic);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'post_id' => ['nullable', 'integer', Rule::exists('forum_posts', 'id')->where('forum_topic_id', $forumTopic->id)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $content = isset($validated['post_id']) ? $forumTopic->posts()->findOrFail((int) $validated['post_id']) : $forumTopic;
        $forum->report($content, $user, $validated['reason']);

        return response()->json(['data' => ['reported' => true]], 201);
    }
}
