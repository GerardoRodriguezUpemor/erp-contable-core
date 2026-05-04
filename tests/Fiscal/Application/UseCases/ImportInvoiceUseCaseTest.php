<?php

declare(strict_types=1);

namespace Tests\Fiscal\Application\UseCases;

use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\Events\InvoiceImportedEvent;
use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyFactoryInterface;
use App\Fiscal\Domain\Services\TaxStrategies\TaxStrategyInterface;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Ingestion\Application\DTOs\RawInvoiceDto;
use App\Ingestion\Application\UseCases\ProcessRawXmlUseCase;
use App\Shared\Application\EventDispatcherInterface;
use App\Shared\Application\TransactionManagerInterface;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImportInvoiceUseCaseTest extends TestCase
{
    private ProcessRawXmlUseCase $ingestionUseCase;
    private InvoiceRepositoryInterface $invoiceRepository;
    private TaxStrategyFactoryInterface $taxFactory;
    private TransactionManagerInterface $transactionManager;
    private EventDispatcherInterface $eventDispatcher;
    private ImportInvoiceUseCase $sut;

    protected function setUp(): void
    {
        $this->ingestionUseCase = $this->createMock(ProcessRawXmlUseCase::class);
        $this->invoiceRepository = $this->createMock(InvoiceRepositoryInterface::class);
        $this->taxFactory = $this->createMock(TaxStrategyFactoryInterface::class);
        
        $this->transactionManager = $this->createMock(TransactionManagerInterface::class);
        $this->transactionManager->expects($this->any())
            ->method('transaction')
            ->willReturnCallback(fn(callable $callback) => $callback());
            
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->sut = new ImportInvoiceUseCase(
            $this->ingestionUseCase,
            $this->invoiceRepository,
            $this->taxFactory,
            $this->transactionManager,
            $this->eventDispatcher
        );
    }

    public function test_it_aborts_import_if_invoice_already_exists(): void
    {
        $dto = new RawInvoiceDto(
            uuid: new Uuid('11111111-2222-3333-4444-555555555555'),
            emittedAt: new DateTimeImmutable('2026-04-20 10:00:00'),
            tipoDeComprobante: 'I',
            metodoPago: 'PUE',
            subtotal: new Money(100000),
            total: new Money(116000),
            emisorRfc: 'AAA010101AAA',
            receptorRfc: 'XXX010101XXX'
        );

        $this->ingestionUseCase->expects($this->once())
            ->method('execute')
            ->with('<xml>fake</xml>')
            ->willReturn($dto);

        $this->invoiceRepository->expects($this->once())
            ->method('exists')
            ->with($dto->uuid)
            ->willReturn(true);

        $this->invoiceRepository->expects($this->never())->method('save');
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $this->sut->execute('<xml>fake</xml>', '625');
    }

    public function test_it_throws_exception_if_fiscal_discrepancy_exists(): void
    {
        $dto = new RawInvoiceDto(
            uuid: new Uuid('11111111-2222-3333-4444-555555555555'),
            emittedAt: new DateTimeImmutable('2026-04-20 10:00:00'),
            tipoDeComprobante: 'I',
            metodoPago: 'PUE',
            subtotal: new Money(100000),
            total: new Money(116000), 
            emisorRfc: 'AAA010101AAA',
            receptorRfc: 'XXX010101XXX'
        );

        $this->ingestionUseCase->expects($this->once())
            ->method('execute')->willReturn($dto);

        $this->invoiceRepository->expects($this->once())
            ->method('exists')->willReturn(false);

        $taxStrategy = $this->createMock(TaxStrategyInterface::class);
        $taxStrategy->expects($this->once())
            ->method('calculateTaxes')
            ->willReturn([
                new Tax('IVA', TaxCategory::TRANSFERRED, new Money(50000), 0.16) 
            ]);

        $this->taxFactory->expects($this->once())
            ->method('create')->willReturn($taxStrategy);

        $this->invoiceRepository->expects($this->never())->method('save');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fiscal Discrepancy Detected');

        $this->sut->execute('<xml>fake</xml>', '625');
    }

    public function test_it_successfully_imports_and_dispatches_event(): void
    {
        $dto = new RawInvoiceDto(
            uuid: new Uuid('11111111-2222-3333-4444-555555555555'),
            emittedAt: new DateTimeImmutable('2026-04-20 10:00:00'),
            tipoDeComprobante: 'I',
            metodoPago: 'PUE',
            subtotal: new Money(100000),
            total: new Money(116000), 
            emisorRfc: 'AAA010101AAA',
            receptorRfc: 'XXX010101XXX'
        );

        $this->ingestionUseCase->expects($this->once())
            ->method('execute')->willReturn($dto);

        $this->invoiceRepository->expects($this->once())
            ->method('exists')->willReturn(false);

        $taxStrategy = $this->createMock(TaxStrategyInterface::class);
        $taxStrategy->expects($this->once())
            ->method('calculateTaxes')
            ->willReturn([
                new Tax('IVA', TaxCategory::TRANSFERRED, new Money(16000), 0.16)
            ]);

        $this->taxFactory->expects($this->once())
            ->method('create')->willReturn($taxStrategy);

        $this->invoiceRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Invoice $invoice) {
                return $invoice->getUuid()->getValue() === '11111111-2222-3333-4444-555555555555'
                    && !$invoice->hasFiscalDiscrepancy();
            }));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchAll')
            ->with($this->callback(function (array $events) {
                return count($events) === 1 && $events[0] instanceof InvoiceImportedEvent;
            }));

        $this->sut->execute('<xml>fake</xml>', '625');
    }
}

