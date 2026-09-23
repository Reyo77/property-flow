<?php

namespace App\Concerns;

use App\Models\Building;
use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

trait UnitValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function unitRules(Community $community, ?int $buildingId, ?int $unitId = null): array
    {
        return [
            'building_id' => [
                'nullable', 'integer',
                Rule::exists(Building::class, 'id')
                    ->where('community_id', $community->id)
                    ->withoutTrashed(),
            ],
            'number' => [
                'required', 'string', 'max:50',
                Rule::unique(Unit::class)
                    ->where('community_id', $community->id)
                    ->where(fn (Builder $query) => $buildingId === null
                        ? $query->whereNull('building_id')
                        : $query->where('building_id', $buildingId))
                    ->withoutTrashed()
                    ->ignore($unitId),
            ],
            ...$this->unitDetailRules(),
        ];
    }

    /**
     * Rules for the unit attributes that do not depend on other records.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function unitDetailRules(): array
    {
        return [
            'floor' => ['nullable', 'integer', 'min:-10', 'max:300'],
            'area' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'unit_factor' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,6'],
            'parking' => ['nullable', 'string', 'max:255'],
            'locker' => ['nullable', 'string', 'max:255'],
        ];
    }
}
