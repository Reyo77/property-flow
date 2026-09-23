<?php

namespace Tests\Feature\Auth;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\PermissionTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post(route('register.store'), [
            'company_name' => 'Maple Property Management',
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_registration_creates_the_company_with_the_user_as_its_admin(): void
    {
        $this->post(route('register.store'), [
            'company_name' => 'Maple Property Management',
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $company = Company::sole();
        $user = User::sole();

        $this->assertSame('Maple Property Management', $company->name);
        $this->assertSame($company->id, $user->company_id);
        $this->assertTrue(PermissionTeam::run($company->id, fn () => $user->hasRole(CompanyRole::CompanyAdmin->value)));
    }

    public function test_registration_requires_a_company_name(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['company_name' => 'The company name field is required.']);

        $this->assertGuest();
        $this->assertDatabaseCount('companies', 0);
    }
}
