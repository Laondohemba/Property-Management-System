<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\StaffInvitation;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * /bff/users (SYS-01).
 */
class StaffAdministrationTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotelA;

    private Property $hotelB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotelA = $this->property(['name' => 'Alpha Hotel']);
        $this->hotelB = $this->property(['name' => 'Beta Hotel']);
        Notification::fake();
    }

    public function test_inviting_a_new_person_creates_a_pending_account_and_sends_an_email(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/bff/users', ['name' => 'Ngozi Okafor', 'email' => 'Ngozi@Example.com', 'property_id' => $this->hotelA->public_id, 'role' => 'front-desk-agent'])
            ->assertCreated()
            ->assertJsonPath('meta.invited', true)
            ->assertJsonPath('data.email', 'ngozi@example.com')
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.assignments.0.property.name', 'Alpha Hotel')
            ->assertJsonPath('data.assignments.0.role.label', 'Front Desk Agent');

        $user = User::where('email', 'ngozi@example.com')->firstOrFail();
        $this->assertSame($this->organization->id, $user->organization_id);

        Notification::assertSentTo($user, StaffInvitation::class, fn (StaffInvitation $notification): bool => str_contains($notification->url($user), '/invitation/'));
    }

    public function test_inviting_an_existing_colleague_adds_access_without_an_email(): void
    {
        $colleague = $this->staff($this->hotelA, Role::FrontDeskAgent);

        $this->actingAs($this->superAdmin())
            ->postJson('/bff/users', ['name' => 'Ignored', 'email' => $colleague->email, 'property_id' => $this->hotelB->public_id, 'role' => 'accountant'])
            ->assertCreated()
            ->assertJsonPath('meta.invited', false)
            ->assertJsonCount(2, 'data.assignments');

        Notification::assertNothingSent();
    }

    public function test_emails_of_other_organisations_cannot_be_invited(): void
    {
        $outsider = User::factory()->for(Organization::factory())->create();

        $this->actingAs($this->superAdmin())
            ->postJson('/bff/users', ['name' => 'X', 'email' => $outsider->email, 'property_id' => $this->hotelA->public_id, 'role' => 'auditor'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'email');
    }

    public function test_super_admin_cannot_be_assigned_as_a_property_role(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/bff/users', ['name' => 'X', 'email' => 'x@example.com', 'property_id' => $this->hotelA->public_id, 'role' => 'super-admin'])
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'role');
    }

    public function test_property_managers_invite_only_to_properties_they_manage(): void
    {
        $manager = $this->staff($this->hotelA, Role::PropertyManager);

        $this->actingAs($manager)
            ->postJson('/bff/users', ['name' => 'A', 'email' => 'a@example.com', 'property_id' => $this->hotelA->public_id, 'role' => 'front-desk-agent'])
            ->assertCreated();

        $this->actingAs($manager)
            ->postJson('/bff/users', ['name' => 'B', 'email' => 'b@example.com', 'property_id' => $this->hotelB->public_id, 'role' => 'front-desk-agent'])
            ->assertForbidden();
    }

    public function test_property_managers_list_only_their_staff_and_assignments(): void
    {
        $manager = $this->staff($this->hotelA, Role::PropertyManager);
        $shared = $this->staff($this->hotelA, Role::FrontDeskAgent);
        $this->assign($shared, $this->hotelB, Role::Accountant);
        $this->staff($this->hotelB, Role::Auditor);

        $response = $this->actingAs($manager)->getJson('/bff/users')->assertOk();

        $this->assertEqualsCanonicalizing([$manager->email, $shared->email], array_column($response->json('data'), 'email'));
        $sharedRow = collect($response->json('data'))->firstWhere('email', $shared->email);
        $this->assertCount(1, $sharedRow['assignments'], 'Assignments at properties the manager does not manage are hidden.');
        $this->assertFalse($response->json('meta.can_manage_accounts'));
        $this->assertSame(['Alpha Hotel'], array_column($response->json('meta.properties'), 'name'));
    }

    public function test_staff_without_users_manage_are_forbidden(): void
    {
        $this->actingAs($this->staff($this->hotelA, Role::FrontDeskAgent))->getJson('/bff/users')->assertForbidden();
    }

    public function test_roles_at_a_property_can_be_changed_and_removed(): void
    {
        $user = $this->staff($this->hotelA, Role::FrontDeskAgent);
        $manager = $this->staff($this->hotelA, Role::PropertyManager);

        $this->actingAs($manager)
            ->putJson("/bff/users/{$user->public_id}/properties/{$this->hotelA->public_id}", ['role' => 'revenue-manager'])
            ->assertOk()
            ->assertJsonPath('data.assignments.0.role.name', 'revenue-manager');

        $this->actingAs($manager)
            ->deleteJson("/bff/users/{$user->public_id}/properties/{$this->hotelA->public_id}")
            ->assertOk()
            ->assertJsonPath('data.assignments', []);
    }

    public function test_managers_cannot_change_access_at_other_properties(): void
    {
        $user = $this->staff($this->hotelA, Role::FrontDeskAgent);
        $this->assign($user, $this->hotelB, Role::FrontDeskAgent);

        $this->actingAs($this->staff($this->hotelA, Role::PropertyManager))
            ->deleteJson("/bff/users/{$user->public_id}/properties/{$this->hotelB->public_id}")
            ->assertForbidden();
    }

    public function test_only_super_admins_change_account_settings(): void
    {
        $user = $this->staff($this->hotelA, Role::FrontDeskAgent);

        $this->actingAs($this->staff($this->hotelA, Role::PropertyManager))
            ->patchJson("/bff/users/{$user->public_id}", ['is_active' => false])
            ->assertForbidden();

        $this->actingAs($this->superAdmin())
            ->patchJson("/bff/users/{$user->public_id}", ['is_active' => false, 'is_super_admin' => true])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.is_super_admin', true);
    }

    public function test_super_admins_cannot_lock_themselves_out(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->patchJson("/bff/users/{$admin->public_id}", ['is_active' => false])->assertStatus(422);
        $this->actingAs($admin)->patchJson("/bff/users/{$admin->public_id}", ['is_super_admin' => false])->assertStatus(422);
    }

    public function test_invitations_can_be_resent_until_accepted(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->postJson('/bff/users', ['name' => 'P', 'email' => 'p@example.com', 'property_id' => $this->hotelA->public_id, 'role' => 'auditor']);
        $invitee = User::where('email', 'p@example.com')->firstOrFail();

        $this->actingAs($admin)->postJson("/bff/users/{$invitee->public_id}/invitation")->assertNoContent();
        Notification::assertSentToTimes($invitee, StaffInvitation::class, 2);

        $invitee->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin)->postJson("/bff/users/{$invitee->public_id}/invitation")->assertStatus(422);
    }

    public function test_users_of_other_organisations_return_404(): void
    {
        $outsider = User::factory()->for(Organization::factory())->create();

        $this->actingAs($this->superAdmin())->getJson("/bff/users/{$outsider->public_id}")->assertNotFound();
    }

    public function test_the_list_can_be_filtered(): void
    {
        $this->staff($this->hotelA, Role::FrontDeskAgent)->update(['name' => 'Chidi Front']);
        $this->staff($this->hotelB, Role::Auditor)->update(['name' => 'Bola Audit', 'is_active' => false]);

        $admin = $this->superAdmin();

        $this->actingAs($admin)->getJson('/bff/users?filter[search]=chidi')->assertJsonPath('meta.total', 1);
        $this->actingAs($admin)->getJson("/bff/users?filter[property]={$this->hotelB->public_id}")->assertJsonPath('data.0.name', 'Bola Audit');
        $this->actingAs($admin)->getJson('/bff/users?filter[status]=inactive')->assertJsonPath('meta.total', 1);
    }
}
