<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Modules\Audit\Support\AuditChain;
use App\Modules\Property\Models\Organization;
use App\Modules\Property\Models\Property;
use App\Support\PropertyContext;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

/**
 * One audit trail entry (spec §5.12). Captures who / what / when / where and seals the entry
 * into its organisation's hash chain. Rows are never updated or deleted (DB triggers).
 *
 * @property int|null $organization_id
 * @property int|null $property_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $prev_hash
 * @property string $hash
 */
class AuditEntry extends Activity
{
    /** Held from "creating" until "created" so concurrent writers on any pod extend the chain in order. */
    public ?Lock $chainLock = null;

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->fillContext();

            $entry->chainLock = Cache::lock(AuditChain::key($entry->organization_id), 10);
            $entry->chainLock->block(5);

            $entry->created_at ??= now()->startOfSecond();
            $entry->updated_at = $entry->created_at;
            $entry->prev_hash = AuditChain::lastHash($entry->organization_id);
            $entry->hash = AuditChain::hash($entry);
        });

        static::created(function (self $entry): void {
            $entry->chainLock?->release();
            $entry->chainLock = null;
        });
    }

    /**
     * Where and for whom: organisation, property, IP and device, unless set explicitly.
     */
    private function fillContext(): void
    {
        $subject = $this->subject;
        $causer = $this->causer;

        $this->organization_id ??= ($subject instanceof Organization ? (int) $subject->getKey() : null)
            ?? $this->attributeOf($subject, 'organization_id')
            ?? $this->attributeOf($causer, 'organization_id')
            ?? Auth::user()?->getAttribute('organization_id');

        $this->property_id ??= $this->attributeOf($subject, 'property_id')
            ?? ($subject instanceof Property ? $subject->getKey() : null)
            ?? app(PropertyContext::class)->property()?->id;

        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            $request = request();
            $this->ip_address ??= $request->ip();
            $this->user_agent ??= $request->userAgent() !== null ? mb_substr($request->userAgent(), 0, 500) : null;
        }
    }

    private function attributeOf(?Model $model, string $attribute): ?int
    {
        $value = $model?->getAttribute($attribute);

        return $value === null ? null : (int) $value;
    }
}
