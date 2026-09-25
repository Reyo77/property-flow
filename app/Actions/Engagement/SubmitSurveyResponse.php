<?php

namespace App\Actions\Engagement;

use App\Enums\SurveyQuestionKind;
use App\Models\Survey;
use App\Models\SurveyAnswer;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Support\Governance\AudienceCheck;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records one person's answers. Each person answers once; answers are checked against each
 * question's kind (a valid option, 1–5 for a rating, some text) and required questions must
 * be answered.
 */
class SubmitSurveyResponse
{
    public function __construct(private readonly AudienceCheck $audienceCheck) {}

    /**
     * @param  array<int, int|string|list<int|string>|null>  $answers  keyed by question id: an option id, option ids, a rating or text
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Survey $survey, User $respondent, array $answers): SurveyResponse
    {
        if (! $survey->isOpen()) {
            throw ValidationException::withMessages(['survey' => __('This survey is not open.')]);
        }

        if (! $this->audienceCheck->includes($respondent, $survey->community_id, $survey->audience)) {
            throw new AuthorizationException(__('This survey isn\'t for you.'));
        }

        $survey->loadMissing('questions.options');
        $rows = [];

        foreach ($survey->questions as $question) {
            $rows = [...$rows, ...$this->answerRows($question, $answers[$question->id] ?? null)];
        }

        try {
            return DB::transaction(function () use ($survey, $respondent, $rows): SurveyResponse {
                $response = new SurveyResponse;
                $response->forceFill([
                    'company_id' => $survey->company_id,
                    'survey_id' => $survey->id,
                    'user_id' => $respondent->id,
                    'submitted_at' => now(),
                ])->save();

                foreach ($rows as $row) {
                    (new SurveyAnswer)->forceFill([...$row, 'company_id' => $survey->company_id, 'survey_response_id' => $response->id])->save();
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['survey' => __('You have already answered this survey.')]);
        }
    }

    /**
     * @param  int|string|list<int|string>|null  $value
     * @return list<array{survey_question_id: int, survey_option_id?: int, rating?: int, text?: string}>
     */
    private function answerRows(SurveyQuestion $question, int|string|array|null $value): array
    {
        $field = "answers.{$question->id}";
        $blank = $value === null || $value === '' || $value === [];

        if ($blank) {
            if ($question->is_required) {
                throw ValidationException::withMessages([$field => __('Please answer ":question".', ['question' => $question->title])]);
            }

            return [];
        }

        $validOptions = array_values($question->options->map(fn ($option): int => $option->id)->all());

        return match ($question->kind) {
            SurveyQuestionKind::SingleChoice => in_array((int) (is_array($value) ? 0 : $value), $validOptions, true)
                ? [['survey_question_id' => $question->id, 'survey_option_id' => (int) $value]]
                : throw ValidationException::withMessages([$field => __('Choose one of the options.')]),
            SurveyQuestionKind::MultipleChoice => $this->multipleChoiceRows($question, (array) $value, $validOptions, $field),
            SurveyQuestionKind::Rating => is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 && (string) (int) $value === (string) $value
                ? [['survey_question_id' => $question->id, 'rating' => (int) $value]]
                : throw ValidationException::withMessages([$field => __('Choose a rating from 1 to 5.')]),
            SurveyQuestionKind::Text => is_string($value) && mb_strlen($value) <= 2000
                ? [['survey_question_id' => $question->id, 'text' => trim($value)]]
                : throw ValidationException::withMessages([$field => __('Keep your answer under 2,000 characters.')]),
        };
    }

    /**
     * @param  array<int|string>  $chosen
     * @param  list<int>  $validOptions
     * @return list<array{survey_question_id: int, survey_option_id: int}>
     */
    private function multipleChoiceRows(SurveyQuestion $question, array $chosen, array $validOptions, string $field): array
    {
        $ids = array_values(array_unique(array_map('intval', $chosen)));

        if (array_diff($ids, $validOptions) !== []) {
            throw ValidationException::withMessages([$field => __('Choose from the options given.')]);
        }

        return array_map(fn (int $id) => ['survey_question_id' => $question->id, 'survey_option_id' => $id], $ids);
    }
}
