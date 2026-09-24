<?php

namespace App\Concerns;

use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait EntryAuthorizationValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function entryAuthorizationRules(Community $community): array
    {
        return [
            'unit_id' => [
                'required', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
