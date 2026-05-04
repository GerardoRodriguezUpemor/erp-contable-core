<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. The Payment Complement Table (Aggregate Root)
        Schema::create('PaymentComplements', function (Blueprint $table) {
            $table->uuid('id')->primary(); // The SAT UUID of the REP
            $table->timestamp('paymentDate');
            $table->bigInteger('totalReceivedCents');
            $table->string('sat_status', 25)->default('ACTIVE');
            $table->timestamps();
        });

        // 2. The Intermediate Application Table (M:N Link)
        Schema::create('PaymentApplications', function (Blueprint $table) {
            $table->id(); // Surrogate primary key for the DB
            
            // Foreign Keys linking the Aggregate Roots
            $table->uuid('paymentUuid');
            $table->uuid('invoiceUuid');
            
            // Cash Flow Tracking
            $table->integer('installmentNumber'); // Parcialidad
            $table->bigInteger('previousBalanceCents');
            $table->bigInteger('amountPaidCents');
            $table->bigInteger('outstandingBalanceCents');
            $table->timestamps();

            // Referential Integrity
            $table->foreign('paymentUuid')
                  ->references('id')
                  ->on('PaymentComplements')
                  ->onDelete('cascade');
                  
            $table->foreign('invoiceUuid')
                  ->references('id')
                  ->on('invoices') 
                  ->onDelete('restrict'); // Never delete an invoice just because a payment was deleted
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PaymentApplications');
        Schema::dropIfExists('PaymentComplements');
    }
};
