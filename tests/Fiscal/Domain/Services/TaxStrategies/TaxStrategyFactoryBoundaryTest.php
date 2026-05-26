<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Services\TaxStrategies;

use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyFactory;
use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Boundary tests for the TaxStrategyFactory.
 *
 * These tests pin the exact year boundaries for Regime 625 (2024–2026).
 * They serve as the safety net for F015 (annual tax law changes) documented
 * in the extensibility blueprint: any new year requires a new entry in the
 * factory's match expression, and these tests will fail loudly if that is
 * not done — preventing silent tax miscalculations in a new fiscal year.
 */
class TaxStrategyFactoryBoundaryTest extends TestCase
{
    private TaxStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TaxStrategyFactory();
    }

    // --- Valid year boundaries ---

    public function test_factory_resolves_regime_625_for_first_supported_year_2024(): void
    {
        $strategy = $this->factory->create('625', new DateTimeImmutable('2024-01-01'));
        $this->assertInstanceOf(Regime625TaxStrategy::class, $strategy);
    }

    public function test_factory_resolves_regime_625_for_mid_boundary_year_2025(): void
    {
        $strategy = $this->factory->create('625', new DateTimeImmutable('2025-06-15'));
        $this->assertInstanceOf(Regime625TaxStrategy::class, $strategy);
    }

    public function test_factory_resolves_regime_625_for_last_supported_year_2026(): void
    {
        $strategy = $this->factory->create('625', new DateTimeImmutable('2026-12-31'));
        $this->assertInstanceOf(Regime625TaxStrategy::class, $strategy);
    }

    // --- Unsupported year boundaries ---

    public function test_factory_throws_for_year_before_support_window_2023(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fiscal year 2023');

        $this->factory->create('625', new DateTimeImmutable('2023-12-31'));
    }

    public function test_factory_throws_for_year_after_support_window_2027(): void
    {
        // This test MUST fail after 2027 strategy is implemented.
        // Its failure is the signal to add Regime625TaxStrategy2027 to the factory.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fiscal year 2027');

        $this->factory->create('625', new DateTimeImmutable('2027-01-01'));
    }

    // --- Unknown regimes ---

    public function test_factory_throws_for_regime_601_general_law_corporations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("regime '601'");

        $this->factory->create('601', new DateTimeImmutable('2026-01-01'));
    }

    public function test_factory_throws_for_regime_612_physical_persons(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("regime '612'");

        $this->factory->create('612', new DateTimeImmutable('2026-01-01'));
    }

    public function test_factory_throws_for_empty_regime_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->factory->create('', new DateTimeImmutable('2026-01-01'));
    }
}
