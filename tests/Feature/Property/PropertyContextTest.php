<?php

namespace Tests\Feature\Property;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * X-Property-Id validation and property-scoped permissions (spec §2.7, §13).
 */
class PropertyContextTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_property_scoped_endpoints_require_the_header(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff($property))
            ->getJson('/bff/dashboard')
            ->assertStatus(400)
            ->assertJsonPath('code', 'property_required');
    }

    public function test_assigned_staff_can_use_their_property(): void
    {
        $property = $this->property(['name' => 'Lagoon Hotel']);

        $this->actingAs($this->staff($property))
            ->withHeader('X-Property-Id', $property->public_id)
            ->getJson('/bff/dashboard')
            ->assertOk()
            ->assertJsonPath('data.property.name', 'Lagoon Hotel')
            ->assertJsonPath('data.property.business_date', $property->business_date->toDateString());
    }

    public function test_staff_cannot_use_a_property_they_are_not_assigned_to(): void
    {
        $mine = $this->property();
        $other = $this->property();

        $this->actingAs($this->staff($mine))
            ->withHeader('X-Property-Id', $other->public_id)
            ->getJson('/bff/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'property_forbidden');
    }

    public function test_properties_of_another_organization_are_never_accessible(): void
    {
        $foreign = Property::factory()->for(Organization::factory())->create();

        $this->actingAs($this->superAdmin())
            ->withHeader('X-Property-Id', $foreign->public_id)
            ->getJson('/bff/dashboard')
            ->assertForbidden();
    }

    public function test_inactive_properties_cannot_be_selected(): void
    {
        $property = $this->property(['is_active' => false]);

        $this->actingAs($this->superAdmin())
            ->withHeader('X-Property-Id', $property->public_id)
            ->getJson('/bff/dashboard')
            ->assertForbidden();
    }

    public function test_permissions_come_from_the_role_held_at_the_active_property(): void
    {
        $property = $this->property();
        $attendant = $this->staff($property, Role::HousekeepingAttendant);

        // Housekeeping attendants have no dashboard.view permission (spec §2.6).
        $this->actingAs($attendant)
            ->withHeader('X-Property-Id', $property->public_id)
            ->getJson('/bff/dashboard')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_a_user_can_hold_different_roles_at_different_properties(): void
    {
        $hotelA = $this->property();
        $hotelB = $this->property();
        $user = $this->staff($hotelA, Role::FrontDeskAgent);
        $this->assign($user, $hotelB, Role::HousekeepingAttendant);

        $this->actingAs($user)->withHeader('X-Property-Id', $hotelA->public_id)->getJson('/bff/dashboard')->assertOk();
        $this->actingAs(User::find($user->id))->withHeader('X-Property-Id', $hotelB->public_id)->getJson('/bff/dashboard')->assertForbidden();
    }
}
