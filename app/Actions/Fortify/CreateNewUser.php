<?php

namespace App\Actions\Fortify;

use App\Actions\Companies\RegisterCompany;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private readonly RegisterCompany $registerCompany) {}

    /**
     * Validate and register a new company with the user as its first admin.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'company_name' => ['required', 'string', 'max:255'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return $this->registerCompany->handle(
            companyName: $input['company_name'],
            name: $input['name'],
            email: $input['email'],
            password: $input['password'],
        );
    }
}
