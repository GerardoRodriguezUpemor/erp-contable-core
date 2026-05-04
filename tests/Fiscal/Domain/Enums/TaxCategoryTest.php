<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Enums;

use PHPUnit\Framework\TestCase;
use App\Fiscal\Domain\Enums\TaxCategory;

class TaxCategoryTest extends TestCase
{
    public function test_it_correctly_identifies_deducted_taxes(): void
    {
        $this->assertTrue(TaxCategory::RETAINED->isDeductedFromTotal());
        $this->assertFalse(TaxCategory::TRANSFERRED->isDeductedFromTotal());
    }

    public function test_it_correctly_identifies_added_taxes(): void
    {
        $this->assertTrue(TaxCategory::TRANSFERRED->isAddedToTotal());
        $this->assertFalse(TaxCategory::RETAINED->isAddedToTotal());
    }
}
