<?php

declare(strict_types=1);

namespace App\Modules\Rates\DTOs;

use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use App\Modules\Rates\Models\DepositPolicy;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * Deposit and payment terms (RTE-07).
 */
class DepositPolicyData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public DepositType $deposit_type,
        public ?string $deposit_percent,
        public ?int $deposit_nights,
        public ?int $deposit_amount_minor,
        public DepositDue $deposit_due,
        public ?int $due_days_before_arrival,
        public bool $requires_card_guarantee,
        public BalanceDue $balance_due,
        public ?string $guest_text,
        public string $summary,
        public bool $is_active,
        public int $rate_plans_count,
        public RecordStampsData $stamps,
    ) {}

    public static function fromModel(DepositPolicy $policy, string $summary): self
    {
        return new self(
            id: $policy->public_id,
            name: $policy->name,
            deposit_type: $policy->deposit_type,
            deposit_percent: $policy->deposit_percent === null ? null : rtrim(rtrim((string) $policy->deposit_percent, '0'), '.'),
            deposit_nights: $policy->deposit_nights,
            deposit_amount_minor: $policy->deposit_amount_minor,
            deposit_due: $policy->deposit_due,
            due_days_before_arrival: $policy->due_days_before_arrival,
            requires_card_guarantee: $policy->requires_card_guarantee,
            balance_due: $policy->balance_due,
            guest_text: $policy->guest_text,
            summary: $summary,
            is_active: $policy->is_active,
            rate_plans_count: (int) ($policy->getAttribute('rate_plans_count') ?? 0),
            stamps: RecordStampsData::fromModel($policy),
        );
    }
}
