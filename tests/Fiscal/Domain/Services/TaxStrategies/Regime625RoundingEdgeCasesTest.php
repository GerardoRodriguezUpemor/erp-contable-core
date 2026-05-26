<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Services\TaxStrategies;

use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Shared\Domain\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Edge case tests for Regime 625 rounding behavior.
 *
 * The SAT uses PHP's default half-up rounding (round()) at the cent level.
 * This is locked in as a critical rule (F015 in FLADP findings).
 * These tests pin the exact rounding behavior so any future change is caught.
 *
 * Also validates that the strategy returns exactly 3 taxes (IVA, IVA_RET, ISR_RET)
 * — no more, no less — for any given base.
 */
class Regime625RoundingEdgeCasesTest extends TestCase
{
    private Regime625TaxStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new Regime625TaxStrategy();
    }

    /**
     * Base of $1.00 (100 cents):
     *   IVA  = 100 * 0.16 = 16 cents  → 16
     *   IVA_RET = 100 * 0.08 = 8 cents → 8
     *   ISR_RET = 100 * 0.021 = 2.1 cents → round() = 2
     */
    public function test_rounding_on_small_base_isr_rounds_down(): void
    {
        $taxes = $this->strategy->calculateTaxes(new Money(100)); // $1.00

        $isr = $this->findTax($taxes, 'ISR_RET');
        $this->assertNotNull($isr);
        $this->assertEquals(2, $isr->amount->getCents()); // 2.1 rounds down to 2
    }

    /**
     * Base of $0.50 (50 cents):
     *   ISR_RET = 50 * 0.021 = 1.05 cents → round() = 1
     */
    public function test_rounding_on_fifty_cents_base(): void
    {
        $taxes = $this->strategy->calculateTaxes(new Money(50)); // $0.50

        $isr = $this->findTax($taxes, 'ISR_RET');
        $this->assertNotNull($isr);
        $this->assertEquals(1, $isr->amount->getCents()); // 1.05 rounds to 1
    }

    /**
     * Base of $3.33 (333 cents):
     *   IVA  = 333 * 0.16 = 53.28 → round = 53
     *   IVA_RET = 333 * 0.08 = 26.64 → round = 27
     *   ISR_RET = 333 * 0.021 = 6.993 → round = 7
     */
    public function test_rounding_on_odd_base_333_cents(): void
    {
        $taxes = $this->strategy->calculateTaxes(new Money(333));

        $iva    = $this->findTax($taxes, 'IVA');
        $ivaRet = $this->findTax($taxes, 'IVA_RET');
        $isrRet = $this->findTax($taxes, 'ISR_RET');

        $this->assertEquals(53, $iva->amount->getCents());
        $this->assertEquals(27, $ivaRet->amount->getCents());
        $this->assertEquals(7, $isrRet->amount->getCents());
    }

    public function test_strategy_always_returns_exactly_three_taxes(): void
    {
        $taxes = $this->strategy->calculateTaxes(new Money(100000));
        $this->assertCount(3, $taxes);
    }

    public function test_strategy_returns_one_transferred_and_two_retained_taxes(): void
    {
        $taxes = $this->strategy->calculateTaxes(new Money(100000));

        $transferred = array_filter($taxes, fn($t) => $t->category === TaxCategory::TRANSFERRED);
        $retained    = array_filter($taxes, fn($t) => $t->category === TaxCategory::RETAINED);

        $this->assertCount(1, $transferred);
        $this->assertCount(2, $retained);
    }

    public function test_total_retained_is_always_less_than_transferred_for_any_base(): void
    {
        // This is the anti-fraud rule documented in the Testing Checklist (1.2)
        foreach ([100, 1000, 50000, 999999] as $cents) {
            $taxes = $this->strategy->calculateTaxes(new Money($cents));

            $transferred = 0;
            $retained    = 0;

            foreach ($taxes as $tax) {
                if ($tax->category === TaxCategory::TRANSFERRED) {
                    $transferred += $tax->amount->getCents();
                } else {
                    $retained += $tax->amount->getCents();
                }
            }

            $this->assertGreaterThan(
                $retained,
                $transferred,
                "Anti-fraud violated for base {$cents} cents: retained ({$retained}) >= transferred ({$transferred})"
            );
        }
    }

    // --- Helpers ---

    private function findTax(array $taxes, string $name): mixed
    {
        foreach ($taxes as $tax) {
            if ($tax->name === $name) {
                return $tax;
            }
        }
        return null;
    }
}
