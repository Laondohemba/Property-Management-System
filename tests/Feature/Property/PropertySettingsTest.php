<?php

namespace Tests\Feature\Property;

use App\Modules\Identity\Enums\Role;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Property setup under /bff/settings/properties (SYS-03).
 */
class PropertySettingsTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private array $valid = [
        'name' => 'Harbour View Hotel',
        'code' => 'hvh',
        'timezone' => 'Africa/Lagos',
        'currency' => 'ngn',
        'check_in_time' => '14:00',
        'check_out_time' => '11:00',
        'city' => 'Lagos',
        'country' => 'ng',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_super_admins_can_create_a_property(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->postJson('/bff/settings/properties', $this->valid)
            ->assertCreated()
            ->assertJsonPath('data.code', 'HVH')
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.country', 'NG')
            ->assertJsonPath('data.check_out_time', '11:00')
            ->assertJsonPath('data.business_date', now('Africa/Lagos')->toDateString());

        $property = Property::withoutGlobalScopes()->firstOrFail();
        $this->assertSame($this->organization->id, $property->organization_id);
        $this->assertSame($admin->id, $property->created_by);
    }

    public function test_staff_cannot_create_properties(): void
    {
        $manager = $this->staff($this->property(), Role::PropertyManager);

        $this->actingAs($manager)->postJson('/bff/settings/properties', $this->valid)->assertForbidden();
    }

    public function test_validation_errors_are_returned_per_field(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/bff/settings/properties', [...$this->valid, 'timezone' => 'Mars/Base', 'currency' => 'NAIRA', 'check_in_time' => '2pm'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonFragment(['field' => 'timezone'])
            ->assertJsonFragment(['field' => 'currency'])
            ->assertJsonFragment(['field' => 'check_in_time']);
    }

    public function test_codes_are_unique_within_an_organization_only(): void
    {
        $this->property(['code' => 'HVH']);
        Property::factory()->for(Organization::factory())->create(['code' => 'XYZ']);

        $admin = $this->superAdmin();

        $this->actingAs($admin)->postJson('/bff/settings/properties', $this->valid)
            ->assertStatus(422)->assertJsonPath('errors.0.field', 'code');

        $this->actingAs($admin)->postJson('/bff/settings/properties', [...$this->valid, 'code' => 'XYZ'])->assertCreated();
    }

    public function test_property_managers_can_update_their_property_but_not_its_business_date(): void
    {
        $property = $this->property(['name' => 'Old Name']);
        $businessDate = $property->business_date->toDateString();
        $manager = $this->staff($property, Role::PropertyManager);

        $this->actingAs($manager)
            ->patchJson("/bff/settings/properties/{$property->public_id}", ['name' => 'New Name', 'business_date' => '2030-01-01'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.business_date', $businessDate);

        $this->assertSame($manager->id, $property->refresh()->updated_by);
    }

    public function test_roles_without_settings_permission_cannot_update(): void
    {
        $property = $this->property();

        $this->actingAs($this->staff($property, Role::FrontDeskAgent))
            ->patchJson("/bff/settings/properties/{$property->public_id}", ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_managers_only_list_properties_they_manage(): void
    {
        $managed = $this->property(['name' => 'Managed']);
        $staffed = $this->property(['name' => 'Front desk only']);
        $this->property(['name' => 'Unassigned']);

        $user = $this->staff($managed, Role::PropertyManager);
        $this->assign($user, $staffed, Role::FrontDeskAgent);

        $this->actingAs($user)
            ->getJson('/bff/settings/properties')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Managed')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_super_admins_list_every_property_in_their_organization(): void
    {
        $this->property(['name' => 'B Hotel']);
        $this->property(['name' => 'A Hotel']);
        Property::factory()->for(Organization::factory())->create(['name' => 'Foreign Hotel']);

        $this->actingAs($this->superAdmin())
            ->getJson('/bff/settings/properties?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'A Hotel')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_properties_of_another_organization_return_404(): void
    {
        $foreign = Property::factory()->for(Organization::factory())->create();

        $this->actingAs($this->superAdmin())
            ->getJson("/bff/settings/properties/{$foreign->public_id}")
            ->assertNotFound();
    }

    public function test_staff_without_settings_access_get_403_on_the_list(): void
    {
        $this->actingAs($this->staff($this->property(), Role::FrontDeskAgent))
            ->getJson('/bff/settings/properties')
            ->assertForbidden();
    }
}
