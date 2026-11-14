<?php

declare(strict_types=1);

namespace App\Modules\Property\DTOs;

use App\Modules\Property\Models\Property;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * Property setup view (SYS-03).
 */
class PropertyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public string $timezone,
        public string $currency,
        public string $business_date,
        public string $check_in_time,
        public string $check_out_time,
        public ?string $email,
        public ?string $phone,
        public ?string $address_line1,
        public ?string $address_line2,
        public ?string $city,
        public ?string $state,
        public ?string $postal_code,
        public ?string $country,
        public bool $is_active,
        public ?string $updated_at,
        public RecordStampsData $stamps,
    ) {}

    public static function fromModel(Property $property): self
    {
        return new self(
            id: $property->public_id,
            name: $property->name,
            code: $property->code,
            timezone: $property->timezone,
            currency: $property->currency,
            business_date: (string) $property->business_date?->toDateString(),
            check_in_time: substr((string) $property->check_in_time, 0, 5),
            check_out_time: substr((string) $property->check_out_time, 0, 5),
            email: $property->email,
            phone: $property->phone,
            address_line1: $property->address_line1,
            address_line2: $property->address_line2,
            city: $property->city,
            state: $property->state,
            postal_code: $property->postal_code,
            country: $property->country,
            is_active: $property->is_active,
            updated_at: $property->updated_at?->toIso8601ZuluString(),
            stamps: RecordStampsData::fromModel($property),
        );
    }
}
