<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Enums;

enum TaxCategory: string
{
    case TRANSFERRED = 'transferred';
    case RETAINED = 'retained';

    /**
     * Determines if this tax category reduces the final total 
     * payable to the issuer of the invoice.
     */
    public function isDeductedFromTotal(): bool
    {
        return match($this) {
            self::RETAINED => true,
            self::TRANSFERRED => false,
        };
    }

    /**
     * Determines if this tax category increases the final total.
     */
    public function isAddedToTotal(): bool
    {
        return match($this) {
            self::TRANSFERRED => true,
            self::RETAINED => false,
        };
    }
}
