<?php

declare(strict_types=1);

namespace App\Ingestion\Application\DTOs;

use InvalidArgumentException;

/**
 * Represents the official SAT TipoDeComprobante taxonomy ONLY.
 *
 * This enum maps raw SAT codes to semantic PHP cases.
 * It does NOT represent ERP internal categories — those are
 * determined downstream by CfdiOwnershipResolver.
 *
 * @see https://www.sat.gob.mx/consultas/83968/comprobantes-fiscales-cfdi (Anexo 20)
 */
enum SatDocumentType: string
{
    case INVOICE     = 'I'; // Ingreso
    case CREDIT_NOTE = 'E'; // Egreso
    case PAYMENT     = 'P'; // Pago (Complemento de Pago / REP)
    case PAYROLL     = 'N'; // Nómina
    case TRANSFER    = 'T'; // Traslado

    /**
     * Constructs a SatDocumentType from a raw SAT TipoDeComprobante code.
     *
     * Fails explicitly on unknown codes to prevent silent semantic corruption
     * of fiscal documents. Do NOT add a default/silent fallback.
     *
     * @throws InvalidArgumentException if the code is not a known SAT type
     */
    public static function fromSatCode(string $code): self
    {
        return match($code) {
            'I'     => self::INVOICE,
            'E'     => self::CREDIT_NOTE,
            'P'     => self::PAYMENT,
            'N'     => self::PAYROLL,
            'T'     => self::TRANSFER,
            default => throw new InvalidArgumentException(
                "Unknown SAT TipoDeComprobante code: '{$code}'. " .
                "The SAT may have introduced a new code in Anexo 20. " .
                "Add it explicitly to SatDocumentType to prevent semantic corruption."
            ),
        };
    }
}
