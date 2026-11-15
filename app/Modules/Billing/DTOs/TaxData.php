<?php

declare(strict_types=1);

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use App\Support\Http\DTOs\RecordStampsData;
use Spatie\LaravelData\Data;

/**
 * A tax or fee (spec §2.8). Percentages use `rate`; fixed fees use `amount_minor`.
 */
class TaxData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public TaxCalculation $calculation,
        public string $calculation_label,
        /** Percentage as a decimal string, e.g. "7.5". */
        public ?string $rate,
        public ?int $amount_minor,
        public bool $is_inclusive,
        public int $sort_order,
        public bool $is_active,
        public RecordStampsData $stamps,
    ) {}

    public static function fromModel(Tax $tax): self
    {
        return new self(
            id: $tax->public_id,
            name: $tax->name,
            code: $tax->code,
            calculation: $tax->calculation,
            calculation_label: $tax->calculation->label(),
            rate: $tax->rate === null ? null : rtrim(rtrim((string) $tax->rate, '0'), '.'),
            amount_minor: $tax->amount_minor,
            is_inclusive: $tax->is_inclusive,
            sort_order: $tax->sort_order,
            is_active: $tax->is_active,
            stamps: RecordStampsData::fromModel($tax),
        );
    }
}
