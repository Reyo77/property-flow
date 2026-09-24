<?php

namespace App\Concerns;

use App\Models\Community;
use App\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AccessKeyValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accessKeyRules(Community $community): array
    {
        return [
            'unit_id' => [
                'nullable', 'integer',
                Rule::exists(Unit::class, 'id')->where('community_id', $community->id)->withoutTrashed(),
            ],
            'label' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function accessKeySignoutRules(): array
    {
        return [
            'signed_out_to' => ['required', 'string', 'max:255'],
            'signed_out_to_phone' => ['nullable', 'string', 'max:50'],
            'due_back_at' => ['nullable', 'date'],
        ];
    }
}
