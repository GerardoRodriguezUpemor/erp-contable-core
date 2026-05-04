<?php

declare(strict_types=1);

namespace App\Fiscal\Application\UseCases;

use App\Ingestion\Application\UseCases\ProcessRawXmlUseCase;
use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyFactoryInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Application\EventDispatcherInterface;
use App\Fiscal\Domain\Events\InvoiceImportedEvent;
use App\Fiscal\Domain\Entities\Invoice;
use RuntimeException;

class ImportInvoiceUseCase
{
    public function __construct(
        private ProcessRawXmlUseCase $ingestionUseCase,
        private InvoiceRepositoryInterface $invoiceRepository,
        private TaxStrategyFactoryInterface $taxFactory,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @param string $xmlContent The raw CFDI file string
     * @param string $taxpayerRegime The regime of the user uploading the file (e.g., '625')
     */
    public function execute(string $xmlContent, string $taxpayerRegime): void
    {
        // 1. Ingestion: Validate XSD and parse into a strictly typed DTO
        $dto = $this->ingestionUseCase->execute($xmlContent);

        // 2. Idempotency: Prevent duplicate processing immediately
        if ($this->invoiceRepository->exists($dto->uuid)) {
            return; 
        }

        // 3. Domain: Initialize the pure Aggregate Root (Enforces PUE/PPD cash flow rules)
        $invoice = Invoice::createFromIngestion(
            $dto->uuid,
            $dto->tipoDeComprobante,
            $dto->metodoPago,
            $dto->subtotal,
            $dto->total
        );

        // 4. Tax Engine: Resolve the correct strategy and calculate taxes
        $taxStrategy = $this->taxFactory->create($taxpayerRegime, $dto->emittedAt);
        $taxes = $taxStrategy->calculateTaxes($dto->subtotal);

        // 5. Domain Integration: Attach taxes to the invoice
        foreach ($taxes as $tax) {
            $invoice->addTax($tax);
        }

        // 6. Domain Verification: Check for SAT rounding errors or malformed XMLs
        if ($invoice->hasFiscalDiscrepancy()) {
            // In a strict ERP, we reject mathematically invalid documents entirely.
            // Alternatively, you could save it but flag its state as 'REQUIRES_MANUAL_REVIEW'.
            throw new RuntimeException(
                "Fiscal Discrepancy Detected: XML Total does not match calculated taxes for UUID {$dto->uuid->getValue()}"
            );
        }

        // 7. Persistence: Atomic Save
        $this->transactionManager->transaction(function () use ($invoice, $dto) {
            $this->invoiceRepository->save($invoice);
            $this->eventDispatcher->dispatchAll([new InvoiceImportedEvent($dto->uuid, new \DateTimeImmutable())]);
            
            // Note: In a full production environment, you would also trigger 
            // a StorageService here to upload the original $xmlContent to an S3 bucket.
        });
    }
}
