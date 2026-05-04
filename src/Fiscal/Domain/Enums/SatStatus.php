<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Enums;

enum SatStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CANCELLED = 'CANCELLED';
    case PENDING_CANCELLATION = 'PENDING_CANCELLATION';
}
