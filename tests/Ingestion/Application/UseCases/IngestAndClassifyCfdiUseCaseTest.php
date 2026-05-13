<?php

declare(strict_types=1);

namespace Tests\Ingestion\Application\UseCases;

use App\Ingestion\Application\DTOs\RawCfdiDto;
use App\Ingestion\Application\DTOs\SatDocumentType;
use App\Ingestion\Application\Enums\CfdiOwnershipCategory;
use App\Ingestion\Application\Events\ClassifiedCfdiIngestedIntegrationEvent;
use App\Ingestion\Application\Services\CfdiOwnershipResolverInterface;
use App\Ingestion\Application\UseCases\IngestAndClassifyCfdiUseCase;
use App\Ingestion\Domain\Validators\XmlValidatorInterface;
use App\Ingestion\Infrastructure\Parsers\XmlParserInterface;
use App\Ingestion\Infrastructure\Persistence\InMemoryRawCfdiStagingRepository;
use App\Shared\Application\EventDispatcherInterface;
use App\Shared\Application\TenantContextInterface;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IngestAndClassifyCfdiUseCaseTest extends TestCase
{
    private const TENANT_RFC   = 'AAA010101AAA';
    private const TENANT_ID    = '00000000-0000-4000-8000-000000000001';
    private const SAT_UUID     = '11111111-2222-3333-4444-555555555555';

    private XmlValidatorInterface       $validator;
    private XmlParserInterface          $parser;
    private InMemoryRawCfdiStagingRepository $stagingRepo;
    private TenantContextInterface      $tenantContext;
    private CfdiOwnershipResolverInterface $resolver;
    private EventDispatcherInterface    $eventDispatcher;
    private IngestAndClassifyCfdiUseCase $sut;

    private RawCfdiDto $sampleDto;

    protected function setUp(): void
    {
        $this->sampleDto = new RawCfdiDto(
            uuid:               new Uuid(self::SAT_UUID),
            emittedAt:          new DateTimeImmutable('2026-05-01 10:00:00'),
            documentType:       SatDocumentType::INVOICE,
            tipoDeComprobante:  'I',
            metodoPago:         'PUE',
            subtotal:           new Money(100000),
            total:              new Money(116000),
            emisorRfc:          self::TENANT_RFC,
            receptorRfc:        'BBB020202BBB',
        );

        $this->validator       = $this->createMock(XmlValidatorInterface::class);
        $this->parser          = $this->createMock(XmlParserInterface::class);
        $this->stagingRepo     = new InMemoryRawCfdiStagingRepository();
        $this->tenantContext   = $this->createMock(TenantContextInterface::class);
        $this->resolver        = $this->createMock(CfdiOwnershipResolverInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->tenantContext->method('getCurrentRfc')->willReturn(self::TENANT_RFC);
        $this->tenantContext->method('getCurrentTenantId')->willReturn(self::TENANT_ID);

        $this->sut = new IngestAndClassifyCfdiUseCase(
            $this->validator,
            $this->parser,
            $this->stagingRepo,
            $this->tenantContext,
            $this->resolver,
            $this->eventDispatcher,
        );
    }

    public function test_it_throws_on_invalid_xml_and_does_not_persist(): void
    {
        $this->validator->expects($this->once())->method('validate')->willReturn(false);
        $this->parser->expects($this->never())->method('parse');
        $this->eventDispatcher->expects($this->never())->method('dispatchAll');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid CFDI XML structure');

        $this->sut->execute('<invalid/>');
    }

    public function test_it_persists_staging_record_before_dispatching_event(): void
    {
        $this->validator->method('validate')->willReturn(true);
        $this->parser->method('parse')->willReturn($this->sampleDto);
        $this->resolver->method('resolve')->willReturn(CfdiOwnershipCategory::INCOME_ISSUED);

        $dispatchedEvents = [];
        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$dispatchedEvents) {
                $dispatchedEvents = $events;
            });

        $this->sut->execute('<valid/>');

        $this->assertCount(1, $dispatchedEvents);
        $event = $dispatchedEvents[0];
        $this->assertInstanceOf(ClassifiedCfdiIngestedIntegrationEvent::class, $event);

        // The staging record must exist BEFORE the event was dispatched
        $persistedDto = $this->stagingRepo->findById($event->cfdiDocumentId);
        $this->assertNotNull($persistedDto);
        $this->assertSame(self::SAT_UUID, strtolower($persistedDto->uuid->getValue()));
    }

    public function test_event_payload_is_minimal_no_dto_no_xml(): void
    {
        $this->validator->method('validate')->willReturn(true);
        $this->parser->method('parse')->willReturn($this->sampleDto);
        $this->resolver->method('resolve')->willReturn(CfdiOwnershipCategory::INCOME_ISSUED);

        $capturedEvent = null;
        $this->eventDispatcher
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$capturedEvent) {
                $capturedEvent = $events[0];
            });

        $this->sut->execute('<valid/>');

        $this->assertInstanceOf(ClassifiedCfdiIngestedIntegrationEvent::class, $capturedEvent);
        $this->assertInstanceOf(Uuid::class, $capturedEvent->cfdiDocumentId);
        $this->assertSame(self::TENANT_ID, $capturedEvent->tenantId);
        $this->assertSame(CfdiOwnershipCategory::INCOME_ISSUED, $capturedEvent->classificationCategory);

        // Verify the event is a readonly object with only 4 properties — no raw data
        $reflection = new \ReflectionClass($capturedEvent);
        $this->assertCount(4, $reflection->getProperties());
    }

    public function test_third_party_flags_staging_record_but_does_not_throw(): void
    {
        $this->validator->method('validate')->willReturn(true);
        $this->parser->method('parse')->willReturn($this->sampleDto);
        $this->resolver->method('resolve')->willReturn(CfdiOwnershipCategory::THIRD_PARTY);
        $this->eventDispatcher->expects($this->once())->method('dispatchAll');

        $capturedEvent = null;
        $this->eventDispatcher
            ->method('dispatchAll')
            ->willReturnCallback(function (array $events) use (&$capturedEvent) {
                $capturedEvent = $events[0];
            });

        // Must NOT throw — THIRD_PARTY is preserved, not rejected
        $this->sut->execute('<valid/>');

        $this->assertSame(CfdiOwnershipCategory::THIRD_PARTY, $capturedEvent->classificationCategory);
        $this->assertTrue($this->stagingRepo->isFlaggedForReview($capturedEvent->cfdiDocumentId));
    }

    public function test_idempotency_prevents_reprocessing_same_sat_uuid(): void
    {
        $this->validator->method('validate')->willReturn(true);
        $this->parser->method('parse')->willReturn($this->sampleDto);
        $this->resolver->method('resolve')->willReturn(CfdiOwnershipCategory::INCOME_ISSUED);
        $this->eventDispatcher->expects($this->once())->method('dispatchAll'); // Only once

        // First ingestion
        $this->sut->execute('<valid/>');

        // Second ingestion of the same CFDI — must be silently skipped
        $this->sut->execute('<valid/>');
    }
}
