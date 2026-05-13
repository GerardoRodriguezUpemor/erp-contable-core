<?php

declare(strict_types=1);

namespace Tests\Fiscal\Application\Listeners;

use App\Fiscal\Application\Listeners\ProcessIncomeCfdiListener;
use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;
use App\Ingestion\Application\DTOs\RawCfdiDto;
use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Events\ClassifiedCfdiIngestedIntegrationEvent;
use App\Ingestion\Infrastructure\Persistence\InMemoryRawCfdiStagingRepository;
use App\Shared\Application\TenantContextInterface;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ProcessIncomeCfdiListenerTest extends TestCase
{
    private InMemoryRawCfdiStagingRepository $stagingRepo;
    private ImportInvoiceUseCase $importUseCase;
    private TenantContextInterface $tenantContext;
    private ProcessIncomeCfdiListener $sut;

    private Uuid $cfdiDocumentId;
    private RawCfdiDto $rawDto;

    protected function setUp(): void
    {
        $this->stagingRepo   = new InMemoryRawCfdiStagingRepository();
        $this->importUseCase = $this->createMock(ImportInvoiceUseCase::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);

        $this->tenantContext->method('getCurrentRegime')->willReturn('625');

        $this->rawDto = new RawCfdiDto(
            uuid:               new Uuid('11111111-2222-3333-4444-555555555555'),
            emittedAt:          new DateTimeImmutable('2026-05-01 10:00:00'),
            documentType:       SatDocumentType::INVOICE,
            tipoDeComprobante:  'I',
            metodoPago:         'PUE',
            subtotal:           new Money(100000),
            total:              new Money(116000),
            emisorRfc:          'AAA010101AAA',
            receptorRfc:        'BBB020202BBB',
        );

        // Pre-load the staging repo with the DTO
        $this->cfdiDocumentId = $this->stagingRepo->persist($this->rawDto, '00000000-0000-4000-8000-000000000001');

        $this->sut = new ProcessIncomeCfdiListener(
            $this->stagingRepo,
            $this->importUseCase,
            $this->tenantContext,
        );
    }

    private function makeEvent(CfdiOwnershipCategory $category): ClassifiedCfdiIngestedIntegrationEvent
    {
        return new ClassifiedCfdiIngestedIntegrationEvent(
            cfdiDocumentId:        $this->cfdiDocumentId,
            tenantId:              '00000000-0000-4000-8000-000000000001',
            classificationCategory: $category,
            occurredOn:            new DateTimeImmutable(),
        );
    }

    // --- Guard clause tests ---

    public function test_it_ignores_transfer_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::TRANSFER));
    }

    public function test_it_ignores_third_party_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::THIRD_PARTY));
    }

    public function test_it_ignores_self_invoice_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::SELF_INVOICE));
    }

    public function test_it_ignores_payroll_issued_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::PAYROLL_ISSUED));
    }

    public function test_it_ignores_payroll_received_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::PAYROLL_RECEIVED));
    }

    public function test_it_ignores_payment_complement_issued_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::PAYMENT_COMPLEMENT_ISSUED));
    }

    public function test_it_ignores_payment_complement_received_events(): void
    {
        $this->importUseCase->expects($this->never())->method('execute');
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::PAYMENT_COMPLEMENT_RECEIVED));
    }

    // --- Income processing tests ---

    public function test_it_hydrates_and_delegates_for_income_issued(): void
    {
        $this->importUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn(RawCfdiDto $dto) => $dto->emisorRfc === 'AAA010101AAA'),
                '625'
            );

        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_ISSUED));
    }

    public function test_it_hydrates_and_delegates_for_income_received(): void
    {
        $this->importUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(fn(RawCfdiDto $dto) => $dto->receptorRfc === 'BBB020202BBB'),
                '625'
            );

        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_RECEIVED));
    }

    public function test_it_throws_when_staging_record_not_found(): void
    {
        $missingId = new Uuid('99999999-9999-4999-9999-999999999999');
        $event = new ClassifiedCfdiIngestedIntegrationEvent(
            cfdiDocumentId:        $missingId,
            tenantId:              '00000000-0000-4000-8000-000000000001',
            classificationCategory: CfdiOwnershipCategory::INCOME_ISSUED,
            occurredOn:            new DateTimeImmutable(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Hydration failed');

        $this->sut->handle($event);
    }
}
