<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ingested_cfdis', function (Blueprint $table) {
            // System-generated ID (NOT the SAT UUID) — used as the lightweight event payload identifier
            $table->uuid('id')->primary();

            // The official SAT UUID from TimbreFiscalDigital
            $table->uuid('sat_uuid')->index();

            // Tenant context — required for idempotency and multi-tenancy
            $table->uuid('tenant_id')->index();

            // Unique constraint enforces idempotency: one record per (sat_uuid, tenant_id)
            $table->unique(['sat_uuid', 'tenant_id'], 'raw_cfdis_idempotency_key');

            // RFC fields for classification inputs
            $table->string('emisor_rfc', 13)->index();
            $table->string('receptor_rfc', 13)->index();

            // SAT document taxonomy (raw code: I, E, P, N, T)
            $table->string('document_type', 1);

            // Payment method (PUE, PPD, or N/A for non-applicable types)
            $table->string('metodo_pago', 3);

            // Financial amounts stored as integer cents to avoid floating-point errors
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('total_cents');

            // Document issuance date from the XML
            $table->timestamp('emitted_at');

            // Manual review flag — set for THIRD_PARTY and unresolvable documents.
            // Records flagged for review must be PRESERVED and AUDITED, never auto-deleted.
            $table->boolean('review_flag')->default(false)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ingested_cfdis');
    }
};
