<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueAccessTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => ['description' => 'The account email.', 'example' => 'resident@propertyflow.test'],
            'password' => ['description' => 'The account password.', 'example' => 'password'],
            'device_name' => ['description' => 'A name for this device, shown when listing and revoking tokens.', 'example' => 'Rita\'s iPhone'],
            'code' => ['description' => 'Required when the account has two-factor authentication: the 6-digit code from the authenticator app.', 'example' => null],
            'recovery_code' => ['description' => 'Instead of `code`: one of the account\'s two-factor recovery codes (each works once).', 'example' => null],
        ];
    }
}
