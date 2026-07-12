<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Queue;

use App\Shared\Application\JobDispatcherInterface;
use App\Shared\Infrastructure\Queue\LaravelJobDispatcher;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * POST-REFACTOR VALIDATION — LaravelJobDispatcher
 *
 * These tests replace the characterization safety net that froze the legacy
 * behavior (10 tests, 55 assertions). The characterization tests served their
 * purpose: they caught the Fatal Error and documented the exact breakage before
 * we performed the surgical fix.
 *
 * What was fixed:
 * 1. Removed hardcoded `use App\Fiscal\Infrastructure\Jobs\ProcessInvoiceJob`
 *    → Circular dependency (Shared → Fiscal) is broken
 * 2. Replaced `dispatchProcessInvoice(string, string)` with `dispatchIngestCfdi(string)`
 *    → Interface contract is now satisfied
 * 3. Injected Closure for queue dispatch
 *    → Dependency Inversion Principle applied
 * 4. Deleted ProcessInvoiceJob.php
 *    → Dead code removed (couldn't load, couldn't execute)
 *
 * @see docs/characterization-plan.md §B (completed)
 * @see docs/dependency-report.md §1.A (resolved)
 */
class LaravelJobDispatcherCharacterizationTest extends TestCase
{
    // ─── Validation 1: Class Can Be Loaded ──────────────────────────

    /**
     * The previous implementation caused a PHP Fatal Error on class_exists()
     * because it declared `implements JobDispatcherInterface` but did not
     * implement dispatchIngestCfdi(). This test proves the fix works.
     */
    public function test_dispatcher_can_be_autoloaded(): void
    {
        $this->assertTrue(
            class_exists(LaravelJobDispatcher::class),
            'LaravelJobDispatcher must be autoloadable without Fatal Error'
        );
    }

    /**
     * Verifies the interface contract is now properly satisfied.
     */
    public function test_dispatcher_implements_job_dispatcher_interface(): void
    {
        $reflection = new ReflectionClass(LaravelJobDispatcher::class);

        $this->assertTrue(
            $reflection->implementsInterface(JobDispatcherInterface::class),
            'LaravelJobDispatcher must implement JobDispatcherInterface'
        );
    }

    /**
     * Verifies the correct method now exists with the right signature.
     */
    public function test_dispatcher_has_dispatch_ingest_cfdi_method(): void
    {
        $reflection = new ReflectionClass(LaravelJobDispatcher::class);

        $this->assertTrue(
            $reflection->hasMethod('dispatchIngestCfdi'),
            'Dispatcher must have dispatchIngestCfdi method'
        );

        $method = $reflection->getMethod('dispatchIngestCfdi');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'dispatchIngestCfdi must accept exactly 1 parameter');
        $this->assertSame('xmlContent', $params[0]->getName());
        $this->assertSame('string', $params[0]->getType()?->getName());
    }

    /**
     * Verifies the old broken method no longer exists.
     */
    public function test_legacy_dispatch_process_invoice_method_is_removed(): void
    {
        $reflection = new ReflectionClass(LaravelJobDispatcher::class);

        $this->assertFalse(
            $reflection->hasMethod('dispatchProcessInvoice'),
            'Legacy dispatchProcessInvoice must no longer exist'
        );
    }

    // ─── Validation 2: Circular Dependency Is Broken ────────────────

    /**
     * Verifies that Shared no longer imports anything from Fiscal.
     * This is the core assertion that proves the circular dependency is dead.
     */
    public function test_dispatcher_has_zero_fiscal_imports(): void
    {
        $reflection = new ReflectionClass(LaravelJobDispatcher::class);
        $sourceFile = $reflection->getFileName();
        $sourceCode = file_get_contents($sourceFile);

        // Extract only PHP use-statements (not comments or docblocks)
        preg_match_all('/^\s*use\s+(.+);/m', $sourceCode, $matches);
        $imports = $matches[1] ?? [];

        foreach ($imports as $import) {
            $this->assertStringNotContainsString(
                'App\Fiscal',
                $import,
                "LaravelJobDispatcher must not import from Fiscal. Found: use {$import};"
            );
        }
    }

    /**
     * Verifies ProcessInvoiceJob.php no longer exists on disk.
     */
    public function test_process_invoice_job_file_is_deleted(): void
    {
        $jobPath = __DIR__ . '/../../../../src/Fiscal/Infrastructure/Jobs/ProcessInvoiceJob.php';

        $this->assertFileDoesNotExist(
            $jobPath,
            'ProcessInvoiceJob.php must be deleted (dead code: couldn\'t load, couldn\'t execute)'
        );
    }

    // ─── Validation 3: Dependency Inversion Works ───────────────────

    /**
     * Verifies the dispatcher accepts a Closure via constructor injection.
     */
    public function test_dispatcher_constructor_accepts_closure(): void
    {
        $reflection = new ReflectionClass(LaravelJobDispatcher::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(1, $params, 'Constructor must accept exactly 1 parameter');
        $this->assertSame('queuePush', $params[0]->getName());
        $this->assertSame('Closure', $params[0]->getType()?->getName());
    }

    /**
     * Verifies the dispatcher correctly delegates to the injected closure.
     */
    public function test_dispatch_ingest_cfdi_delegates_to_closure(): void
    {
        $dispatched = [];
        $closure = function (string $xml) use (&$dispatched): void {
            $dispatched[] = $xml;
        };

        $dispatcher = new LaravelJobDispatcher($closure);
        $dispatcher->dispatchIngestCfdi('<cfdi:Comprobante/>');

        $this->assertCount(1, $dispatched);
        $this->assertSame('<cfdi:Comprobante/>', $dispatched[0]);
    }

    /**
     * Verifies multiple dispatches accumulate correctly.
     */
    public function test_multiple_dispatches_invoke_closure_independently(): void
    {
        $dispatched = [];
        $closure = function (string $xml) use (&$dispatched): void {
            $dispatched[] = $xml;
        };

        $dispatcher = new LaravelJobDispatcher($closure);
        $dispatcher->dispatchIngestCfdi('<xml>1</xml>');
        $dispatcher->dispatchIngestCfdi('<xml>2</xml>');
        $dispatcher->dispatchIngestCfdi('<xml>3</xml>');

        $this->assertCount(3, $dispatched);
        $this->assertSame('<xml>1</xml>', $dispatched[0]);
        $this->assertSame('<xml>2</xml>', $dispatched[1]);
        $this->assertSame('<xml>3</xml>', $dispatched[2]);
    }
}
