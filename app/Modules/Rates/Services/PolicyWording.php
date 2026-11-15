<?php

declare(strict_types=1);

namespace App\Modules\Rates\Services;

use App\Modules\Rates\Enums\BalanceDue;
use App\Modules\Rates\Enums\DepositDue;
use App\Modules\Rates\Enums\DepositType;
use App\Modules\Rates\Enums\PenaltyType;
use App\Modules\Rates\Models\CancellationPolicy;
use App\Modules\Rates\Models\DepositPolicy;
use App\Support\Money\Money;

/**
 * Plain-language summaries of policies for staff screens and, later, quotes and confirmations
 * (RTE-10). A policy's own guest_text, when set, is used instead.
 */
final class PolicyWording
{
    public function cancellation(CancellationPolicy $policy, string $currency): string
    {
        if ($policy->guest_text !== null && trim($policy->guest_text) !== '') {
            return trim($policy->guest_text);
        }

        $late = $this->penalty($policy->penalty_type, $policy->penalty_nights, $policy->penalty_percent, $policy->penalty_amount_minor, $currency);
        $noShow = $this->penalty($policy->no_show_type, $policy->no_show_nights, $policy->no_show_percent, $policy->no_show_amount_minor, $currency);

        $hours = $policy->free_cancellation_hours;
        $sentences = match (true) {
            $hours === null => ['Non-refundable: cancelling at any time costs '.$late.'.'],
            $hours === 0 => ['Free cancellation until check-in time on the day of arrival.', 'After that, cancelling costs '.$late.'.'],
            default => ['Free cancellation until '.$this->hours($hours).' before arrival.', 'After that, cancelling costs '.$late.'.'],
        };
        $sentences[] = 'A no-show costs '.$noShow.'.';

        return implode(' ', $sentences);
    }

    public function deposit(DepositPolicy $policy, string $currency): string
    {
        if ($policy->guest_text !== null && trim($policy->guest_text) !== '') {
            return trim($policy->guest_text);
        }

        $sentences = [];

        if ($policy->deposit_type === DepositType::None) {
            $sentences[] = 'No deposit is required.';
        } else {
            $amount = match ($policy->deposit_type) {
                DepositType::Percent => $this->percent((string) $policy->deposit_percent).' of the stay',
                DepositType::Nights => $this->nights((int) $policy->deposit_nights),
                DepositType::Fixed => Money::format((int) $policy->deposit_amount_minor, $currency),
            };
            $due = $policy->deposit_due === DepositDue::AtBooking
                ? 'at booking'
                : (int) $policy->due_days_before_arrival.' '.((int) $policy->due_days_before_arrival === 1 ? 'day' : 'days').' before arrival';

            $sentences[] = 'A deposit of '.$amount.' is due '.$due.'.';
        }

        if ($policy->requires_card_guarantee) {
            $sentences[] = 'A credit card is required to guarantee the booking.';
        }

        // A full prepayment leaves no balance to speak of.
        $fullyPrepaid = $policy->deposit_type === DepositType::Percent && (float) $policy->deposit_percent >= 100;
        if (! $fullyPrepaid) {
            $sentences[] = $policy->balance_due === BalanceDue::AtCheckIn ? 'The balance is paid at check-in.' : 'The balance is paid at check-out.';
        }

        return implode(' ', $sentences);
    }

    private function penalty(PenaltyType $type, ?int $nights, ?string $percent, ?int $amountMinor, string $currency): string
    {
        return match ($type) {
            PenaltyType::None => 'nothing',
            PenaltyType::Nights => $this->nights((int) $nights),
            PenaltyType::Percent => $this->percent((string) $percent).' of the stay',
            PenaltyType::Fixed => Money::format((int) $amountMinor, $currency),
        };
    }

    private function nights(int $nights): string
    {
        return $nights === 1 ? 'the first night' : "the first {$nights} nights";
    }

    private function percent(string $rate): string
    {
        return rtrim(rtrim($rate, '0'), '.').'%';
    }

    private function hours(int $hours): string
    {
        if ($hours % 24 === 0) {
            $days = intdiv($hours, 24);

            return $days === 1 ? '1 day' : "{$days} days";
        }

        return $hours === 1 ? '1 hour' : "{$hours} hours";
    }
}
