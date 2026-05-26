<?php

declare(strict_types=1);

namespace Tests\Fiscal\Domain\Entities;

use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Domain\Enums\LifecycleState;
use App\Fiscal\Domain\Enums\SatStatus;
use App\Fiscal\Domain\Events\InvoiceCancelledEvent;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\TaxCategory;
use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the LOCKED lifecycle state and domain event emission.
 *
 * LOCKED state represents invoices included in an annual SAT declaration.
 * They are fiscally immutable — no modifications, payments, or reversals
 * are permitted. This protects the integrity of submitted annual declarations.
 *
 * These tests serve as a characterization harness for the LOCKED constraint
 * described in current-state-analysis.md and hidden-business-rules.md.
 */
class InvoiceLifecycleTest extends TestCase
{
    private function makeLockedInvoice(): Invoice
    {
        return Invoice::reconstitute(
            new Uuid('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee'),
            'I',
            'PPD',
            new Money(100000),
            new Money(116000),
            new Money(116000),
            SatStatus::ACTIVE,
            LifecycleState::LOCKED
        );
    }

    // --- LOCKED state: mutation guards ---

    public function test_locked_invoice_cannot_receive_payment(): void
    {
        $invoice = $this->makeLockedInvoice();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('LOCKED');

        $invoice->applyPayment(new Money(10000));
    }

    public function test_locked_invoice_cannot_have_taxes_added(): void
    {
        $invoice = $this->makeLockedInvoice();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('LOCKED');

        $invoice->addTax(new Tax('IVA', TaxCategory::TRANSFERRED, new Money(16000), 0.16));
    }

    public function test_locked_invoice_cannot_have_payments_reversed(): void
    {
        $invoice = $this->makeLockedInvoice();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('LOCKED');

        $invoice->reversePayment(new Money(10000));
    }

    public function test_locked_invoice_cancelled_by_sat_throws_for_manual_reconciliation(): void
    {
        // This is an explicit architectural decision: cancelling a LOCKED invoice
        // signals a critical accounting inconsistency requiring human intervention.
        $invoice = $this->makeLockedInvoice();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('SAT cancelled a LOCKED invoice');

        $invoice->markAsCancelledBySat();
    }

    // --- Lifecycle advancement ---

    public function test_invoice_can_advance_from_imported_to_processed(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('11111111-2222-3333-4444-555555555555'),
            'I', 'PUE',
            new Money(100000), new Money(116000)
        );

        $invoice->markAsProcessed();

        $this->assertEquals(LifecycleState::PROCESSED, $invoice->getLifecycleState());
    }

    public function test_invoice_cannot_advance_from_processed_to_processed(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('22222222-3333-4444-5555-666666666666'),
            'I', 'PUE',
            new Money(100000), new Money(116000)
        );

        $invoice->markAsProcessed();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('IMPORTED state');

        $invoice->markAsProcessed();
    }

    // --- Domain Events ---

    public function test_cancellation_emits_a_domain_event(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('33333333-4444-5555-6666-777777777777'),
            'I', 'PUE',
            new Money(100000), new Money(116000)
        );

        // No events before cancellation
        $this->assertEmpty($invoice->releaseEvents());

        $invoice->markAsCancelledBySat();

        $events = $invoice->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(InvoiceCancelledEvent::class, $events[0]);
    }

    public function test_released_events_are_cleared_after_release(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('44444444-5555-6666-7777-888888888888'),
            'I', 'PUE',
            new Money(100000), new Money(116000)
        );

        $invoice->markAsCancelledBySat();

        // First release gives the events
        $first = $invoice->releaseEvents();
        $this->assertCount(1, $first);

        // Second release should be empty — events must not be dispatched twice
        $second = $invoice->releaseEvents();
        $this->assertEmpty($second);
    }

    public function test_regular_creation_emits_no_domain_events(): void
    {
        $invoice = Invoice::createFromIngestion(
            new Uuid('55555555-6666-7777-8888-999999999999'),
            'I', 'PPD',
            new Money(100000), new Money(116000)
        );

        $this->assertEmpty($invoice->releaseEvents());
    }
}
