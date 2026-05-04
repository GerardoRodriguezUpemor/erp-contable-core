<?php

declare(strict_types=1);

namespace Tests\Fiscal\Application\UseCases;

use App\Fiscal\Application\UseCases\CancelInvoiceUseCase;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\Enums\LifecycleState;
use App\Fiscal\Domain\Enums\SatStatus;
use App\Fiscal\Domain\Events\InvoiceCancelledEvent;
use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Shared\Application\EventDispatcherInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CancelInvoiceUseCaseTest extends TestCase
{
    private InvoiceRepositoryInterface $invoiceRepository;
    private TransactionManagerInterface $transactionManager;
    private EventDispatcherInterface $eventDispatcher;
    private CancelInvoiceUseCase $sut;

    protected function setUp(): void
    {
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
        
        $this->transactionManager->expects($this->any())
            ->method('transaction')
            ->willReturnCallback(fn(callable $callback) => $callback());

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->sut = new CancelInvoiceUseCase(
            $this->invoiceRepository,
            $this->transactionManager,
            $this->eventDispatcher
        );
    }

    public function test_it_throws_exception_if_invoice_not_found(): void
    {
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');

        $this->invoiceRepository->expects($this->once())
            ->method('findById')
            ->with($uuid)
            ->willReturn(null);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not found in the system');

        $this->sut->execute($uuid);
    }

    public function test_it_throws_exception_if_ppd_invoice_has_payments_applied(): void
    {
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');
        
        // Total = 10,000, Balance = 5,000 => Applied Payment
        $invoice = $this->createPartialMock(Invoice::class, ['getMetodoPago', 'getBalanceDue', 'getTotal']);
        $invoice->method('getMetodoPago')->willReturn('PPD');
        $invoice->method('getBalanceDue')->willReturn(new Money(500000));
        $invoice->method('getTotal')->willReturn(new Money(1000000));

        $this->invoiceRepository->expects($this->once())
            ->method('findById')
            ->with($uuid)
            ->willReturn($invoice);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot cancel a PPD invoice that has active Payment Applications');

        $this->sut->execute($uuid);
    }

    public function test_it_successfully_cancels_and_dispatches_event(): void
    {
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');

        $invoice = Invoice::createFromIngestion(
            $uuid,
            'I',
            'PUE',
            new Money(100000),
            new Money(116000)
        );

        $invoice->addTax(new \App\Fiscal\Domain\ValueObjects\Tax('IVA', \App\Fiscal\Domain\Enums\TaxCategory::TRANSFERRED, new Money(16000), 0.16));

        $this->invoiceRepository->expects($this->once())
            ->method('findById')
            ->with($uuid)
            ->willReturn($invoice);

        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Invoice $inv) use ($uuid) {
                return $inv->getUuid()->getValue() === $uuid->getValue()
                    && !$inv->hasFiscalDiscrepancy()
                    && $inv->getBalanceDue()->getCents() === 0; // Cancelled zeros out balance
            }));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->with($this->callback(function (array $events) {
                return count($events) === 1 && $events[0] instanceof InvoiceCancelledEvent;
            }));

        $this->sut->execute($uuid);
    }
}

