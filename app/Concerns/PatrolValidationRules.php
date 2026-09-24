<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait PatrolValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function patrolRouteRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function patrolCheckpointRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
