<?php

namespace App\Concerns;

use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ParkingPermitValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function parkingPermitRules(Community $community): array
    {
        return [
            'unit_id' => [
                'required', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'plate_number' => ['required', 'string', 'max:20'],
            'visitor_name' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
