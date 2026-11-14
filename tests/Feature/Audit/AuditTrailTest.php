<?php

namespace Tests\Feature\Audit;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Audit trail recording and tamper evidence (spec §5.12: AUD-01, AUD-02, AUD-04).
 */
class AuditTrailTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property(['name' => 'Alpha Hotel']);
    }

    public function test_data_changes_record_who_what_when_where_with_before_and_after_values(): void
    {
        $admin = $this->superAdmin();
        $room = Room::factory()->for(RoomType::factory()->for($this->hotel))->create(['number' => '101', 'floor' => '1']);

        $this->actingAs($admin)
            ->withHeaders(['X-Property-Id' => $this->hotel->public_id, 'User-Agent' => 'PMS-Test/1.0'])
            ->patchJson("/bff/settings/rooms/{$room->public_id}", ['floor' => '2'])
            ->assertOk();

        $entry = AuditEntry::where('subject_type', $room->getMorphClass())->where('subject_id', $room->id)->where('event', 'updated')->firstOrFail();

        $this->assertSame('data', $entry->log_name);
        $this->assertSame('Room 101 updated', $entry->description);
        $this->assertTrue($entry->causer->is($admin));
        $this->assertSame($this->hotel->id, $entry->property_id);
        $this->assertSame($this->organization->id, $entry->organization_id);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertSame('PMS-Test/1.0', $entry->user_agent);
        $this->assertSame(['floor' => '1'], $entry->properties['old']);
        $this->assertSame(['floor' => '2'], $entry->properties['attributes']);
    }

    public function test_credentials_and_secrets_are_never_recorded(): void
    {
        $user = $this->staff($this->hotel);

        $this->actingAs($user)->putJson('/user/password', [
            'current_password' => 'password',
            'password' => 'N3w-Secure-Passw0rd!',
            'password_confirmation' => 'N3w-Secure-Passw0rd!',
        ])->assertOk();

        $this->assertDatabaseHas('activity_log', ['event' => 'password_changed', 'causer_id' => $user->id]);

        foreach (AuditEntry::all() as $entry) {
            $json = json_encode($entry->properties);
            $this->assertStringNotContainsString('password', strtolower((string) $json), "Entry '{$entry->description}' leaks a password field.");
            $this->assertStringNotContainsString('two_factor_secret', (string) $json);
        }
    }

    public function test_sign_ins_and_failures_are_security_events(): void
    {
        $user = $this->staff($this->hotel);

        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password']);
        $this->postJson('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $failed = AuditEntry::where('event', 'login_failed')->orderBy('id')->get();
        $this->assertCount(2, $failed);
        $this->assertSame('security', $failed[0]->log_name);
        $this->assertSame($user->email, $failed[0]->properties['email']);
        $this->assertSame($this->organization->id, $failed[0]->organization_id);
        $this->assertNull($failed[1]->organization_id, 'Unknown e-mails go to the global chain.');

        $login = AuditEntry::where('event', 'login')->firstOrFail();
        $this->assertTrue($login->causer->is($user));
    }

    public function test_permission_changes_are_security_events(): void
    {
        $admin = $this->superAdmin();
        $user = $this->staff($this->hotel, Role::FrontDeskAgent);

        $this->actingAs($admin)
            ->putJson("/bff/users/{$user->public_id}/properties/{$this->hotel->public_id}", ['role' => 'accountant'])
            ->assertOk();

        $entry = AuditEntry::where('event', 'role_changed')->firstOrFail();
        $this->assertSame('security', $entry->log_name);
        $this->assertSame($this->hotel->id, $entry->property_id);
        $this->assertSame(['previous_role' => 'front-desk-agent', 'role' => 'accountant'], collect($entry->properties)->sortKeys()->all());

        $this->actingAs($admin)
            ->patchJson('/bff/roles/front-desk-agent', ['requires_two_factor' => true])
            ->assertOk();

        $roleEntry = AuditEntry::where('event', 'role_updated')->firstOrFail();
        $this->assertFalse($roleEntry->properties['old']['requires_two_factor']);
        $this->assertTrue($roleEntry->properties['attributes']['requires_two_factor']);
    }

    public function test_the_trail_is_append_only(): void
    {
        $this->superAdmin();
        $entry = AuditEntry::firstOrFail();

        try {
            DB::table('activity_log')->where('id', $entry->id)->update(['description' => 'tampered']);
            $this->fail('Updating an audit entry must be rejected.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->expectException(QueryException::class);
        DB::table('activity_log')->where('id', $entry->id)->delete();
    }

    public function test_entries_are_hash_chained_and_verifiable(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->postJson('/bff/settings/properties', [
            'name' => 'Beta Hotel', 'code' => 'BETA', 'timezone' => 'Africa/Lagos', 'currency' => 'NGN',
            'check_in_time' => '14:00', 'check_out_time' => '12:00',
        ])->assertCreated();

        $entries = AuditEntry::where('organization_id', $this->organization->id)->orderBy('id')->get();
        $this->assertGreaterThan(2, $entries->count());

        foreach ($entries as $index => $entry) {
            $this->assertSame($index === 0 ? null : $entries[$index - 1]->hash, $entry->prev_hash);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $entry->hash);
        }

        $this->artisan('audit:verify')->assertSuccessful();
    }

    public function test_verification_detects_a_forged_entry(): void
    {
        $this->superAdmin();
        Room::factory()->for(RoomType::factory()->for($this->hotel))->create();
        $this->artisan('audit:verify')->assertSuccessful();

        // Someone with database access appends an entry without a valid hash. Inserts are possible
        // (only UPDATE and DELETE are blocked), but they cannot produce a hash that fits the chain.
        $last = AuditEntry::where('organization_id', $this->organization->id)->orderByDesc('id')->firstOrFail();
        DB::table('activity_log')->insert([
            'log_name' => 'data',
            'description' => 'Room quietly deleted',
            'organization_id' => $this->organization->id,
            'prev_hash' => $last->hash,
            'hash' => str_repeat('0', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('audit:verify')->assertFailed();
    }

    public function test_changes_without_effect_are_not_recorded(): void
    {
        $room = Room::factory()->for(RoomType::factory()->for($this->hotel))->create(['floor' => '1']);
        $before = AuditEntry::count();

        $room->update(['floor' => '1']);
        User::factory()->for($this->organization)->create()->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->assertSame($before + 1, AuditEntry::count(), 'Only the new user is recorded; a no-op update is not.');
    }
}
