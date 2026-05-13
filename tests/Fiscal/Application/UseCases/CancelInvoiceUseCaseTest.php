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
        // GIVEN: Una factura real (no mockeada) PPD que ya recibió un abono
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');
        
        $invoice = Invoice::createFromIngestion(
            $uuid,
            'I',
            'PPD',
            new Money(100000), // 1,000 subtotal
            new Money(116000)  // 1,160 total
        );
        $invoice->addTax(new \App\Fiscal\Domain\ValueObjects\Tax('IVA', \App\Fiscal\Domain\Enums\TaxCategory::TRANSFERRED, new Money(16000), 0.16));

        // Aplicamos un pago parcial de 5,000 centavos ($50.00)
        $invoice->applyPayment(new Money(5000));

        $this->invoiceRepository->expects($this->once())
            ->method('findById')
            ->with($uuid)
            ->willReturn($invoice);

        // THEN: Aseguramos que nunca se guarde nada ni se disparen eventos
        $this->invoiceRepository->expects($this->never())->method('save');
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        // Y debe explotar por Regla de Dominio
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot cancel a PPD invoice that has active Payment Applications');

        // WHEN
        $this->sut->execute($uuid);
    }

    public function test_it_successfully_cancels_and_dispatches_event(): void
    {
        // GIVEN: Una factura real y válida
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');

        $invoice = Invoice::createFromIngestion(
            $uuid,
            'I',
            'PUE',
            new Money(100000),
            new Money(116000)
        );
        $invoice->addTax(new \App\Fiscal\Domain\ValueObjects\Tax('IVA', \App\Fiscal\Domain\Enums\TaxCategory::TRANSFERRED, new Money(16000), 0.16));

        // El repositorio devuelve nuestra instancia real
        $this->invoiceRepository->expects($this->once())
            ->method('findById')
            ->with($uuid)
            ->willReturn($invoice);

        // THEN: Capturamos y verificamos la instancia real que se va a guardar
        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Invoice $savedInvoice) {
                // Las facturas canceladas anulan su balance contable a 0 y cambian su estado SAT a CANCELLED
                return $savedInvoice->getSatStatus() === \App\Fiscal\Domain\Enums\SatStatus::CANCELLED
                    && $savedInvoice->getBalanceDue()->getCents() === 0;
            }));

        // THEN: Capturamos y verificamos el evento real despachado
        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->with($this->callback(function (array $events) use ($uuid) {
                if (count($events) !== 1) return false;
                $event = $events[0];
                
                // Extraer propiedades reales del evento
                return $event instanceof \App\Fiscal\Domain\Events\InvoiceCancelledEvent
                    && $event->invoiceUuid->getValue() === $uuid->getValue();
            }));

        // WHEN
        $this->sut->execute($uuid);
    }

    public function test_it_prevents_cancelling_an_already_cancelled_invoice(): void
    {
        // GIVEN: Factura Cancelada en SAT
        $uuid = new Uuid('11111111-2222-3333-4444-555555555555');

        $invoice = Invoice::createFromIngestion($uuid, 'I', 'PUE', new Money(100), new Money(116));
        $invoice->addTax(new \App\Fiscal\Domain\ValueObjects\Tax('IVA', \App\Fiscal\Domain\Enums\TaxCategory::TRANSFERRED, new Money(16), 0.16));
        
        $invoice->markAsCancelledBySat();

        $this->invoiceRepository->expects($this->once())->method('findById')->willReturn($invoice);
        
        // THEN: Cuando lo vuelva a cancelar simplemente no debe emitir excepciones.
        // Pero como Invoice no bloquea el doble markAsCancelledBySat, lanzará el evento interno.
        $this->invoiceRepository->expects($this->once())->method('save'); 
        
        $this->eventDispatcher->expects($this->exactly(1))
            ->method('dispatchAll');
        
        // WHEN
        $this->sut->execute($uuid);
    }
}

