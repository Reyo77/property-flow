<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Engagement\SubmitSurveyResponse;
use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Community engagement
 */
class SurveyResponseController extends Controller
{
    /**
     * Answer a survey or poll
     *
     * Once per person. `answers` is keyed by question id: an option id for a single choice, a list
     * of option ids for multiple choice, 1–5 for a rating, text for a written answer. Required
     * questions must be answered.
     *
     * @bodyParam answers object required Example: {"1": 2, "2": [4, 5], "3": 4, "4": "More bike racks"}
     *
     * @response 201 {"data": {"survey_id": 1, "answered": true}}
     */
    public function store(Request $request, Community $community, Survey $survey, SubmitSurveyResponse $submit): JsonResponse
    {
        Gate::authorize('view', $survey);

        $validated = $request->validate(['answers' => ['present', 'array']]);

        /** @var User $user */
        $user = $request->user();
        $submit->handle($survey, $user, $validated['answers']);

        return response()->json(['data' => ['survey_id' => $survey->id, 'answered' => true]], 201);
    }
}
