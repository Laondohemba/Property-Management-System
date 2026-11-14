<?php

namespace Tests\Feature\Property;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * GET /bff/session: assigned properties, active property and its permissions (spec §8.2).
 */
class SessionPropertiesTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_it_lists_assigned_properties_with_the_role_at_each(): void
    {
        $alpha = $this->property(['name' => 'Alpha']);
        $beta = $this->property(['name' => 'Beta']);
        $this->property(['name' => 'Gamma']);

        $user = $this->staff($alpha, Role::FrontDeskAgent);
        $this->assign($user, $beta, Role::Accountant);

        $this->actingAs($user)
            ->getJson('/bff/session')
            ->assertOk()
            ->assertJsonCount(2, 'data.properties')
            ->assertJsonPath('data.properties.0.name', 'Alpha')
            ->assertJsonPath('data.properties.0.role.label', 'Front Desk Agent')
            ->assertJsonPath('data.properties.1.role.label', 'Accountant / Finance')
            ->assertJsonPath('data.is_super_admin', false);
    }

    public function test_it_defaults_to_the_first_property_and_returns_its_permissions(): void
    {
        $property = $this->property(['name' => 'Alpha']);

        $response = $this->actingAs($this->staff($property, Role::HousekeepingAttendant))
            ->getJson('/bff/session')
            ->assertOk()
            ->assertJsonPath('data.active_property.id', $property->public_id);

        $this->assertEqualsCanonicalizing(['housekeeping.update', 'housekeeping.view'], $response->json('data.permissions'));
    }

    public function test_the_header_selects_the_active_property(): void
    {
        $alpha = $this->property(['name' => 'Alpha']);
        $beta = $this->property(['name' => 'Beta']);
        $user = $this->staff($alpha, Role::FrontDeskAgent);
        $this->assign($user, $beta, Role::RevenueManager);

        $response = $this->actingAs($user)
            ->withHeader('X-Property-Id', $beta->public_id)
            ->getJson('/bff/session')
            ->assertJsonPath('data.active_property.id', $beta->public_id);

        $this->assertContains(Permission::RatesManage->value, $response->json('data.permissions'));
        $this->assertNotContains(Permission::FrontdeskCheckin->value, $response->json('data.permissions'));
    }

    public function test_an_invalid_header_falls_back_instead_of_failing(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff($property))
            ->withHeader('X-Property-Id', '01INVALIDPROPERTYID000000')
            ->getJson('/bff/session')
            ->assertOk()
            ->assertJsonPath('data.active_property.id', $property->public_id);
    }

    public function test_users_without_properties_have_no_active_property(): void
    {
        $this->actingAs($this->staff())
            ->getJson('/bff/session')
            ->assertOk()
            ->assertJsonPath('data.properties', [])
            ->assertJsonPath('data.active_property', null)
            ->assertJsonPath('data.permissions', []);
    }

    public function test_super_admins_see_every_active_property_and_all_permissions(): void
    {
        $this->property(['name' => 'Alpha']);
        $this->property(['name' => 'Closed', 'is_active' => false]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson('/bff/session')
            ->assertJsonCount(1, 'data.properties')
            ->assertJsonPath('data.properties.0.role.name', 'super-admin')
            ->assertJsonPath('data.is_super_admin', true);

        $this->assertCount(count(Permission::cases()), $response->json('data.permissions'));
    }
}
