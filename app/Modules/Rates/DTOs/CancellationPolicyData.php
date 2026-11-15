<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Rates\Enums\PenaltyType;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * Cancellation and no-show terms (RTE-07, RES-03, RES-04).
 */
class CancellationPolicyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        /** Free cancellation until this many hours before arrival; null when non-refundable. */
        public ?int $free_cancellation_hours,
        public PenaltyType $penalty_type,
        public ?int $penalty_nights,
        public ?string $penalty_percent,
        public ?int $penalty_amount_minor,
        public PenaltyType $no_show_type,
        public ?int $no_show_nights,
        public ?string $no_show_percent,
        public ?int $no_show_amount_minor,
        public ?string $guest_text,
        /** What guests read: guest_text, or a summary generated from the rules. */
        public string $summary,
        public bool $is_active,
        public int $rate_plans_count,
        public RecordStampsData $stamps,
    ) {}

    public static function fromModel(CancellationPolicy $policy, string $summary): self
    {
        return new self(
            id: $policy->public_id,
            name: $policy->name,
            free_cancellation_hours: $policy->free_cancellation_hours,
            penalty_type: $policy->penalty_type,
            penalty_nights: $policy->penalty_nights,
            penalty_percent: self::percent($policy->penalty_percent),
            penalty_amount_minor: $policy->penalty_amount_minor,
            no_show_type: $policy->no_show_type,
            no_show_nights: $policy->no_show_nights,
            no_show_percent: self::percent($policy->no_show_percent),
            no_show_amount_minor: $policy->no_show_amount_minor,
            guest_text: $policy->guest_text,
            summary: $summary,
            is_active: $policy->is_active,
            rate_plans_count: (int) ($policy->getAttribute('rate_plans_count') ?? 0),
            stamps: RecordStampsData::fromModel($policy),
        );
    }

    private static function percent(?string $value): ?string
    {
        return $value === null ? null : rtrim(rtrim($value, '0'), '.');
    }
}
