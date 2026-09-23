<?php

namespace App\Concerns;

use App\Models\Building;
use App\Models\Community;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait BuildingValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function buildingRules(Community $community, ?int $buildingId = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique(Building::class)
                    ->where('community_id', $community->id)
                    ->withoutTrashed()
                    ->ignore($buildingId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'floors' => ['nullable', 'integer', 'min:1', 'max:300'],
        ];
    }
}
