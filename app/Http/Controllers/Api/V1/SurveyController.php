<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SurveyResource;
use App\Models\Community;
use App\Models\Survey;
use App\Models\User;
use App\Support\Api\ApiQuery;
use App\Support\Governance\AudienceCheck;
use App\Support\Governance\SurveyResults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Community engagement
 *
 * Surveys and quick polls, forms to sign, and the community board. Poll results are shown to
 * those who answered; survey results to the team.
 */
class SurveyController extends Controller
{
    /**
     * List surveys and polls
     *
     * @apiResourceCollection App\Http\Resources\Api\V1\SurveyResource
     *
     * @apiResourceModel App\Models\Survey paginate=25
     */
    public function index(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Survey::class, $community]);

        /** @var User $user */
        $user = $request->user();

        return SurveyResource::collection(ApiQuery::paginate(
            $request,
            $community->surveys()->getQuery(),
            sorts: ['published_at', 'closes_at'],
            defaultSort: '-published_at',
            visible: fn (Survey $survey) => $user->can('view', $survey),
        ));
    }

    /**
     * Show a survey or poll
     *
     * `can_answer` says whether to show the questions; `results` appears when you may see them.
     *
     * @response {"data": {"id": 1, "title": "New lobby paint colour", "is_poll": true, "open": true, "questions": [{"id": 1, "kind": "single_choice", "title": "Which colour do you prefer?", "required": true, "options": [{"id": 1, "label": "Warm grey"}]}]}, "can_answer": false, "answered": true, "results": {"responses": 19, "questions": [{"title": "Which colour do you prefer?", "options": [{"label": "Warm grey", "count": 5, "percent": "26.3"}]}]}}
     */
    public function show(Request $request, Community $community, Survey $survey, AudienceCheck $audience, SurveyResults $results): JsonResponse
    {
        Gate::authorize('view', $survey);

        /** @var User $user */
        $user = $request->user();
        $answered = $survey->responses()->where('user_id', $user->id)->exists();

        return (new SurveyResource($survey->load('questions.options')))->additional([
            'answered' => $answered,
            'can_answer' => $survey->isOpen() && ! $answered && $audience->includes($user, $survey->community_id, $survey->audience),
            'results' => $user->can('viewResults', $survey) ? $results->for($survey) : null,
        ])->response();
    }
}
