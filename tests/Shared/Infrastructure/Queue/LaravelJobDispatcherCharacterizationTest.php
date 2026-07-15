<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Queue;

use App\Shared\Application\JobDispatcherInterface;
use App\Shared\Infrastructure\Queue\LaravelJobDispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/** Characterizes the stabilized asynchronous ingestion boundary. */
final class LaravelJobDispatcherCharacterizationTest extends TestCase
{
    public function test_dispatcher_autoloads_and_implements_the_contract(): void
    {
        $this->assertTrue(class_exists(LaravelJobDispatcher::class));
        $this->assertTrue(is_subclass_of(LaravelJobDispatcher::class, JobDispatcherInterface::class));
    }

    public function test_dispatcher_contract_has_the_single_neutral_ingestion_method(): void
    {
        $method = (new ReflectionClass(LaravelJobDispatcher::class))->getMethod('dispatchIngestCfdi');

        $this->assertSame('void', (string) $method->getReturnType());
        $this->assertCount(1, $method->getParameters());
        $this->assertSame('xmlContent', $method->getParameters()[0]->getName());
        $this->assertSame('string', (string) $method->getParameters()[0]->getType());
        $this->assertFalse(method_exists(LaravelJobDispatcher::class, 'dispatchProcessInvoice'));
    }

    public function test_dispatcher_delegates_the_xml_to_the_injected_queue_mechanism(): void
    {
        $dispatched = [];
        $dispatcher = new LaravelJobDispatcher(
            static function (string $xmlContent) use (&$dispatched): void {
                $dispatched[] = $xmlContent;
            }
        );

        $dispatcher->dispatchIngestCfdi('<cfdi:Comprobante/>');

        $this->assertSame(['<cfdi:Comprobante/>'], $dispatched);
    }

    public function test_shared_dispatcher_has_no_fiscal_job_dependency(): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/src/Shared/Infrastructure/Queue/LaravelJobDispatcher.php');

        $this->assertIsString($source);
        $this->assertStringNotContainsString('App\\Fiscal', $source);
        $this->assertStringNotContainsString('ProcessInvoiceJob', $source);
        $this->assertStringNotContainsString('::dispatch(', $source);
    }

    public function test_broken_fiscal_process_invoice_job_is_removed(): void
    {
        $this->assertFileDoesNotExist(
            dirname(__DIR__, 4) . '/src/Fiscal/Infrastructure/Jobs/ProcessInvoiceJob.php'
        );
    }
}
