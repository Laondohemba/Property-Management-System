<?php

declare(strict_types=1);

namespace App\Modules\Audit\DTOs;

use App\Modules\Audit\Enums\AuditLog;
use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\DTOs\PropertyRefData;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\Amenity;
use App\Modules\Inventory\Models\InventoryBlock;
use App\Modules\Inventory\Models\Room;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * One row of the audit viewer: who, what, when, where, with before/after values (AUD-01, AUD-03).
 */
class AuditEntryData extends Data
{
    /**
     * @param  array<string, mixed>|null  $old  values before the change
     * @param  array<string, mixed>|null  $new  values after the change
     * @param  array<string, mixed>  $details  other recorded context (e.g. role, e-mail attempted)
     */
    public function __construct(
        /** The entry's chain hash doubles as its public id. */
        public string $id,
        public AuditLog $log,
        public ?string $event,
        public string $description,
        public ?AuditActorData $causer,
        public ?AuditSubjectData $subject,
        public ?PropertyRefData $property,
        public ?array $old,
        public ?array $new,
        public array $details,
        public ?string $ip_address,
        public ?string $user_agent,
        public string $created_at,
    ) {}

    /**
     * Expects causer, subject and property loaded.
     *
     * @param  array<int, Property>  $properties  by id
     */
    public static function fromModel(AuditEntry $entry, array $properties): self
    {
        $values = $entry->properties?->toArray() ?? [];
        $causer = $entry->causer;
        $property = $entry->property_id ? ($properties[$entry->property_id] ?? null) : null;

        return new self(
            id: $entry->hash,
            log: AuditLog::tryFrom((string) $entry->log_name) ?? AuditLog::Data,
            event: $entry->event,
            description: $entry->description,
            causer: $causer instanceof User ? new AuditActorData($causer->public_id, $causer->name, $causer->email) : null,
            subject: $entry->subject_type ? self::subject($entry->subject_type, $entry->subject) : null,
            property: $property ? PropertyRefData::fromProperty($property) : null,
            old: isset($values['old']) && is_array($values['old']) ? $values['old'] : null,
            new: isset($values['attributes']) && is_array($values['attributes']) ? $values['attributes'] : null,
            details: array_diff_key($values, array_flip(['old', 'attributes'])),
            ip_address: $entry->ip_address,
            user_agent: $entry->user_agent,
            created_at: (string) $entry->created_at?->toIso8601ZuluString(),
        );
    }

    private static function subject(string $type, ?Model $subject): AuditSubjectData
    {
        $label = match (true) {
            $subject instanceof User => $subject->email,
            $subject instanceof Room => 'Room '.$subject->number,
            $subject instanceof InventoryBlock => $subject->type->label().' · '.$subject->from_date->toDateString().' to '.$subject->to_date->toDateString(),
            $subject instanceof RoomType, $subject instanceof Property => $subject->name.' ('.$subject->code.')',
            $subject instanceof Amenity, $subject instanceof Organization => $subject->name,
            $subject instanceof Role => $subject->displayLabel(),
            default => null,
        };

        $publicId = $subject?->getAttribute('public_id') ?? ($subject instanceof Role ? $subject->name : null);

        return new AuditSubjectData(class_basename($type), $publicId === null ? null : (string) $publicId, $label);
    }
}
