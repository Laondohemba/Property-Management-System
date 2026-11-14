<?php

namespace Database\Seeders;

use App\Modules\Identity\Enums\Role as RoleEnum;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
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
