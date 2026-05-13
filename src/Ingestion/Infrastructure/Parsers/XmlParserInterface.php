<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Parsers;

use App\Ingestion\Application\DTOs\RawCfdiDto;

interface XmlParserInterface
{
    public function parse(string $xmlContent): RawCfdiDto;
}
