<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use App\Modules\Audit\Models\AuditEntry;
use Illuminate\Support\Collection;

/**
 * Hash chaining for the audit trail (AUD-02). Each organisation has its own chain; entries
 * without an organisation (e.g. a failed login for an unknown e-mail) form a global chain.
 *
 * hash = sha256(canonical JSON of the entry's content + the previous entry's hash), so editing
 * or removing any entry breaks every hash after it.
 */
final class AuditChain
{
    public static function key(?int $organizationId): string
    {
        return 'audit-chain:'.($organizationId ?? 'global');
    }

    public static function lastHash(?int $organizationId): ?string
    {
        return AuditEntry::query()
            ->when($organizationId === null, fn ($query) => $query->whereNull('organization_id'), fn ($query) => $query->where('organization_id', $organizationId))
            ->orderByDesc('id')
            ->value('hash');
    }

    public static function hash(AuditEntry $entry): string
    {
        return hash('sha256', json_encode([
            'prev_hash' => $entry->prev_hash,
            'organization_id' => $entry->organization_id === null ? null : (int) $entry->organization_id,
            'property_id' => $entry->property_id === null ? null : (int) $entry->property_id,
            'log_name' => $entry->log_name,
            'event' => $entry->event,
            'description' => $entry->description,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id === null ? null : (string) $entry->subject_id,
            'causer_type' => $entry->causer_type,
            'causer_id' => $entry->causer_id === null ? null : (string) $entry->causer_id,
            'properties' => self::canonical($entry->properties),
            'ip_address' => $entry->ip_address,
            'user_agent' => $entry->user_agent,
            'created_at' => $entry->created_at?->format('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * Normalise properties exactly as they will read back from the JSON column: JSON round trip
     * (enums, dates and objects become scalars) and recursively sorted keys (MySQL reorders them).
     */
    public static function canonical(mixed $properties): mixed
    {
        if ($properties instanceof Collection) {
            $properties = $properties->all();
        }

        $value = json_decode(json_encode($properties ?? [], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        return self::sortKeys($value);
    }

    private static function sortKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => self::sortKeys($item), $value);
    }
}
