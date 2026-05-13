<?php

declare(strict_types=1);

namespace Tests\Ingestion\Application\DTOs;

use App\Ingestion\Application\DTOs\SatDocumentType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SatDocumentTypeTest extends TestCase
{
    public function test_it_maps_all_known_sat_codes(): void
    {
        $this->assertSame(SatDocumentType::INVOICE,     SatDocumentType::fromSatCode('I'));
        $this->assertSame(SatDocumentType::CREDIT_NOTE, SatDocumentType::fromSatCode('E'));
        $this->assertSame(SatDocumentType::PAYMENT,     SatDocumentType::fromSatCode('P'));
        $this->assertSame(SatDocumentType::PAYROLL,     SatDocumentType::fromSatCode('N'));
        $this->assertSame(SatDocumentType::TRANSFER,    SatDocumentType::fromSatCode('T'));
    }

    public function test_it_throws_on_unknown_sat_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown SAT TipoDeComprobante code: 'X'");

        SatDocumentType::fromSatCode('X');
    }

    public function test_it_throws_on_empty_code(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SatDocumentType::fromSatCode('');
    }

    public function test_it_throws_on_lowercase_code(): void
    {
        // SAT codes are uppercase — lowercase must not silently map
        $this->expectException(InvalidArgumentException::class);

        SatDocumentType::fromSatCode('i');
    }

    public function test_backed_enum_values_match_sat_codes(): void
    {
        $this->assertSame('I', SatDocumentType::INVOICE->value);
        $this->assertSame('E', SatDocumentType::CREDIT_NOTE->value);
        $this->assertSame('P', SatDocumentType::PAYMENT->value);
        $this->assertSame('N', SatDocumentType::PAYROLL->value);
        $this->assertSame('T', SatDocumentType::TRANSFER->value);
    }
}
