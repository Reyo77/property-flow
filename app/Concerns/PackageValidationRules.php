<?php

namespace App\Concerns;

use App\Models\Community;
use App\Models\Resident;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PackageValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function packageRules(Community $community): array
    {
        return [
            'unit_id' => [
                'nullable', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'resident_id' => [
                'nullable', 'integer',
                Rule::exists(Resident::class, 'id')->where('company_id', $community->company_id)->withoutTrashed(),
            ],
            'carrier' => ['required', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function packageReleaseRules(): array
    {
        return [
            'released_to_name' => ['required', 'string', 'max:255'],
        ];
    }
}
