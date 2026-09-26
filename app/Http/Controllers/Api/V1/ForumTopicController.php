<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Engagement\ForumActions;
use App\Enums\ForumTopicKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ForumTopicResource;
use App\Models\Community;
use App\Models\ForumTopic;
use App\Models\User;
use App\Support\Api\ApiQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Community engagement
 */
class ForumTopicController extends Controller
{
    /**
     * List community board posts
     *
     * Pinned first, then most recently active. Hidden posts are left out for residents.
     *
     * @queryParam filter[section] `discussion` or `classifieds`. Example: classifieds
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\ForumTopicResource
     *
     * @apiResourceModel App\Models\ForumTopic paginate=25 with=author
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [ForumTopic::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return ForumTopicResource::collection(ApiQuery::paginate(
            $request,
            $community->forumTopics()->with('author')->orderByDesc('is_pinned')->getQuery(),
            filters: ['section' => fn (Builder $query, string $value) => $value === 'classifieds'
                ? $query->whereIn('kind', ForumTopicKind::classifieds())
                : $query->where('kind', ForumTopicKind::Discussion)],
            sorts: ['last_activity_at', 'created_at'],
            defaultSort: '-last_activity_at',
            visible: fn (ForumTopic $topic) => $user->can('view', $topic),
        ));
    }

    /**
     * Post to the community board
     *
     * A discussion, or a classified (`for_sale`, `wanted`, `free`, `service`); only `for_sale` has a
     * price.
     *
     * @bodyParam kind string required `discussion`, `for_sale`, `wanted`, `free` or `service`. Example: for_sale
     * @bodyParam title string required Example: Kids bike, 20 inch
     * @bodyParam body string required Example: Outgrown, good condition.
     * @bodyParam price_cents integer For `for_sale`. Example: 4500
     *
     * @apiResource 201 App\Http\Resources\Api\V1\ForumTopicResource
     *
     * @apiResourceModel App\Models\ForumTopic with=author
     */
    public function store(Request $request, Community $community, ForumActions $forum): JsonResponse
    {
        Gate::authorize('create', [ForumTopic::class, $community]);

        $validated = $request->validate([
            'kind' => ['required', Rule::enum(ForumTopicKind::class)],
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:99999999'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $topic = $forum->postTopic($community, $user, ForumTopicKind::from($validated['kind']), $validated['title'], $validated['body'], isset($validated['price_cents']) ? (int) $validated['price_cents'] : null);

        return (new ForumTopicResource($topic->load('author')))->response()->setStatusCode(201);
    }

    /**
     * Show a post and its replies
     *
     * @apiResource App\Http\Resources\Api\V1\ForumTopicResource
     *
     * @apiResourceModel App\Models\ForumTopic with=author,posts.author
     */
    public function show(Request $request, Community $community, ForumTopic $forumTopic): ForumTopicResource
    {
        Gate::authorize('view', $forumTopic);

        $moderator = $request->user()?->can('moderate', $forumTopic) === true;

        return new ForumTopicResource($forumTopic->load([
            'author',
            'posts' => fn ($query) => $query->when(! $moderator, fn ($query) => $query->whereNull('hidden_at'))->with('author'),
        ]));
    }
}
