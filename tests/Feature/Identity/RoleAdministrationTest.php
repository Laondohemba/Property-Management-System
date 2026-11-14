<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Permission;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * /bff/roles: custom roles and the permission matrix (SYS-02).
 */
class RoleAdministrationTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private User $admin;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->admin = $this->superAdmin();
        $this->hotel = $this->property();
    }

    public function test_it_lists_built_in_roles_with_the_permission_catalogue(): void
    {
        $this->staff($this->hotel, RoleEnum::FrontDeskAgent);

        $response = $this->actingAs($this->admin)->getJson('/bff/roles')->assertOk();

        $this->assertCount(count(RoleEnum::cases()), $response->json('data'));
        $frontDesk = collect($response->json('data'))->firstWhere('name', 'front-desk-agent');
        $this->assertTrue($frontDesk['is_system']);
        $this->assertSame(1, $frontDesk['users_count']);
        $this->assertContains('frontdesk.checkin', $frontDesk['permissions']);
        $this->assertSame(1, collect($response->json('data'))->firstWhere('name', 'super-admin')['users_count']);
        $this->assertSame('dashboard', $response->json('meta.permission_groups.0.group'));
    }

    public function test_a_custom_role_can_be_created_and_assigned(): void
    {
        $name = $this->actingAs($this->admin)
            ->postJson('/bff/roles', ['label' => 'Night Auditor', 'permissions' => ['audit.run', 'reports.view']])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Night Auditor')
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.permissions', ['audit.run', 'reports.view'])
            ->json('data.name');

        $user = $this->staff();
        $this->actingAs($this->admin)
            ->putJson("/bff/users/{$user->public_id}/properties/{$this->hotel->public_id}", ['role' => $name])
            ->assertOk()
            ->assertJsonPath('data.assignments.0.role.label', 'Night Auditor');

        $this->assertTrue(User::find($user->id)->hasPropertyPermission($this->hotel, Permission::NightAuditRun));
        $this->assertFalse(User::find($user->id)->hasPropertyPermission($this->hotel, Permission::DashboardView));
    }

    public function test_custom_role_permissions_can_be_changed(): void
    {
        $role = $this->customRole('Concierge', ['guests.view']);
        $user = $this->staff();
        $this->hotel->users()->attach($user->id, ['role_id' => $role->id]);

        $this->actingAs($this->admin)
            ->patchJson("/bff/roles/{$role->name}", ['permissions' => ['guests.view', 'guests.update']])
            ->assertOk();

        $this->assertTrue(User::find($user->id)->hasPropertyPermission($this->hotel, Permission::GuestsUpdate));
    }

    public function test_built_in_roles_are_read_only_except_two_factor(): void
    {
        $this->actingAs($this->admin)
            ->patchJson('/bff/roles/front-desk-agent', ['permissions' => ['dashboard.view']])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->patchJson('/bff/roles/front-desk-agent', ['requires_two_factor' => true])
            ->assertOk()
            ->assertJsonPath('data.requires_two_factor', true);

        $this->assertSame(['front-desk-agent'], $this->organization->refresh()->twoFactorRoles());
    }

    public function test_labels_must_be_unique_and_not_clash_with_built_in_roles(): void
    {
        $this->customRole('Concierge');

        $this->actingAs($this->admin)->postJson('/bff/roles', ['label' => 'Concierge'])->assertStatus(422);
        $this->actingAs($this->admin)->postJson('/bff/roles', ['label' => 'Front Desk Agent'])->assertStatus(422);
        $this->actingAs($this->admin)->postJson('/bff/roles', ['label' => 'Bad', 'permissions' => ['nope.never']])->assertStatus(422);
    }

    public function test_custom_roles_of_other_organisations_are_invisible(): void
    {
        $foreign = Role::create(['name' => 'custom-foreign', 'guard_name' => 'web', 'organization_id' => Organization::factory()->create()->id, 'label' => 'Foreign']);

        $this->actingAs($this->admin)->getJson("/bff/roles/{$foreign->name}")->assertNotFound();
        $this->assertNotContains('custom-foreign', array_column($this->actingAs($this->admin)->getJson('/bff/roles')->json('data'), 'name'));
    }

    public function test_assigned_custom_roles_cannot_be_deleted(): void
    {
        $role = $this->customRole('Concierge');
        $this->hotel->users()->attach($this->staff()->id, ['role_id' => $role->id]);

        $this->actingAs($this->admin)->deleteJson("/bff/roles/{$role->name}")->assertStatus(409)->assertJsonPath('code', 'conflict');

        $this->hotel->users()->detach();
        $this->actingAs($this->admin)->deleteJson("/bff/roles/{$role->name}")->assertNoContent();
        $this->actingAs($this->admin)->deleteJson('/bff/roles/auditor')->assertStatus(409);
    }

    public function test_only_super_admins_manage_roles(): void
    {
        $this->actingAs($this->staff($this->hotel, RoleEnum::PropertyManager))->getJson('/bff/roles')->assertForbidden();
    }

    private function customRole(string $label, array $permissions = []): Role
    {
        $role = Role::create(['name' => 'custom-'.strtolower($label), 'guard_name' => 'web', 'organization_id' => $this->organization->id, 'label' => $label]);
        $role->syncPermissions($permissions);

        return $role;
    }
}
