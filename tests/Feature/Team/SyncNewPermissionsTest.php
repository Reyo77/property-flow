<?php

use App\Enums\CompanyRole;
use App\Enums\Permission as PermissionName;
use App\Models\Company;
use App\Support\Tenancy\CompanyRoles;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\artisan;

it('grants permissions added by a release to existing companies\' default roles', function () {
    $company = Company::factory()->create();
    Permission::findByName(PermissionName::ManageGovernance->value)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    artisan('permissions:sync-new')->expectsOutputToContain('1 new permission(s): governance.manage')->assertSuccessful();

    expect(CompanyRoles::find($company->id, CompanyRole::BoardMember->value)?->hasPermissionTo(PermissionName::ManageGovernance->value))->toBeTrue()
        ->and(CompanyRoles::find($company->id, CompanyRole::Staff->value)?->hasPermissionTo(PermissionName::ManageGovernance->value))->toBeFalse();
});

it('leaves a permission a company removed from a role removed', function () {
    $company = Company::factory()->create();
    $board = CompanyRoles::find($company->id, CompanyRole::BoardMember->value);
    $board?->revokePermissionTo(PermissionName::ManageGovernance->value);

    artisan('permissions:sync-new')->expectsOutputToContain('0 new permission(s).')->assertSuccessful();

    expect($board?->fresh()?->hasPermissionTo(PermissionName::ManageGovernance->value))->toBeFalse();
});
