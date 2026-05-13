<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Parsers;

use App\Ingestion\Application\DTOs\RawCfdiDto;
use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use RuntimeException;

/**
 * Parses a raw SAT CFDI XML string into a RawCfdiDto.
 *
 * Audit: This parser contains ZERO ownership, routing, or fiscal logic.
 * Its sole responsibility is to extract raw SAT data and map it to
 * strongly-typed value objects. Classification is handled downstream
 * by CfdiOwnershipResolver.
 */
class SatXmlParser implements XmlParserInterface
{
    public function parse(string $xmlContent): RawCfdiDto
    {
        $xml = simplexml_load_string($xmlContent);
        if ($xml === false) {
            throw new RuntimeException("Failed to parse XML content.");
        }

        // Register namespaces to safely extract SAT nodes
        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('cfdi', $namespaces['cfdi'] ?? 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('tfd', $namespaces['tfd'] ?? 'http://www.sat.gob.mx/TimbreFiscalDigital');

        // Extract UUID from the TimbreFiscalDigital node with defensive check
        $tfdNode = $xml->xpath('//tfd:TimbreFiscalDigital');
        if (empty($tfdNode)) {
            throw new RuntimeException("TimbreFiscalDigital node not found. Is this a valid SAT CFDI?");
        }
        $uuidString = (string) $tfdNode[0]['UUID'];

        // Extract base attributes
        $emittedAtString    = (string) ($xml['Fecha'] ?? throw new RuntimeException("Fecha attribute missing."));
        $tipoDeComprobante  = (string) ($xml['TipoDeComprobante'] ?? throw new RuntimeException("TipoDeComprobante attribute missing."));
        $metodoPago         = (string) ($xml['MetodoPago'] ?? 'N/A'); // REPs (Pago) might not have this at the root

        // Normalize TipoDeComprobante into the semantic SatDocumentType.
        // This will throw explicitly if SAT introduces an unknown code.
        $documentType = SatDocumentType::fromSatCode($tipoDeComprobante);

        // Extract Emisor and Receptor RFCs with defensive checks
        $emisor    = $xml->xpath('//cfdi:Emisor');
        $emisorRfc = !empty($emisor) ? (string) $emisor[0]['Rfc'] : throw new RuntimeException("Emisor RFC missing");

        $receptor    = $xml->xpath('//cfdi:Receptor');
        $receptorRfc = !empty($receptor) ? (string) $receptor[0]['Rfc'] : throw new RuntimeException("Receptor RFC missing");

        return new RawCfdiDto(
            uuid:                new Uuid($uuidString),
            emittedAt:           new DateTimeImmutable($emittedAtString),
            documentType:        $documentType,
            tipoDeComprobante:   $tipoDeComprobante,
            metodoPago:          $metodoPago,
            subtotal:            Money::fromFloat((float) ($xml['SubTotal'] ?? 0)),
            total:               Money::fromFloat((float) ($xml['Total'] ?? 0)),
            emisorRfc:           $emisorRfc,
            receptorRfc:         $receptorRfc,
        );
    }
}
