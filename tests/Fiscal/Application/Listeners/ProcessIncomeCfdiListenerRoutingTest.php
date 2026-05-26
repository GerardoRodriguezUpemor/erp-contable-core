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

/**
 * Characterization tests for the Listener-as-Router contract.
 *
 * These tests document and freeze the routing behavior of ProcessIncomeCfdiListener
 * as it stands today, before the architectural refactoring into Sales/Expenses
 * sub-domains. They will need to be updated when the routing splits into
 * ImportSaleUseCase and ImportExpenseUseCase.
 *
 * Key insight being tested: today both INCOME_ISSUED (sale) and INCOME_RECEIVED
 * (purchase/expense) route to the SAME ImportInvoiceUseCase. This is the
 * architectural gap we identified — the listener does not distinguish between
 * them. These tests make that gap explicit and measurable.
 */
class ProcessIncomeCfdiListenerRoutingTest extends TestCase
{
    private InMemoryRawCfdiStagingRepository $stagingRepo;
    private ImportInvoiceUseCase $importUseCase;
    private TenantContextInterface $tenantContext;
    private ProcessIncomeCfdiListener $sut;

    private Uuid $cfdiDocumentId;

    protected function setUp(): void
    {
        $this->stagingRepo   = new InMemoryRawCfdiStagingRepository();
        $this->importUseCase = $this->createMock(ImportInvoiceUseCase::class);
        $this->tenantContext = $this->createMock(TenantContextInterface::class);
        $this->tenantContext->method('getCurrentRegime')->willReturn('625');

        $rawDto = new RawCfdiDto(
            uuid:              new Uuid('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            emittedAt:         new DateTimeImmutable('2026-05-01'),
            documentType:      SatDocumentType::INVOICE,
            tipoDeComprobante: 'I',
            metodoPago:        'PUE',
            subtotal:          new Money(100000),
            total:             new Money(116000),
            emisorRfc:         'SELLER000000AAA',
            receptorRfc:       'TENANT000000BBB',
        );

        $this->cfdiDocumentId = $this->stagingRepo->persist($rawDto, '00000000-0000-4000-8000-000000000001');

        $this->sut = new ProcessIncomeCfdiListener(
            $this->stagingRepo,
            $this->importUseCase,
            $this->tenantContext,
        );
    }

    private function makeEvent(CfdiOwnershipCategory $category): ClassifiedCfdiIngestedIntegrationEvent
    {
        return new ClassifiedCfdiIngestedIntegrationEvent(
            cfdiDocumentId:         $this->cfdiDocumentId,
            tenantId:               '00000000-0000-4000-8000-000000000001',
            classificationCategory: $category,
            occurredOn:             new DateTimeImmutable(),
        );
    }

    /**
     * CHARACTERIZATION: Today, both INCOME_ISSUED and INCOME_RECEIVED route to
     * the same use case. This test freezes that current (flawed) behavior.
     *
     * POST-REFACTOR: This test should be DELETED and replaced by two separate
     * tests — one for ImportSaleUseCase, one for ImportExpenseUseCase.
     */
    public function test_current_behavior_routes_both_issued_and_received_to_same_use_case(): void
    {
        // Both events hit execute() exactly once each.
        $this->importUseCase
            ->expects($this->exactly(2))
            ->method('execute');

        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_ISSUED));
        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_RECEIVED));
    }

    /**
     * The listener MUST pass the regime from TenantContext to the use case.
     * If TenantContext is wrong, the wrong tax strategy is applied to the invoice.
     */
    public function test_listener_passes_regime_from_tenant_context_to_use_case(): void
    {
        $this->importUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RawCfdiDto::class),
                '625'
            );

        $this->sut->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_ISSUED));
    }

    /**
     * Validates that the regime string is passed as-is from TenantContext.
     * A tenant with regime '612' should receive a '612' strategy, not '625'.
     */
    public function test_listener_propagates_correct_regime_for_non_625_tenant(): void
    {
        $tenantCtx = $this->createMock(TenantContextInterface::class);
        $tenantCtx->method('getCurrentRegime')->willReturn('612');

        $listener = new ProcessIncomeCfdiListener(
            $this->stagingRepo,
            $this->importUseCase,
            $tenantCtx,
        );

        $this->importUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(
                $this->isInstanceOf(RawCfdiDto::class),
                '612'
            );

        $listener->handle($this->makeEvent(CfdiOwnershipCategory::INCOME_ISSUED));
    }

    /**
     * Validates that even if a third_party event slips through, the use case is
     * never called. This is the Zero Data Loss guarantee from hidden-business-rules.md:
     * THIRD_PARTY events go to staging + flagForReview, NOT to Fiscal.
     */
    public function test_all_non_income_categories_are_dropped_silently(): void
    {
        $droppedCategories = [
            CfdiOwnershipCategory::TRANSFER,
            CfdiOwnershipCategory::THIRD_PARTY,
            CfdiOwnershipCategory::SELF_INVOICE,
            CfdiOwnershipCategory::PAYROLL_ISSUED,
            CfdiOwnershipCategory::PAYROLL_RECEIVED,
            CfdiOwnershipCategory::PAYMENT_COMPLEMENT_ISSUED,
            CfdiOwnershipCategory::PAYMENT_COMPLEMENT_RECEIVED,
        ];

        $this->importUseCase->expects($this->never())->method('execute');

        foreach ($droppedCategories as $category) {
            $this->sut->handle($this->makeEvent($category));
        }
    }
}
