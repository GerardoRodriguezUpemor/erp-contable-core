<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Services\TaxStrategies;

use PHPUnit\Framework\TestCase;
use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyFactory;
use App\Fiscal\Domain\Services\TaxStrategies\Regime625TaxStrategy;
use DateTimeImmutable;
use InvalidArgumentException;

class TaxStrategyFactoryTest extends TestCase
{
    private TaxStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TaxStrategyFactory();
    }

    public function test_it_resolves_regime_625_for_valid_years(): void
    {
        $date = new DateTimeImmutable('2026-04-23');
        $strategy = $this->factory->create('625', $date);

        $this->assertInstanceOf(Regime625TaxStrategy::class, $strategy);
    }

    public function test_it_throws_exception_for_unsupported_regime(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No tax strategy defined for regime '601'");

        $date = new DateTimeImmutable('2026-04-23');
        $this->factory->create('601', $date); // 601 is General Law for Corporations
    }

    public function test_it_throws_exception_for_unsupported_year(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No Regime 625 tax strategy defined for fiscal year 2020");

        $date = new DateTimeImmutable('2020-01-01');
        $this->factory->create('625', $date);
    }
}
