<?php

namespace App\Concerns;

use App\Enums\ServiceRequestCategory;
use App\Enums\ServiceRequestPriority;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ServiceRequestValidationRules
{
    public const int MAX_PHOTO_KILOBYTES = 8 * 1024;

    public const int MAX_PHOTOS = 6;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function serviceRequestRules(Community $community): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(ServiceRequestCategory::class)],
            'priority' => ['required', Rule::enum(ServiceRequestPriority::class)],
            'unit_id' => [
                'nullable', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'entry_permission' => ['boolean'],
            'photos' => ['array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => ['image', 'max:'.self::MAX_PHOTO_KILOBYTES],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function serviceRequestCommentRules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }
}
