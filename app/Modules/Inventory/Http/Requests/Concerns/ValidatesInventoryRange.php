<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests\Concerns;

use App\Modules\Inventory\Models\RoomType;
use App\Modules\Inventory\Services\InventoryLedger;
use App\Support\PropertyContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for changes over a range of nights: a room type of the active property, and
 * dates between the business date (past nights are history) and the end of the ledger.
 *
 * @mixin FormRequest
 */
trait ValidatesInventoryRange
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function rangeRules(string $fromField, string $toField): array
    {
        $property = app(PropertyContext::class)->require();
        $ledger = app(InventoryLedger::class);
        $first = $ledger->firstDate($property)->toDateString();
        $last = $ledger->lastDate($property)->toDateString();
        $maxNights = (int) config('pms.inventory.max_range_days');

        return [
            'room_type_id' => [
                'required', 'string',
                Rule::exists('room_types', 'public_id')->where('property_id', $property->id)->whereNull('deleted_at'),
            ],
            $fromField => ['required', 'date_format:Y-m-d', "after_or_equal:{$first}", "before_or_equal:{$last}"],
            $toField => [
                'required', 'date_format:Y-m-d', "after_or_equal:{$fromField}", "before_or_equal:{$last}",
                function (string $attribute, mixed $value, Closure $fail) use ($fromField, $maxNights): void {
                    $from = $this->input($fromField);
                    if (is_string($from) && is_string($value) && strtotime($from) !== false && strtotime($value) !== false
                        && CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($value)) >= $maxNights) {
                        $fail("A range can cover at most {$maxNights} nights.");
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function rangeMessages(string $fromField, string $toField): array
    {
        return [
            "{$fromField}.after_or_equal" => 'Past nights cannot be changed: start on or after the business date.',
            "{$fromField}.before_or_equal" => 'That date is beyond the inventory horizon.',
            "{$toField}.before_or_equal" => 'That date is beyond the inventory horizon.',
            "{$toField}.after_or_equal" => 'The last night must be on or after the first night.',
        ];
    }

    public function roomType(): RoomType
    {
        return RoomType::forProperty(app(PropertyContext::class)->require())
            ->where('public_id', (string) $this->validated('room_type_id'))
            ->firstOrFail();
    }
}
