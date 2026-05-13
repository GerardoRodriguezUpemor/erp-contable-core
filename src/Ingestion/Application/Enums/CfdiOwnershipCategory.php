<?php

declare(strict_types=1);

namespace App\Ingestion\Application\Enums;

/**
 * Semantic ERP ownership categories for a CFDI document.
 *
 * These categories represent internal ERP routing ownership and must NOT
 * be confused with:
 * - SatDocumentType (official SAT taxonomy)
 * - SAT fiscal states (ACTIVE, CANCELLED)
 * - Invoice LifecycleState (IMPORTED, PROCESSED, LOCKED)
 *
 * These are cross-context routing categories. Each category determines
 * which bounded context listener will handle the document.
 */
enum CfdiOwnershipCategory: string
{
    case INCOME_ISSUED                 = 'INCOME_ISSUED';
    case INCOME_RECEIVED               = 'INCOME_RECEIVED';
    case PAYMENT_COMPLEMENT_ISSUED     = 'PAYMENT_COMPLEMENT_ISSUED';
    case PAYMENT_COMPLEMENT_RECEIVED   = 'PAYMENT_COMPLEMENT_RECEIVED';
    case PAYROLL_ISSUED                = 'PAYROLL_ISSUED';
    case PAYROLL_RECEIVED              = 'PAYROLL_RECEIVED';
    case TRANSFER                      = 'TRANSFER';
    case THIRD_PARTY                   = 'THIRD_PARTY';
    case SELF_INVOICE                  = 'SELF_INVOICE';
}
