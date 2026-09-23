<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait VendorValidationRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function vendorRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'trade' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
