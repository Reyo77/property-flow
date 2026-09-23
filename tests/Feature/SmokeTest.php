<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_auth_links(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('PropertyFlow')
            ->assertSee(route('login'))
            ->assertSee(route('register'))
            ->assertDontSee(route('dashboard'));
    }

    public function test_authenticated_users_see_a_dashboard_link_on_the_landing_page(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee(route('dashboard'))
            ->assertDontSee(route('login'));
    }

    public function test_the_dashboard_shows_the_community_switcher(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('data-test="community-switcher"', escape: false)
            ->assertSee('No community selected');
    }

    public function test_the_login_page_does_not_offer_password_reset_while_email_is_disabled(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Forgot your password?');

        $this->get('/forgot-password')->assertNotFound();
    }
}
