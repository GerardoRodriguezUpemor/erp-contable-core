<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Queue;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * CHARACTERIZATION TEST - DO NOT UPDATE WITH THE PRODUCTION REFACTOR.
 *
 * This safety net deliberately freezes the broken legacy wiring as source text.
 * The production classes are not autoloaded because LaravelJobDispatcher does
 * not currently satisfy JobDispatcherInterface and ProcessInvoiceJob depends on
 * Laravel queue classes that are not installed by this project.
 */
final class LaravelJobDispatcherCharacterizationTest extends TestCase
{
    private string $projectRoot;
    private string $dispatcherPath;
    private string $dispatcherSource;
    private string $interfaceSource;
    private string $jobSource;
    private string $useCaseSource;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 4);
        $this->dispatcherPath = $this->projectRoot . '/src/Shared/Infrastructure/Queue/LaravelJobDispatcher.php';
        $this->dispatcherSource = $this->readSource($this->dispatcherPath);
        $this->interfaceSource = $this->readSource(
            $this->projectRoot . '/src/Shared/Application/JobDispatcherInterface.php'
        );
        $this->jobSource = $this->readSource(
            $this->projectRoot . '/src/Fiscal/Infrastructure/Jobs/ProcessInvoiceJob.php'
        );
        $this->useCaseSource = $this->readSource(
            $this->projectRoot . '/src/Fiscal/Application/UseCases/ImportInvoiceUseCase.php'
        );
    }

    public function test_dispatcher_file_exists_at_expected_path(): void
    {
        $expectedPath = $this->projectRoot . '/src/Shared/Infrastructure/Queue/LaravelJobDispatcher.php';

        $this->assertFileExists($expectedPath);
        $this->assertIsReadable($expectedPath);
        $this->assertSame($this->normalizePath($expectedPath), $this->normalizePath($this->dispatcherPath));
    }

    public function test_dispatcher_namespace_is_shared_infrastructure_queue(): void
    {
        $this->assertStringContainsString(
            'namespace App\Shared\Infrastructure\Queue;',
            $this->dispatcherSource
        );
        $this->assertMatchesRegularExpression(
            '/namespace\s+App\\\\Shared\\\\Infrastructure\\\\Queue\s*;/',
            $this->dispatcherSource
        );
        $this->assertStringNotContainsString('namespace App\Fiscal', $this->dispatcherSource);
    }

    public function test_dispatcher_declares_job_dispatcher_interface_implementation(): void
    {
        $this->assertStringContainsString(
            'use App\Shared\Application\JobDispatcherInterface;',
            $this->dispatcherSource
        );
        $this->assertMatchesRegularExpression(
            '/class\s+LaravelJobDispatcher\s+implements\s+JobDispatcherInterface/',
            $this->dispatcherSource
        );
        $this->assertSame(1, substr_count($this->dispatcherSource, 'implements JobDispatcherInterface'));
        $this->assertSame(1, substr_count($this->dispatcherSource, 'class LaravelJobDispatcher'));
    }

    public function test_broken_method_name_contract_is_frozen(): void
    {
        $this->assertMatchesRegularExpression(
            '/public\s+function\s+dispatchIngestCfdi\s*\(/',
            $this->interfaceSource
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public\s+function\s+dispatchIngestCfdi\s*\(/',
            $this->dispatcherSource
        );
        $this->assertMatchesRegularExpression(
            '/public\s+function\s+dispatchProcessInvoice\s*\(/',
            $this->dispatcherSource
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public\s+function\s+dispatchProcessInvoice\s*\(/',
            $this->interfaceSource
        );
        $this->assertSame(1, substr_count($this->interfaceSource, 'dispatchIngestCfdi'));
        $this->assertSame(1, substr_count($this->dispatcherSource, 'dispatchProcessInvoice'));
    }

    public function test_legacy_dispatch_method_has_two_string_parameters(): void
    {
        $matched = preg_match(
            '/public\s+function\s+dispatchProcessInvoice\s*\(\s*string\s+\$(xmlContent)\s*,\s*string\s+\$(taxpayerRegime)\s*\)\s*:\s*(void)/s',
            $this->dispatcherSource,
            $matches
        );

        $this->assertSame(1, $matched);
        $this->assertSame('xmlContent', $matches[1]);
        $this->assertSame('taxpayerRegime', $matches[2]);
        $this->assertSame('void', $matches[3]);
        $this->assertStringContainsString(
            'dispatchProcessInvoice(string $xmlContent, string $taxpayerRegime): void',
            $this->dispatcherSource
        );
        $this->assertSame(1, substr_count($this->dispatcherSource, 'public function dispatchProcessInvoice'));
        $this->assertStringNotContainsString('dispatchProcessInvoice(?string', $this->dispatcherSource);
    }

    public function test_dispatcher_imports_process_invoice_job_from_fiscal(): void
    {
        $legacyImport = 'use App\Fiscal\Infrastructure\Jobs\ProcessInvoiceJob;';

        $this->assertStringContainsString($legacyImport, $this->dispatcherSource);
        $this->assertMatchesRegularExpression(
            '/^use\s+App\\\\Fiscal\\\\Infrastructure\\\\Jobs\\\\ProcessInvoiceJob\s*;/m',
            $this->dispatcherSource
        );
        $this->assertStringContainsString('namespace App\Shared', $this->dispatcherSource);
        $this->assertStringContainsString('App\Fiscal\Infrastructure\Jobs', $this->dispatcherSource);
        $this->assertSame(1, substr_count($this->dispatcherSource, $legacyImport));
    }

    public function test_dispatcher_hardcodes_static_process_invoice_job_dispatch(): void
    {
        $matched = preg_match(
            '/ProcessInvoiceJob::dispatch\s*\(\s*\$(xmlContent)\s*,\s*\$(taxpayerRegime)\s*\)\s*;/',
            $this->dispatcherSource,
            $matches
        );

        $this->assertSame(1, $matched);
        $this->assertSame('xmlContent', $matches[1]);
        $this->assertSame('taxpayerRegime', $matches[2]);
        $this->assertStringContainsString(
            'ProcessInvoiceJob::dispatch($xmlContent, $taxpayerRegime);',
            $this->dispatcherSource
        );
        $this->assertSame(1, substr_count($this->dispatcherSource, 'ProcessInvoiceJob::dispatch'));
    }

    public function test_process_invoice_job_constructor_payload_is_two_private_strings(): void
    {
        $matched = preg_match(
            '/public\s+function\s+__construct\s*\(\s*(private)\s+(string)\s+\$(xmlContent)\s*,\s*(private)\s+(string)\s+\$(taxpayerRegime)\s*\)/s',
            $this->jobSource,
            $matches
        );

        $this->assertSame(1, $matched);
        $this->assertSame('private', $matches[1]);
        $this->assertSame('string', $matches[2]);
        $this->assertSame('xmlContent', $matches[3]);
        $this->assertSame('private', $matches[4]);
        $this->assertSame('string', $matches[5]);
        $this->assertSame('taxpayerRegime', $matches[6]);
    }

    public function test_process_invoice_job_handle_depends_on_import_invoice_use_case(): void
    {
        $this->assertStringContainsString(
            'use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;',
            $this->jobSource
        );

        $matched = preg_match(
            '/public\s+function\s+handle\s*\(\s*(ImportInvoiceUseCase)\s+\$(useCase)\s*\)\s*:\s*(void)/',
            $this->jobSource,
            $matches
        );

        $this->assertSame(1, $matched);
        $this->assertSame('ImportInvoiceUseCase', $matches[1]);
        $this->assertSame('useCase', $matches[2]);
        $this->assertSame('void', $matches[3]);
        $this->assertStringContainsString('$useCase->execute(', $this->jobSource);
        $this->assertSame(1, substr_count($this->jobSource, 'public function handle'));
    }

    public function test_job_string_payload_mismatches_raw_cfdi_dto_use_case_contract(): void
    {
        $jobCallMatched = preg_match(
            '/\$useCase->execute\s*\(\s*\$this->(xmlContent)\s*,\s*\$this->(taxpayerRegime)\s*\)/',
            $this->jobSource,
            $jobCallMatches
        );
        $useCaseMatched = preg_match(
            '/public\s+function\s+execute\s*\(\s*(RawCfdiDto)\s+\$(dto)\s*,\s*(string)\s+\$(taxpayerRegime)\s*\)/',
            $this->useCaseSource,
            $useCaseMatches
        );

        $this->assertSame(1, $jobCallMatched);
        $this->assertSame('xmlContent', $jobCallMatches[1]);
        $this->assertSame(1, $useCaseMatched);
        $this->assertSame('RawCfdiDto', $useCaseMatches[1]);
        $this->assertSame('dto', $useCaseMatches[2]);
        $this->assertSame('string', $useCaseMatches[3]);
        $this->assertSame('taxpayerRegime', $useCaseMatches[4]);
        $this->assertNotSame('string', $useCaseMatches[1]);
    }

    private function readSource(string $path): string
    {
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException("Unable to read characterization source file: {$path}");
        }

        return $source;
    }

    private function normalizePath(string $path): string
    {
        $realPath = realpath($path);

        if ($realPath === false) {
            throw new RuntimeException("Unable to resolve characterization path: {$path}");
        }

        return str_replace('\\', '/', $realPath);
    }
}
