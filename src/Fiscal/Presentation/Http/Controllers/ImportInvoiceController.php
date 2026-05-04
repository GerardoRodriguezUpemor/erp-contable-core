<?php

declare(strict_types=1);

namespace App\Fiscal\Presentation\Http\Controllers;

use App\Fiscal\Application\UseCases\ImportInvoiceUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

class ImportInvoiceController
{
    public function __construct(
        private ImportInvoiceUseCase $useCase
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // 1. Basic HTTP Validation (Format, not business logic)
        $request->validate([
            'xml_content' => 'required|string',
            'taxpayer_regime' => 'required|string|size:3',
        ]);

        try {
            // 2. Delegate to the Application Layer
            $this->useCase->execute(
                $request->input('xml_content'),
                $request->input('taxpayer_regime')
            );

            // 3. Translate Success to HTTP
            return response()->json([
                'message' => 'Invoice ingested and processed successfully.'
            ], 201); // 201 Created

        } catch (DomainException | InvalidArgumentException $e) {
            // 4. Translate Business Rule Violations to HTTP 422
            return response()->json([
                'error' => 'Fiscal Validation Failed',
                'detail' => $e->getMessage()
            ], 422);

        } catch (RuntimeException $e) {
            // 5. Translate System/Integrity Errors to HTTP 400 or 409
            return response()->json([
                'error' => 'Processing Error',
                'detail' => $e->getMessage()
            ], 400);
        }
    }
}
