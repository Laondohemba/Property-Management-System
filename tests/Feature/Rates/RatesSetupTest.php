<?php

namespace Tests\Feature\Rates;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use App\Modules\Rates\Models\RatePlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Taxes and fees (spec §2.8), policies (RTE-07) and rate plans with prices, taxes, rate audit
 * and the price preview (RTE-01, RTE-09).
 */
class RatesSetupTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    private User $manager;

    private RoomType $standard;

    private RoomType $deluxe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->hotel = $this->property(['currency' => 'NGN']);
        $this->manager = $this->staff($this->hotel, Role::PropertyManager);
        $this->standard = RoomType::factory()->for($this->hotel)->create(['code' => 'STD', 'name' => 'Standard', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3, 'sort_order' => 1]);
        $this->deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX', 'name' => 'Deluxe', 'base_occupancy' => 2, 'max_adults' => 3, 'max_children' => 2, 'max_occupancy' => 4, 'sort_order' => 2]);
    }

    // Taxes ------------------------------------------------------------------------------------

    public function test_taxes_can_be_created_listed_and_updated(): void
    {
        $id = $this->api()->postJson('/bff/settings/taxes', $this->taxPayload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'VAT')
            ->assertJsonPath('data.rate', '7.5')
            ->assertJsonPath('data.amount_minor', null)
            ->assertJsonPath('data.is_inclusive', true)
            ->json('data.id');

        $this->api()->getJson('/bff/settings/taxes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('meta.currency', 'NGN')
            ->assertJsonPath('meta.calculations.0.value', 'percentage');

        $this->api()->patchJson("/bff/settings/taxes/{$id}", $this->taxPayload(['calculation' => 'per_night', 'rate' => null, 'amount_minor' => 50000, 'is_inclusive' => false, 'name' => 'Levy', 'code' => 'LEVY']))
            ->assertOk()
            ->assertJsonPath('data.rate', null)
            ->assertJsonPath('data.amount_minor', 50000)
            ->assertJsonPath('data.calculation_label', 'Fixed amount per room night');
    }

    public function test_only_percentages_can_be_inclusive_and_values_must_match_the_calculation(): void
    {
        $this->api()->postJson('/bff/settings/taxes', $this->taxPayload(['calculation' => 'per_stay', 'rate' => null, 'amount_minor' => 1000, 'is_inclusive' => true]))
            ->assertUnprocessable()->assertJsonPath('errors.0.message', 'Only percentages can be included in the price.');

        $this->api()->postJson('/bff/settings/taxes', $this->taxPayload(['rate' => null]))
            ->assertUnprocessable()->assertJsonPath('errors.0.message', 'Enter the percentage.');

        $this->api()->postJson('/bff/settings/taxes', $this->taxPayload(['rate' => '101']))
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'rate');
    }

    public function test_tax_codes_are_unique_per_property(): void
    {
        $this->api()->postJson('/bff/settings/taxes', $this->taxPayload())->assertCreated();
        $this->api()->postJson('/bff/settings/taxes', $this->taxPayload(['code' => 'vat']))
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'code');
    }

    // Policies ---------------------------------------------------------------------------------

    public function test_cancellation_policies_keep_only_the_values_their_types_use_and_read_well(): void
    {
        $this->api()->postJson('/bff/settings/cancellation-policies', $this->cancellationPayload([
            'penalty_type' => 'nights', 'penalty_nights' => 1, 'penalty_percent' => '50',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.penalty_nights', 1)
            ->assertJsonPath('data.penalty_percent', null)
            ->assertJsonPath('data.summary', 'Free cancellation until 2 days before arrival. After that, cancelling costs the first night. A no-show costs 100% of the stay.');

        $this->api()->postJson('/bff/settings/cancellation-policies', $this->cancellationPayload(['name' => 'NRF', 'free_cancellation_hours' => null, 'penalty_type' => 'fixed', 'penalty_amount_minor' => 2500000]))
            ->assertCreated()
            ->assertJsonPath('data.summary', 'Non-refundable: cancelling at any time costs NGN 25,000.00. A no-show costs 100% of the stay.');

        $this->api()->postJson('/bff/settings/cancellation-policies', $this->cancellationPayload(['penalty_type' => 'percent', 'penalty_percent' => null]))
            ->assertUnprocessable()->assertJsonPath('errors.0.message', 'Enter the percentage charged.');
    }

    public function test_deposit_policies_and_custom_guest_text(): void
    {
        $id = $this->api()->postJson('/bff/settings/deposit-policies', $this->depositPayload())
            ->assertCreated()
            ->assertJsonPath('data.summary', 'A deposit of 50% of the stay is due 7 days before arrival. A credit card is required to guarantee the booking. The balance is paid at check-in.')
            ->json('data.id');

        $this->api()->patchJson("/bff/settings/deposit-policies/{$id}", $this->depositPayload(['guest_text' => 'Half now, half on arrival.']))
            ->assertOk()->assertJsonPath('data.summary', 'Half now, half on arrival.');

        $this->api()->postJson('/bff/settings/deposit-policies', $this->depositPayload(['name' => 'Prepaid', 'deposit_percent' => '100', 'deposit_due' => 'at_booking', 'requires_card_guarantee' => false]))
            ->assertCreated()->assertJsonPath('data.summary', 'A deposit of 100% of the stay is due at booking.');

        $this->api()->getJson('/bff/settings/policies')
            ->assertOk()
            ->assertJsonCount(2, 'data.deposit')
            ->assertJsonPath('meta.penalty_types.0.value', 'none');
    }

    // Rate plans -------------------------------------------------------------------------------

    public function test_a_rate_plan_is_created_with_prices_taxes_and_policies(): void
    {
        [$vat, $service] = $this->taxes();
        $policy = $this->api()->postJson('/bff/settings/cancellation-policies', $this->cancellationPayload())->json('data.id');

        $this->api()->postJson('/bff/rate-plans', $this->planPayload([
            'cancellation_policy_id' => $policy,
            'tax_ids' => [$vat->public_id, $service->public_id],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.code', 'BAR')
            ->assertJsonPath('data.meal_plan_label', 'Bed and breakfast')
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.cancellation_policy.name', 'Flexible')
            ->assertJsonPath('data.prices.0.room_type.code', 'STD')
            ->assertJsonPath('data.prices.1.base_price_minor', 6500000)
            ->assertJsonCount(2, 'data.taxes');

        $this->api()->getJson('/bff/rate-plans')->assertOk()->assertJsonCount(1, 'data');
        $this->api()->getJson('/bff/rate-plans/options')
            ->assertOk()
            ->assertJsonCount(2, 'data.room_types')
            ->assertJsonCount(2, 'data.taxes')
            ->assertJsonPath('data.cancellation_policies.0.name', 'Flexible');
    }

    public function test_price_changes_are_audited_with_old_and_new_values_and_the_reason(): void
    {
        $id = $this->api()->postJson('/bff/rate-plans', $this->planPayload())->json('data.id');

        $this->api()->patchJson("/bff/rate-plans/{$id}", $this->planPayload([
            'prices' => [
                ['room_type_id' => $this->standard->public_id, 'base_price_minor' => 4800000, 'extra_adult_minor' => 1000000, 'extra_child_minor' => 500000],
            ],
            'change_reason' => 'Festive season',
        ]))->assertOk()->assertJsonCount(1, 'data.prices');

        $changed = AuditEntry::query()->where('event', 'price_changed')->sole();
        $this->assertSame('Rate plan BAR: STD price changed', $changed->description);
        // Only what changed is recorded.
        $this->assertSame(['base_price_minor' => 4500000], $changed->properties['old']);
        $this->assertSame(['base_price_minor' => 4800000], $changed->properties['attributes']);
        $this->assertSame('Festive season', $changed->properties['reason']);

        $removed = AuditEntry::query()->where('event', 'price_removed')->sole();
        $this->assertSame('Rate plan BAR: DLX price removed', $removed->description);
    }

    public function test_rate_plan_validation(): void
    {
        $this->api()->postJson('/bff/rate-plans', $this->planPayload())->assertCreated();

        $this->api()->postJson('/bff/rate-plans', $this->planPayload())
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'code');

        $this->api()->postJson('/bff/rate-plans', $this->planPayload(['code' => 'X', 'min_price_minor' => 500, 'max_price_minor' => 100]))
            ->assertUnprocessable()->assertJsonPath('errors.0.message', 'The maximum must not be below the minimum.');

        $elsewhere = RoomType::factory()->for($this->property())->create();
        $this->api()->postJson('/bff/rate-plans', $this->planPayload(['code' => 'Y', 'prices' => [
            ['room_type_id' => $elsewhere->public_id, 'base_price_minor' => 1, 'extra_adult_minor' => 0, 'extra_child_minor' => 0],
        ]]))->assertUnprocessable()->assertJsonPath('errors.0.field', 'prices.0.room_type_id');
    }

    public function test_the_preview_prices_supplements_clamps_and_taxes(): void
    {
        [$vat, $service] = $this->taxes();
        $id = $this->api()->postJson('/bff/rate-plans', $this->planPayload(['tax_ids' => [$vat->public_id, $service->public_id]]))->json('data.id');

        // Deluxe: 2 adults fill the base occupancy, so both children pay the child supplement.
        $this->api()->getJson("/bff/rate-plans/{$id}/preview?room_type_id={$this->deluxe->public_id}&adults=2&children=2&nights=2")
            ->assertOk()
            ->assertJsonPath('data.extra_children', 2)
            ->assertJsonPath('data.nightly_minor', 7500000)
            ->assertJsonPath('data.room_charge_minor', 15000000)
            ->assertJsonPath('data.taxes.net_minor', 13953488)
            ->assertJsonPath('data.taxes.lines.0.amount_minor', 1046512)
            ->assertJsonPath('data.taxes.lines.1.amount_minor', 1395349)
            ->assertJsonPath('data.taxes.total_minor', 16395349);

        RatePlan::query()->where('public_id', $id)->update(['max_price_minor' => 7000000]);
        $this->api()->getJson("/bff/rate-plans/{$id}/preview?room_type_id={$this->deluxe->public_id}&adults=2&children=2")
            ->assertJsonPath('data.nightly_minor', 7000000)
            ->assertJsonPath('data.clamped', true);
    }

    public function test_the_preview_refuses_occupancy_the_room_cannot_take(): void
    {
        $id = $this->api()->postJson('/bff/rate-plans', $this->planPayload())->json('data.id');

        $this->api()->getJson("/bff/rate-plans/{$id}/preview?room_type_id={$this->standard->public_id}&adults=3")
            ->assertUnprocessable()->assertJsonPath('errors.0.message', 'Standard takes at most 2 adults.');
    }

    public function test_permissions_and_tenancy(): void
    {
        $id = $this->api()->postJson('/bff/rate-plans', $this->planPayload())->json('data.id');

        $frontDesk = $this->actingAs($this->staff($this->hotel, Role::FrontDeskAgent))->withHeader('X-Property-Id', $this->hotel->public_id);
        $frontDesk->getJson('/bff/rate-plans')->assertForbidden();
        $frontDesk->getJson('/bff/settings/taxes')->assertForbidden();
        $frontDesk->getJson('/bff/settings/policies')->assertForbidden();

        // A revenue manager prices rooms but does not manage taxes.
        $revenue = $this->actingAs($this->staff($this->hotel, Role::RevenueManager))->withHeader('X-Property-Id', $this->hotel->public_id);
        $revenue->getJson('/bff/rate-plans')->assertOk();
        $revenue->getJson('/bff/settings/taxes')->assertForbidden();

        $otherHotel = $this->property();
        $this->actingAs($this->superAdmin())->withHeader('X-Property-Id', $otherHotel->public_id)
            ->getJson("/bff/rate-plans/{$id}")->assertNotFound();
    }

    /**
     * @return array{0: Tax, 1: Tax}
     */
    private function taxes(): array
    {
        return [
            Tax::create(['property_id' => $this->hotel->id, 'name' => 'VAT', 'code' => 'VAT', 'calculation' => 'percentage', 'rate' => '7.5', 'is_inclusive' => true, 'sort_order' => 1]),
            Tax::create(['property_id' => $this->hotel->id, 'name' => 'Service charge', 'code' => 'SC', 'calculation' => 'percentage', 'rate' => '10', 'sort_order' => 2]),
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function taxPayload(array $overrides = []): array
    {
        return ['name' => 'VAT', 'code' => 'VAT', 'calculation' => 'percentage', 'rate' => '7.5', 'amount_minor' => null, 'is_inclusive' => true, 'sort_order' => 1, 'is_active' => true, ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function cancellationPayload(array $overrides = []): array
    {
        return [
            'name' => 'Flexible', 'free_cancellation_hours' => 48,
            'penalty_type' => 'nights', 'penalty_nights' => 1, 'penalty_percent' => null, 'penalty_amount_minor' => null,
            'no_show_type' => 'percent', 'no_show_nights' => null, 'no_show_percent' => '100', 'no_show_amount_minor' => null,
            'guest_text' => null, 'is_active' => true, ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function depositPayload(array $overrides = []): array
    {
        return [
            'name' => 'Half deposit', 'deposit_type' => 'percent', 'deposit_percent' => '50', 'deposit_nights' => null, 'deposit_amount_minor' => null,
            'deposit_due' => 'before_arrival', 'due_days_before_arrival' => 7, 'requires_card_guarantee' => true, 'balance_due' => 'at_check_in',
            'guest_text' => null, 'is_active' => true, ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return [
            'name' => 'Best Available Rate', 'code' => 'BAR', 'description' => null, 'meal_plan' => 'breakfast',
            'cancellation_policy_id' => null, 'deposit_policy_id' => null, 'min_price_minor' => null, 'max_price_minor' => null,
            'sort_order' => 0, 'is_active' => true, 'tax_ids' => [],
            'prices' => [
                ['room_type_id' => $this->standard->public_id, 'base_price_minor' => 4500000, 'extra_adult_minor' => 1000000, 'extra_child_minor' => 500000],
                ['room_type_id' => $this->deluxe->public_id, 'base_price_minor' => 6500000, 'extra_adult_minor' => 1000000, 'extra_child_minor' => 500000],
            ],
            ...$overrides,
        ];
    }

    private function api(): static
    {
        return $this->actingAs($this->manager)->withHeader('X-Property-Id', $this->hotel->public_id);
    }
}
