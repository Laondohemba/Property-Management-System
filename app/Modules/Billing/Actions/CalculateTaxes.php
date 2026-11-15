<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\DTOs\TaxBreakdownData;
use App\Modules\Billing\DTOs\TaxLineData;
use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use App\Support\Money\Money;
use Brick\Math\BigDecimal;

/**
 * Works out the taxes and fees on a room charge (spec §2.8, RTE-10). Shared by the rate plan
 * preview, quotes, reservations and the night audit so every screen shows the same numbers.
 *
 * - Inclusive percentages are already inside the charge: the net is extracted once using their
 *   combined rate, and the last inclusive line absorbs any rounding so net + taxes = charge.
 * - Exclusive percentages are added on top, on the net charge (they do not compound).
 * - Fixed fees are per room night, per stay, or per guest per night, always on top.
 */
final class CalculateTaxes
{
    /**
     * @param  iterable<Tax>  $taxes  active taxes in display order
     */
    public function handle(int $chargeMinor, int $nights, int $guests, iterable $taxes, string $currency): TaxBreakdownData
    {
        $taxes = collect($taxes)->filter(fn (Tax $tax): bool => $tax->is_active)->values();

        $inclusive = $taxes->filter(fn (Tax $tax): bool => $tax->is_inclusive && $tax->calculation === TaxCalculation::Percentage)->values();
        $inclusiveRate = (string) $inclusive->reduce(fn (BigDecimal $sum, Tax $tax): BigDecimal => $sum->plus((string) $tax->rate), BigDecimal::zero());
        $net = $inclusive->isEmpty() ? $chargeMinor : Money::netOfInclusive($chargeMinor, $inclusiveRate);

        $inclusiveAmounts = [];
        $remaining = $chargeMinor - $net;
        foreach ($inclusive as $index => $tax) {
            $amount = $index === $inclusive->count() - 1 ? $remaining : Money::percentOf($net, (string) $tax->rate);
            $inclusiveAmounts[$tax->id] = $amount;
            $remaining -= $amount;
        }

        $lines = [];
        $exclusiveTotal = 0;

        foreach ($taxes as $tax) {
            if (array_key_exists($tax->id, $inclusiveAmounts)) {
                $lines[] = $this->line($tax, $this->percentText((string) $tax->rate).' included', $inclusiveAmounts[$tax->id], true);

                continue;
            }

            [$amount, $basis] = match ($tax->calculation) {
                TaxCalculation::Percentage => [Money::percentOf($net, (string) $tax->rate), $this->percentText((string) $tax->rate)],
                TaxCalculation::PerNight => [(int) $tax->amount_minor * $nights, $this->fixedText($tax, $currency, $nights, 'night')],
                TaxCalculation::PerStay => [(int) $tax->amount_minor, Money::format((int) $tax->amount_minor, $currency).' per stay'],
                TaxCalculation::PerPersonNight => [(int) $tax->amount_minor * $guests * $nights, $this->fixedText($tax, $currency, $guests * $nights, 'guest night')],
            };

            $exclusiveTotal += $amount;
            $lines[] = $this->line($tax, $basis, $amount, false);
        }

        return new TaxBreakdownData(
            currency: $currency,
            charge_minor: $chargeMinor,
            net_minor: $net,
            inclusive_minor: $chargeMinor - $net,
            exclusive_minor: $exclusiveTotal,
            total_minor: $chargeMinor + $exclusiveTotal,
            lines: $lines,
        );
    }

    private function line(Tax $tax, string $basis, int $amount, bool $inclusive): TaxLineData
    {
        return new TaxLineData($tax->public_id, $tax->name, $tax->code, $basis, $amount, $inclusive);
    }

    private function percentText(string $rate): string
    {
        return rtrim(rtrim($rate, '0'), '.').'%';
    }

    private function fixedText(Tax $tax, string $currency, int $count, string $unit): string
    {
        return Money::format((int) $tax->amount_minor, $currency).' × '.$count.' '.$unit.($count === 1 ? '' : 's');
    }
}
