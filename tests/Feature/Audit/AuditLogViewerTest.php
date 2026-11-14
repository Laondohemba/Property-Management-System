<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Audit;
use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * GET /bff/audit-logs (AUD-03).
 */
class AuditLogViewerTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $alpha;

    private Property $beta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->alpha = $this->property(['name' => 'Alpha Hotel']);
        $this->beta = $this->property(['name' => 'Beta Hotel']);

        Room::factory()->for(RoomType::factory()->for($this->alpha))->create(['number' => 'A1']);
        Room::factory()->for(RoomType::factory()->for($this->beta))->create(['number' => 'B1']);
    }

    public function test_super_admins_see_the_whole_organisation_newest_first(): void
    {
        $admin = $this->superAdmin();
        Audit::security('login', 'Signed in', $admin, [], $admin);

        $response = $this->actingAs($admin)->getJson('/bff/audit-logs')->assertOk();

        $descriptions = array_column($response->json('data'), 'description');
        $this->assertSame('Signed in', $descriptions[0]);
        $this->assertContains('Room A1 created', $descriptions);
        $this->assertContains('Room B1 created', $descriptions);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $response->json('data.0.id'));
    }

    public function test_entries_carry_actor_subject_property_and_changes(): void
    {
        $admin = $this->superAdmin();

        $row = collect($this->actingAs($admin)->getJson('/bff/audit-logs?filter[search]=Room A1')->json('data'))->first();

        $this->assertSame('data', $row['log']);
        $this->assertSame('created', $row['event']);
        $this->assertSame(['type' => 'Room', 'id' => Room::where('number', 'A1')->value('public_id'), 'label' => 'Room A1'], $row['subject']);
        $this->assertSame('Alpha Hotel', $row['property']['name']);
        $this->assertNull($row['old']);
        $this->assertSame('A1', $row['new']['number']);
    }

    public function test_auditors_see_only_their_property_and_no_organisation_wide_events(): void
    {
        $auditor = $this->staff($this->alpha, Role::Auditor);
        Audit::security('super_admin_granted', 'Granted Super Admin', $auditor);

        $descriptions = array_column(
            $this->actingAs($auditor)->withHeader('X-Property-Id', $this->alpha->public_id)->getJson('/bff/audit-logs')->assertOk()->json('data'),
            'description',
        );

        $this->assertContains('Room A1 created', $descriptions);
        $this->assertNotContains('Room B1 created', $descriptions);
        $this->assertNotContains('Granted Super Admin', $descriptions);
    }

    public function test_filters_narrow_the_results(): void
    {
        $admin = $this->superAdmin();
        Audit::security('login', 'Signed in', $admin, [], $admin);

        $this->actingAs($admin)->getJson('/bff/audit-logs?filter[log]=security')->assertJsonPath('meta.total', 1);
        $this->actingAs($admin)->getJson("/bff/audit-logs?filter[property]={$this->beta->public_id}&filter[log]=data")
            ->assertJsonPath('data.0.property.name', 'Beta Hotel');
        $this->actingAs($admin)->getJson("/bff/audit-logs?filter[user]={$admin->public_id}")->assertJsonPath('meta.total', 1);
    }

    public function test_roles_without_audit_view_are_forbidden(): void
    {
        $this->actingAs($this->staff($this->alpha, Role::FrontDeskAgent))
            ->withHeader('X-Property-Id', $this->alpha->public_id)
            ->getJson('/bff/audit-logs')
            ->assertForbidden();
    }

    public function test_other_organisations_entries_are_never_visible(): void
    {
        $foreignProperty = Property::factory()->for(Organization::factory())->create();
        Room::factory()->for(RoomType::factory()->for($foreignProperty))->create(['number' => 'X9']);

        $descriptions = array_column($this->actingAs($this->superAdmin())->getJson('/bff/audit-logs?per_page=100')->json('data'), 'description');

        $this->assertNotContains('Room X9 created', $descriptions);
    }
}
