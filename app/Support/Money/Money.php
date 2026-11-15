<?php

declare(strict_types=1);

namespace App\Support\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money as BrickMoney;

/**
 * Money helpers on top of brick/money (spec §2.8, §10.2): amounts are integers in minor units
 * (kobo, cents) with an ISO-4217 currency, and arithmetic never goes through floats.
 * Rounding is half up, line by line.
 */
final class Money
{
    /**
     * $percent of $minor, e.g. percentOf(10000, '7.5') = 750.
     */
    public static function percentOf(int $minor, BigNumber|int|string $percent): int
    {
        return BigDecimal::of($minor)->multipliedBy($percent)->dividedBy(100, 0, RoundingMode::HalfUp)->toInt();
    }

    /**
     * The amount before percentage taxes of $ratePercent that are included in $grossMinor,
     * e.g. netOfInclusive(10750, '7.5') = 10000.
     */
    public static function netOfInclusive(int $grossMinor, BigNumber|int|string $ratePercent): int
    {
        return BigDecimal::of($grossMinor)->multipliedBy(100)
            ->dividedBy(BigDecimal::of(100)->plus($ratePercent), 0, RoundingMode::HalfUp)
            ->toInt();
    }

    /** "₦45,000.00" style text for messages and policy wording. */
    public static function format(int $minor, string $currency, string $locale = 'en'): string
    {
        // ICU separates the currency with a (narrow) no-break space; plain text reads better with a space.
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', BrickMoney::ofMinor($minor, $currency)->formatToLocale($locale));
    }

    public static function fractionDigits(string $currency): int
    {
        return Currency::of($currency)->getDefaultFractionDigits();
    }
}
