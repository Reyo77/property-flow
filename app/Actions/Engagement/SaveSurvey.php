<?php

namespace App\Actions\Engagement;

use App\Enums\SurveyQuestionKind;
use App\Models\Community;
use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates or rewrites a draft survey or poll. Once published its questions are fixed.
 */
class SaveSurvey
{
    /**
     * @param  array{title: string, description: string|null, is_poll: bool, audience: string, is_anonymous: bool, closes_at: string|null}  $attributes
     * @param  list<array{kind: string, title: string, is_required: bool, options: list<string>}>  $questions
     *
     * @throws LogicException
     */
    public function handle(Community $community, ?Survey $survey, array $attributes, array $questions, User $author): Survey
    {
        if ($survey?->published_at !== null) {
            throw new LogicException(__('A published survey can no longer be edited.'));
        }

        if ($attributes['is_poll'] && count($questions) !== 1) {
            throw new LogicException(__('A poll asks exactly one question.'));
        }

        return DB::transaction(function () use ($community, $survey, $attributes, $questions, $author): Survey {
            if ($survey === null) {
                $survey = new Survey($attributes);
                $survey->forceFill(['company_id' => $community->company_id, 'community_id' => $community->id, 'created_by_id' => $author->id])->save();
            } else {
                $survey->update($attributes);
                SurveyQuestion::query()->where('survey_id', $survey->id)->delete();
            }

            foreach ($questions as $index => $data) {
                $kind = SurveyQuestionKind::from($data['kind']);
                $question = new SurveyQuestion(['position' => $index + 1, 'kind' => $kind, 'title' => $data['title'], 'is_required' => $data['is_required']]);
                $question->forceFill(['company_id' => $community->company_id, 'survey_id' => $survey->id])->save();

                if (! $kind->hasOptions()) {
                    continue;
                }

                foreach ($data['options'] as $position => $label) {
                    $option = new SurveyOption(['position' => $position + 1, 'label' => $label]);
                    $option->forceFill(['company_id' => $community->company_id, 'survey_question_id' => $question->id])->save();
                }
            }

            return $survey;
        });
    }

    /**
     * @throws LogicException
     */
    public function publish(Survey $survey): void
    {
        $survey->loadMissing('questions.options');

        if ($survey->questions->isEmpty()) {
            throw new LogicException(__('Add at least one question before publishing.'));
        }

        if ($survey->questions->contains(fn (SurveyQuestion $question) => $question->kind->hasOptions() && $question->options->count() < 2)) {
            throw new LogicException(__('Every choice question needs at least two options.'));
        }

        $survey->forceFill(['published_at' => now()])->save();
    }
}
