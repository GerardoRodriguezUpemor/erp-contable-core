<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Enums;

enum LifecycleState: string
{
    case IMPORTED = 'IMPORTED';   // Just arrived from Ingestion
    case PROCESSED = 'PROCESSED'; // Tax math applied, ready for reporting
    case DECLARED = 'DECLARED';   // Included in a monthly declaration
    case LOCKED = 'LOCKED';       // Included in an annual declaration (Immutable)
}
