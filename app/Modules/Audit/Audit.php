<?php

declare(strict_types=1);

namespace App\Modules\Audit;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Models\User;
use App\Modules\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;

/**
 * Entry point for audit records that are not plain model changes (spec §5.12):
 * security events and permission changes (AUD-04).
 */
final class Audit
{
    public const DATA = 'data';

    public const SECURITY = 'security';

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function security(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $causer = null,
        ?Property $property = null,
    ): void {
        self::record(self::SECURITY, $event, $description, $subject, $properties, $causer, $property);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(
        string $log,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $causer = null,
        ?Property $property = null,
    ): void {
        $logger = activity($log)->event($event)->withProperties($properties);

        if ($subject !== null) {
            $logger->performedOn($subject);
        }

        $causer ??= auth()->user() instanceof User ? auth()->user() : null;
        $causer !== null ? $logger->causedBy($causer) : $logger->causedByAnonymous();

        if ($property !== null) {
            $logger->tap(function (AuditEntry $entry) use ($property): void {
                $entry->property_id = $property->id;
                $entry->organization_id = $property->organization_id;
            });
        }

        $logger->log($description);
    }
}
