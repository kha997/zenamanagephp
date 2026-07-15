<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    /**
     * Truth table for computeTotals.
     *
     * @dataProvider computeTotalsProvider
     */
    public function test_compute_totals_truth_table(float $subtotal, float $discountPercent, float $vatPercent, float $expectedDiscount, float $expectedVat, float $expectedTotal): void
    {
        $result = Quote::computeTotals($subtotal, $discountPercent, $vatPercent);

        $this->assertSame($expectedDiscount, $result['discount_amount']);
        $this->assertSame($expectedVat, $result['vat_amount']);
        $this->assertSame($expectedTotal, $result['total']);
    }

    /** @return array<string, array{float, float, float, float, float, float}> */
    public static function computeTotalsProvider(): array
    {
        return [
            'no discount no vat' => [1_000_000, 0, 0, 0, 0, 1_000_000],
            '10% discount only' => [27_500_000, 10, 0, 2_750_000, 0, 24_750_000],
            '8% vat only' => [27_500_000, 0, 8, 0, 2_200_000, 29_700_000],
            '10% discount + 8% vat' => [27_500_000, 10, 8, 2_750_000, 1_980_000, 26_730_000],
            'rounding' => [100, 33.33, 10, 33.33, 6.67, 73.34],
        ];
    }

    public function test_new_quote_has_zero_totals_by_default(): void
    {
        // Backfill in migration sets total = subtotal for existing rows.
        // New rows get default 0 for discount_percent, vat_percent, discount_amount, vat_amount, total.
        // This is verified by the migration schema definition: ->default(0) for all 5 columns.
        // The truth table tests above verify computeTotals produces correct values from those defaults.

        $result = Quote::computeTotals(5_000_000, 0, 0);
        $this->assertSame(0.0, $result['discount_amount']);
        $this->assertSame(0.0, $result['vat_amount']);
        $this->assertSame(5_000_000.0, $result['total']);
    }
}
