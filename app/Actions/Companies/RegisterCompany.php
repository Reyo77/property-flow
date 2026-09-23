<?php

namespace App\Actions\Companies;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterCompany
{
    public function __construct(
        private readonly SyncDefaultRoles $syncDefaultRoles,
        private readonly AssignCompanyRole $assignCompanyRole,
    ) {}

    /**
     * Create a company with its default roles and make the given person its first admin.
     */
    public function handle(string $companyName, string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($companyName, $name, $email, $password): User {
            $company = Company::create(['name' => $companyName]);

            $this->syncDefaultRoles->handle($company);

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->company()->associate($company);
            $user->save();

            $this->assignCompanyRole->handle($user, CompanyRole::CompanyAdmin);

            return $user;
        });
    }
}
