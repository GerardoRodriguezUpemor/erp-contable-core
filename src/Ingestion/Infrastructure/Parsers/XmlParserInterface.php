<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Parsers;

use App\Ingestion\Application\DTOs\RawInvoiceDto;

interface XmlParserInterface
{
    public function parse(string $xmlContent): RawInvoiceDto;
}
