<?php

namespace App\Support\Governance;

use App\Enums\SurveyQuestionKind;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;

/**
 * Tallies a survey: per choice question each option's count and share of respondents; per
 * rating question the average and spread; per text question the answers (with the
 * respondent's name only if the survey isn't anonymous).
 */
class SurveyResults
{
    /**
     * @return array{responses: int, questions: list<array{id: int, title: string, kind: SurveyQuestionKind, options?: list<array{label: string, count: int, percent: string}>, average?: string|null, ratings?: array<int, int>, texts?: list<array{text: string, name: string|null}>}>}
     */
    public function for(Survey $survey): array
    {
        $survey->loadMissing('questions.options');
        $responses = SurveyResponse::query()->withoutGlobalScopes()->where('survey_id', $survey->id)->with('user')->get()->keyBy('id');
        $answers = SurveyAnswer::query()->withoutGlobalScopes()->whereIn('survey_response_id', $responses->keys())->get()->groupBy('survey_question_id');
        $total = $responses->count();
        $questions = [];

        foreach ($survey->questions as $question) {
            $mine = $answers->get($question->id, collect());
            $row = ['id' => $question->id, 'title' => $question->title, 'kind' => $question->kind];

            if ($question->kind->hasOptions()) {
                $row['options'] = array_values($question->options->map(fn ($option) => [
                    'label' => $option->label,
                    'count' => $count = $mine->where('survey_option_id', $option->id)->count(),
                    'percent' => $total === 0 ? '0.0' : number_format($count * 100 / $total, 1, '.', ''),
                ])->all());
            } elseif ($question->kind === SurveyQuestionKind::Rating) {
                $ratings = $mine->pluck('rating')->filter()->map(fn ($rating) => (int) $rating);
                $row['average'] = $ratings->isEmpty() ? null : number_format($ratings->avg() ?? 0, 1, '.', '');
                $row['ratings'] = array_replace(array_fill(1, 5, 0), $ratings->countBy()->all());
            } else {
                $row['texts'] = array_values($mine->filter(fn ($answer) => filled($answer->text))->map(fn ($answer) => [
                    'text' => (string) $answer->text,
                    'name' => $survey->is_anonymous ? null : $responses->get($answer->survey_response_id)?->user->name,
                ])->all());
            }

            $questions[] = $row;
        }

        return ['responses' => $total, 'questions' => $questions];
    }
}
