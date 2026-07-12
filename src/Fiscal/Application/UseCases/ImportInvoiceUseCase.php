<?php

declare(strict_types=1);

namespace App\Fiscal\Application\UseCases;

use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyFactoryInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Application\EventDispatcherInterface;
use App\Fiscal\Domain\Events\InvoiceImportedEvent;
use App\Fiscal\Domain\Entities\Invoice;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use RuntimeException;

/**
 * Fiscal use case responsible for constructing the Invoice aggregate,
 * applying tax strategy calculations, and persisting to the Fiscal repository.
 *
 * Post-refactor responsibilities (PERMITTED):
 * - Domain-level fiscal validations
 * - Aggregate construction and state initialization
 * - Tax strategy execution
 * - Repository persistence
 * - Domain event dispatch
 *
 * Explicitly PROHIBITED:
 * - XML parsing (handled by SatXmlParser in Ingestion)
 * - CFDI classification or ownership detection
 * - Cross-context routing decisions
 * - Calling IngestAndClassifyCfdiUseCase or ProcessRawXmlUseCase
 * - Depending on Ingestion constructs (e.g. RawCfdiDto)
 *
 * This use case now receives a decoupled array of invoice data
 * provided by ProcessIncomeCfdiListener (which acts as an ACL).
 */
class ImportInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface    $invoiceRepository,
        private TaxStrategyFactoryInterface   $taxFactory,
        private TransactionManagerInterface   $transactionManager,
        private EventDispatcherInterface      $eventDispatcher,
    ) {}

    /**
     * @param array{
     *     uuid: Uuid,
     *     emittedAt: DateTimeImmutable,
     *     tipoDeComprobante: string,
     *     metodoPago: string,
     *     subtotal: Money,
     *     total: Money
     * } $invoiceData Decoupled CFDI data from the ACL
     * @param string $taxpayerRegime The fiscal regime of the tenant (e.g., '625')
     */
    public function execute(array $invoiceData, string $taxpayerRegime): void
    {
        // 1. Idempotency: prevent duplicate fiscal processing
        if ($this->invoiceRepository->exists($invoiceData['uuid'])) {
            return;
        }

        // 2. Domain: Initialize the pure Aggregate Root (enforces PUE/PPD cash flow rules)
        $invoice = Invoice::createFromIngestion(
            $invoiceData['uuid'],
            $invoiceData['tipoDeComprobante'],
            $invoiceData['metodoPago'],
            $invoiceData['subtotal'],
            $invoiceData['total'],
        );

        // 3. Tax Engine: resolve the correct strategy and calculate taxes
        $taxStrategy = $this->taxFactory->create($taxpayerRegime, $invoiceData['emittedAt']);
        $taxes = $taxStrategy->calculateTaxes($invoiceData['subtotal']);

        // 4. Domain Integration: attach taxes to the invoice
        foreach ($taxes as $tax) {
            $invoice->addTax($tax);
        }

        // 5. Domain Verification: check for SAT rounding errors or malformed XMLs
        if ($invoice->hasFiscalDiscrepancy()) {
            throw new RuntimeException(
                "Fiscal Discrepancy Detected: XML Total does not match calculated taxes for UUID {$invoiceData['uuid']->getValue()}"
            );
        }

        // 6. Persistence: atomic save
        $this->transactionManager->transaction(function () use ($invoice, $invoiceData) {
            $this->invoiceRepository->save($invoice);
            $this->eventDispatcher->dispatchAll([new InvoiceImportedEvent($invoiceData['uuid'], new \DateTimeImmutable())]);
        });
    }
}
