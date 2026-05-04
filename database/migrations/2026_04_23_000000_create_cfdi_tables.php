<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. The Base Document Table (Source of Truth)
        Schema::create('cfdi_documents', function (Blueprint $table) {
            $table->uuid('id')->primary(); // The SAT UUID
            $table->string('rfc_emisor', 13)->index();
            $table->string('rfc_receptor', 13)->index();
            $table->string('cfdi_version', 5);
            $table->timestamp('emitted_at');
            $table->string('s3_path')->nullable(); // Where the raw XML lives
            $table->timestamps();
        });

        // 2. The Invoice Table (Accounting Context)
        Schema::create('invoices', function (Blueprint $table) {
            // Foreign Key linking directly to the CFDI document
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('cfdi_documents')->onDelete('cascade');
            
            $table->string('tipo_de_comprobante', 1); // 'I', 'E'
            $table->string('metodo_pago', 3);         // 'PUE', 'PPD'
            
            // Financials stored strictly as CENTS
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('total_cents');
            $table->bigInteger('balance_due_cents');
            
            // State Machines
            $table->string('sat_status', 25)->default('ACTIVE'); // ACTIVE, CANCELLED
            $table->string('lifecycle_state', 25)->default('IMPORTED'); // IMPORTED, PROCESSED, LOCKED
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cfdi_documents');
    }
};
