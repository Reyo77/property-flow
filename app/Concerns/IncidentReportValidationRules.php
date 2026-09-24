<?php

namespace App\Concerns;

use App\Enums\IncidentSeverity;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait IncidentReportValidationRules
{
    public const int MAX_PHOTO_KILOBYTES = 8 * 1024;

    public const int MAX_PHOTOS = 6;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function incidentReportRules(Community $community): array
    {
        return [
            'unit_id' => [
                'nullable', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
            'occurred_at' => ['required', 'date'],
            'photos' => ['array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'max:'.self::MAX_PHOTO_KILOBYTES],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function incidentReportResolutionRules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
