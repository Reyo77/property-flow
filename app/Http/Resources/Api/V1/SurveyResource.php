<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Survey;
use App\Models\SurveyOption;
use App\Models\SurveyQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Survey
 */
class SurveyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'title' => $this->title,
            'description' => $this->description,
            'is_poll' => $this->is_poll,
            'is_anonymous' => $this->is_anonymous,
            'audience' => $this->audience->value,
            'open' => $this->isOpen(),
            'published_at' => $this->published_at?->toIso8601String(),
            'closes_at' => $this->closes_at?->toIso8601String(),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn (SurveyQuestion $question) => [
                'id' => $question->id,
                'kind' => $question->kind->value,
                'title' => $question->title,
                'required' => $question->is_required,
                'options' => $question->options->map(fn (SurveyOption $option) => ['id' => $option->id, 'label' => $option->label])->values(),
            ])->values()),
        ];
    }
}
