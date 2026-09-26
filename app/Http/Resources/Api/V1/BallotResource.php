<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Ballot;
use App\Models\BallotOption;
use App\Models\BallotQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ballot
 */
class BallotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status();

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'meeting' => $this->meeting === null ? null : ['id' => $this->meeting->id, 'title' => $this->meeting->title],
            'title' => $this->title,
            'description' => $this->description,
            'weighting' => $this->weighting->value,
            'quorum_percent' => $this->quorum_percent,
            'opens_at' => $this->opens_at->toIso8601String(),
            'closes_at' => $this->closes_at->toIso8601String(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn (BallotQuestion $question) => [
                'id' => $question->id,
                'title' => $question->title,
                'options' => $question->options->map(fn (BallotOption $option) => ['id' => $option->id, 'label' => $option->label])->values(),
            ])->values()),
            'results' => $this->when($request->user()?->can('viewResults', $this->resource) === true, fn () => $this->results),
        ];
    }
}
