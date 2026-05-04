<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Jobs;

use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $xmlContent,
        private string $taxpayerRegime
    ) {}

    public function handle(ImportInvoiceUseCase $useCase): void
    {
        $useCase->execute($this->xmlContent, $this->taxpayerRegime);
    }
}
