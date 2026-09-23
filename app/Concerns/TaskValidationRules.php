<?php

namespace App\Concerns;

use App\Models\Community;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait TaskValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function taskRules(Community $community): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['nullable', 'date'],
            'assigned_to_id' => [
                'nullable', 'integer',
                Rule::exists(User::class, 'id')->where('company_id', $community->company_id),
            ],
        ];
    }
}
