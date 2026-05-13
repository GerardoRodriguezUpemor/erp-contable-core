<?php

declare(strict_types=1);

namespace App\Ingestion\Application\DTOs;

/**
 * @deprecated Use RawCfdiDto instead.
 *
 * This class exists only as a temporary compatibility alias.
 * It will be removed once all callers have been migrated to RawCfdiDto.
 *
 * Migration checklist before removal:
 * - No external callers use this class directly
 * - All jobs reference IngestAndClassifyCfdiUseCase
 * - All legacy tests have been updated
 */
class RawInvoiceDto extends RawCfdiDto
{
}
