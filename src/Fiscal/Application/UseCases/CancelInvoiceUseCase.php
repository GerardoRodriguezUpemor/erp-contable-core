<?php

declare(strict_types=1);

namespace App\Fiscal\Application\UseCases;

use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Application\EventDispatcherInterface;
use App\Shared\Domain\ValueObjects\Uuid;
use DomainException;
use RuntimeException;

class CancelInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $invoiceRepository,
        private TransactionManagerInterface $transactionManager,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function execute(Uuid $invoiceUuid): void
    {
        $invoice = $this->invoiceRepository->findById($invoiceUuid);

        if (!$invoice) {
            throw new RuntimeException("Cancellation Failed: Invoice {$invoiceUuid->getValue()} not found in the system.");
        }

        // 1. Enforce SAT Dependency Rules for PPD
        // If the balance due is strictly less than the total, it means payments have been applied.
        if (
            $invoice->getMetodoPago() === 'PPD' && 
            $invoice->getBalanceDue()->getCents() < $invoice->getTotal()->getCents()
        ) {
            throw new DomainException(
                "Cannot cancel a PPD invoice that has active Payment Applications. You must cancel the associated REPs first."
            );
        }

        // 2. Delegate to the Aggregate Root
        // This triggers the internal state machine we built in Phase 3, 
        // changing satStatus to CANCELLED and zeroing out the balance.
        $invoice->markAsCancelledBySat();

        // 3. Atomic Persistence
        $this->transactionManager->transaction(function () use ($invoice) {
            $this->invoiceRepository->save($invoice);
        });

        // Extract and dispatch the events ONLY if the transaction succeeded
        $events = $invoice->releaseEvents();
        $this->eventDispatcher->dispatchAll($events);
    }
}
