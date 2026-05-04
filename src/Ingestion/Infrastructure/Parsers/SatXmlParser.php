<?php

declare(strict_types=1);

namespace App\Ingestion\Infrastructure\Parsers;

use App\Ingestion\Application\DTOs\RawInvoiceDto;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use RuntimeException;

class SatXmlParser implements XmlParserInterface
{
    public function parse(string $xmlContent): RawInvoiceDto
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
        $emittedAtString = (string) ($xml['Fecha'] ?? throw new RuntimeException("Fecha attribute missing."));
        $tipoDeComprobante = (string) ($xml['TipoDeComprobante'] ?? throw new RuntimeException("TipoDeComprobante attribute missing."));
        $metodoPago = (string) ($xml['MetodoPago'] ?? 'N/A'); // REPs (Pago) might not have this at the root

        // Extract Emisor and Receptor RFCs with defensive checks
        $emisor = $xml->xpath('//cfdi:Emisor');
        $emisorRfc = !empty($emisor) ? (string) $emisor[0]['Rfc'] : throw new RuntimeException("Emisor RFC missing");

        $receptor = $xml->xpath('//cfdi:Receptor');
        $receptorRfc = !empty($receptor) ? (string) $receptor[0]['Rfc'] : throw new RuntimeException("Receptor RFC missing");

        // Map directly to strongly typed Value Objects to prevent data leakage
        return new RawInvoiceDto(
            uuid: new Uuid($uuidString),
            emittedAt: new DateTimeImmutable($emittedAtString),
            tipoDeComprobante: $tipoDeComprobante,
            metodoPago: $metodoPago,
            subtotal: Money::fromFloat((float) ($xml['SubTotal'] ?? 0)),
            total: Money::fromFloat((float) ($xml['Total'] ?? 0)),
            emisorRfc: $emisorRfc,
            receptorRfc: $receptorRfc
        );
    }
}
