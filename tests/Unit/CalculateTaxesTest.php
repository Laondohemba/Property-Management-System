<?php

namespace Tests\Unit;

use App\Modules\Billing\Actions\CalculateTaxes;
use App\Modules\Billing\Enums\TaxCalculation;
use App\Modules\Billing\Models\Tax;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Taxes and fees on a room charge (spec §2.8): inclusive extraction, exclusive additions,
 * fixed fees, and amounts that always add up to the minor unit.
 */
class CalculateTaxesTest extends TestCase
{
    private int $id = 0;

    public function test_an_inclusive_percentage_is_extracted_from_the_price(): void
    {
        $result = (new CalculateTaxes)->handle(10750, 1, 2, [$this->tax('VAT', 'percentage', rate: '7.5', inclusive: true)], 'NGN');

        $this->assertSame(10000, $result->net_minor);
        $this->assertSame(750, $result->inclusive_minor);
        $this->assertSame(0, $result->exclusive_minor);
        $this->assertSame(10750, $result->total_minor);
        $this->assertSame('7.5% included', $result->lines[0]->basis);
    }

    public function test_an_exclusive_percentage_is_added_on_the_net_charge(): void
    {
        $result = (new CalculateTaxes)->handle(10750, 1, 2, [
            $this->tax('VAT', 'percentage', rate: '7.5', inclusive: true),
            $this->tax('SC', 'percentage', rate: '10'),
        ], 'NGN');

        $this->assertSame(1000, $result->lines[1]->amount_minor);
        $this->assertSame(11750, $result->total_minor);
    }

    public function test_several_inclusive_taxes_always_add_up_to_the_charge(): void
    {
        // 9,999 with 7.5% + 5% included: rounding is absorbed by the last inclusive line.
        $result = (new CalculateTaxes)->handle(9999, 1, 1, [
            $this->tax('VAT', 'percentage', rate: '7.5', inclusive: true),
            $this->tax('CT', 'percentage', rate: '5', inclusive: true),
        ], 'NGN');

        $this->assertSame(9999, $result->net_minor + $result->lines[0]->amount_minor + $result->lines[1]->amount_minor);
        $this->assertSame(8888, $result->net_minor);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function fixedFees(): iterable
    {
        yield 'per night' => ['per_night', 150000, 'NGN 500.00 × 3 nights'];
        yield 'per stay' => ['per_stay', 50000, 'NGN 500.00 per stay'];
        yield 'per guest per night' => ['per_person_night', 300000, 'NGN 500.00 × 6 guest nights'];
    }

    #[DataProvider('fixedFees')]
    public function test_fixed_fees_count_nights_stays_or_guest_nights(string $calculation, int $expected, string $basis): void
    {
        $result = (new CalculateTaxes)->handle(3000000, 3, 2, [$this->tax('LEVY', $calculation, amount: 50000)], 'NGN');

        $this->assertSame($expected, $result->exclusive_minor);
        $this->assertSame(3000000 + $expected, $result->total_minor);
        $this->assertSame($basis, $result->lines[0]->basis);
    }

    public function test_inactive_taxes_are_ignored_and_no_taxes_means_no_change(): void
    {
        $inactive = $this->tax('OLD', 'percentage', rate: '20');
        $inactive->is_active = false;

        $result = (new CalculateTaxes)->handle(5000, 1, 1, [$inactive], 'NGN');

        $this->assertSame([], $result->lines);
        $this->assertSame(5000, $result->net_minor);
        $this->assertSame(5000, $result->total_minor);
    }

    private function tax(string $code, string $calculation, ?string $rate = null, ?int $amount = null, bool $inclusive = false): Tax
    {
        $tax = new Tax([
            'name' => $code, 'code' => $code, 'calculation' => TaxCalculation::from($calculation),
            'rate' => $rate, 'amount_minor' => $amount, 'is_inclusive' => $inclusive, 'is_active' => true,
        ]);
        $tax->id = ++$this->id;
        $tax->public_id = 'tax-'.$this->id;

        return $tax;
    }
}
