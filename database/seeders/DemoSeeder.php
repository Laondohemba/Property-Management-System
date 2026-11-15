<?php

namespace Database\Seeders;

use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use App\Modules\Rates\Enums\MealPlan;
use App\Modules\Rates\Enums\PenaltyType;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;
use App\Modules\Rates\Models\RatePlan;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Local demo data. Idempotent, so it can be re-run without wiping existing accounts.
 * All demo users have the password "password". Never runs in production.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::firstOrCreate(['slug' => 'demo-hotels'], ['name' => 'Demo Hotels']);

        $admin = $this->user($organization, 'admin@example.com', 'Demo Admin');
        $admin->assignRole(RoleEnum::SuperAdmin->value);

        $property = Property::withoutGlobalScopes()->firstOrCreate(
            ['organization_id' => $organization->id, 'code' => 'DEMO'],
            [
                'name' => 'Demo Grand Hotel',
                'timezone' => 'Africa/Lagos',
                'currency' => 'NGN',
                'business_date' => now('Africa/Lagos')->toDateString(),
                'check_in_time' => '14:00',
                'check_out_time' => '12:00',
                'city' => 'Lagos',
                'country' => 'NG',
            ],
        );

        $frontDesk = $this->user($organization, 'frontdesk@example.com', 'Demo Front Desk');
        $property->users()->syncWithoutDetaching([
            $frontDesk->id => ['role_id' => Role::findByName(RoleEnum::FrontDeskAgent->value, 'web')->id],
        ]);

        $this->rooms($property);
        $this->rates($property);
    }

    /**
     * Nigerian taxes (VAT 7.5% included in prices, 10% service charge on top), two cancellation
     * policies, two deposit policies and three rate plans. Prices in kobo.
     */
    private function rates(Property $property): void
    {
        $owned = ['organization_id' => $property->organization_id, 'property_id' => $property->id];

        $vat = Tax::withoutGlobalScopes()->firstOrCreate([...$owned, 'code' => 'VAT'], [
            'name' => 'VAT', 'calculation' => TaxCalculation::Percentage, 'rate' => '7.5', 'is_inclusive' => true, 'sort_order' => 1,
        ]);
        $service = Tax::withoutGlobalScopes()->firstOrCreate([...$owned, 'code' => 'SC'], [
            'name' => 'Service charge', 'calculation' => TaxCalculation::Percentage, 'rate' => '10', 'is_inclusive' => false, 'sort_order' => 2,
        ]);

        $flexible = CancellationPolicy::withoutGlobalScopes()->firstOrCreate([...$owned, 'name' => 'Flexible'], [
            'free_cancellation_hours' => 48, 'penalty_type' => PenaltyType::Nights, 'penalty_nights' => 1,
            'no_show_type' => PenaltyType::Nights, 'no_show_nights' => 1,
        ]);
        $nonRefundable = CancellationPolicy::withoutGlobalScopes()->firstOrCreate([...$owned, 'name' => 'Non-refundable'], [
            'free_cancellation_hours' => null, 'penalty_type' => PenaltyType::Percent, 'penalty_percent' => '100',
            'no_show_type' => PenaltyType::Percent, 'no_show_percent' => '100',
        ]);
        $payAtHotel = DepositPolicy::withoutGlobalScopes()->firstOrCreate([...$owned, 'name' => 'Pay at hotel'], [
            'deposit_type' => DepositType::None, 'deposit_due' => DepositDue::AtBooking, 'requires_card_guarantee' => true,
            'balance_due' => BalanceDue::AtCheckOut,
        ]);
        $prepaid = DepositPolicy::withoutGlobalScopes()->firstOrCreate([...$owned, 'name' => 'Prepaid in full'], [
            'deposit_type' => DepositType::Percent, 'deposit_percent' => '100', 'deposit_due' => DepositDue::AtBooking,
            'balance_due' => BalanceDue::AtCheckIn,
        ]);

        $roomTypes = RoomType::withoutGlobalScopes()->where('property_id', $property->id)->pluck('id', 'code');
        $plans = [
            'BAR' => ['Best Available Rate', MealPlan::RoomOnly, $flexible, $payAtHotel, 0, ['STD' => 4500000, 'DLX' => 6500000, 'EXS' => 12000000]],
            'BB' => ['Bed and Breakfast', MealPlan::Breakfast, $flexible, $payAtHotel, 1, ['STD' => 5200000, 'DLX' => 7200000, 'EXS' => 13000000]],
            'NRF' => ['Non-refundable Saver', MealPlan::RoomOnly, $nonRefundable, $prepaid, 2, ['STD' => 3900000, 'DLX' => 5600000, 'EXS' => 10500000]],
        ];

        foreach ($plans as $code => [$name, $meal, $cancellation, $deposit, $sort, $prices]) {
            $plan = RatePlan::withoutGlobalScopes()->firstOrCreate([...$owned, 'code' => $code], [
                'name' => $name, 'meal_plan' => $meal, 'cancellation_policy_id' => $cancellation->id,
                'deposit_policy_id' => $deposit->id, 'sort_order' => $sort,
            ]);
            $plan->taxes()->syncWithoutDetaching([$vat->id, $service->id]);

            foreach ($prices as $typeCode => $base) {
                if (isset($roomTypes[$typeCode])) {
                    $plan->prices()->firstOrCreate(['room_type_id' => $roomTypes[$typeCode]], [
                        'base_price_minor' => $base, 'extra_adult_minor' => 1000000, 'extra_child_minor' => 500000,
                    ]);
                }
            }
        }
    }

    /**
     * Amenities, three room types and 20 rooms on two floors.
     */
    private function rooms(Property $property): void
    {
        $owned = ['organization_id' => $property->organization_id, 'property_id' => $property->id];

        $amenities = collect(['Wi-Fi', 'Air conditioning', 'Smart TV', 'Mini bar', 'Safe', 'Balcony', 'Bathtub', 'Workspace'])
            ->mapWithKeys(fn (string $name): array => [
                $name => Amenity::withoutGlobalScopes()->firstOrCreate([...$owned, 'name' => $name])->id,
            ]);

        $types = [
            'STD' => ['Standard Room', 2, 2, 1, 3, [['type' => 'queen', 'count' => 1]], ['Wi-Fi', 'Air conditioning', 'Smart TV'], 0],
            'DLX' => ['Deluxe Room', 2, 2, 2, 4, [['type' => 'king', 'count' => 1], ['type' => 'sofa_bed', 'count' => 1]], ['Wi-Fi', 'Air conditioning', 'Smart TV', 'Mini bar', 'Safe', 'Balcony'], 1],
            'EXS' => ['Executive Suite', 2, 3, 2, 4, [['type' => 'king', 'count' => 1], ['type' => 'twin', 'count' => 1]], ['Wi-Fi', 'Air conditioning', 'Smart TV', 'Mini bar', 'Safe', 'Bathtub', 'Workspace'], 2],
        ];

        $typeIds = collect($types)->map(function (array $definition, string $code) use ($owned, $amenities): int {
            [$name, $base, $adults, $children, $max, $beds, $amenityNames, $sort] = $definition;

            $roomType = RoomType::withoutGlobalScopes()->firstOrCreate([...$owned, 'code' => $code], [
                'name' => $name, 'base_occupancy' => $base, 'max_adults' => $adults, 'max_children' => $children,
                'max_occupancy' => $max, 'bed_configuration' => $beds, 'sort_order' => $sort,
            ]);
            $roomType->amenities()->syncWithoutDetaching($amenities->only($amenityNames)->values());

            return $roomType->id;
        })->all();

        $layout = [
            '1' => ['STD' => range(101, 108), 'DLX' => [109, 110]],
            '2' => ['DLX' => range(201, 206), 'EXS' => [207, 208, 209, 210]],
        ];

        $sort = 0;
        foreach ($layout as $floor => $byType) {
            foreach ($byType as $code => $numbers) {
                foreach ($numbers as $number) {
                    Room::withoutGlobalScopes()->firstOrCreate([...$owned, 'number' => (string) $number], [
                        'room_type_id' => $typeIds[$code], 'floor' => $floor, 'sort_order' => $sort++,
                    ]);
                }
            }
        }
    }

    private function user(Organization $organization, string $email, string $name): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['organization_id' => $organization->id, 'name' => $name, 'password' => 'password'],
        );

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }
}
