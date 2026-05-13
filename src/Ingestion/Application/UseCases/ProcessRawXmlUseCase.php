<?php

declare(strict_types=1);

namespace App\Ingestion\Application\UseCases;

use App\Ingestion\Domain\Validators\XmlValidatorInterface;
use App\Ingestion\Infrastructure\Parsers\XmlParserInterface;
use App\Ingestion\Application\DTOs\RawCfdiDto;
use RuntimeException;

/**
 * @deprecated Use IngestAndClassifyCfdiUseCase instead.
 *
 * This class is a temporary compatibility layer. It will be removed when:
 * - No external callers reference it directly
 * - All queue jobs use IngestAndClassifyCfdiUseCase
 * - All legacy tests have been migrated
 *
 * Do NOT add new functionality here. Do NOT introduce new callers.
 *
 * @internal
 */
class ProcessRawXmlUseCase
{
    public function __construct(
        private XmlValidatorInterface $validator,
        private XmlParserInterface $parser
    ) {}

    public function execute(string $xmlContent): RawCfdiDto
    {
        if (!$this->validator->validate($xmlContent)) {
            throw new RuntimeException("Invalid CFDI XML structure. XSD validation failed.");
        }

        return $this->parser->parse($xmlContent);
    }
}

