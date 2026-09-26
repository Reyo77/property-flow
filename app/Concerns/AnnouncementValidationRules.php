<?php

namespace App\Concerns;

use App\Enums\AnnouncementAudience;
use App\Enums\ResidencyType;
use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AnnouncementValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function announcementRules(Community $community, string $audienceType): array
    {
        $audience = AnnouncementAudience::tryFrom($audienceType);

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'audience_type' => ['required', Rule::enum(AnnouncementAudience::class)],
            'residency_type' => [
                $audience === AnnouncementAudience::ResidencyType ? 'required' : 'nullable',
                Rule::enum(ResidencyType::class),
            ],
            'building_ids' => [$audience === AnnouncementAudience::Buildings ? 'required' : 'array', 'array'],
            'building_ids.*' => [Rule::exists(Building::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'unit_ids' => [$audience === AnnouncementAudience::Units ? 'required' : 'array', 'array'],
            'unit_ids.*' => [Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed()],
            'publish_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ];
    }
}
