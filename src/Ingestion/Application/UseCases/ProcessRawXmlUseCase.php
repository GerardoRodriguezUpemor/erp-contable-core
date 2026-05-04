<?php

declare(strict_types=1);

namespace App\Ingestion\Application\UseCases;

use App\Ingestion\Domain\Validators\XmlValidatorInterface;
use App\Ingestion\Infrastructure\Parsers\XmlParserInterface;
use App\Ingestion\Application\DTOs\RawInvoiceDto;
use RuntimeException;

class ProcessRawXmlUseCase
{
    public function __construct(
        private XmlValidatorInterface $validator,
        private XmlParserInterface $parser
    ) {}

    public function execute(string $xmlContent): RawInvoiceDto
    {
        if (!$this->validator->validate($xmlContent)) {
            throw new RuntimeException("Invalid CFDI XML structure. XSD validation failed.");
        }

        return $this->parser->parse($xmlContent);
    }
}
