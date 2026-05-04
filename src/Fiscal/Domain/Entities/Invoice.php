<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Entities;

use App\Shared\Domain\ValueObjects\Money;
use App\Shared\Domain\ValueObjects\Uuid;
use App\Fiscal\Domain\ValueObjects\Tax;
use App\Fiscal\Domain\Enums\SatStatus;
use App\Fiscal\Domain\Enums\LifecycleState;
use App\Fiscal\Domain\Events\InvoiceCancelledEvent;
use DateTimeImmutable;
use DomainException;

class Invoice
{
    /**
     * SAT officially tolerates minor rounding discrepancies per concept.
     * We allow a maximum variance of 2 cents.
     */
    private const MAX_DISCREPANCY_TOLERANCE_CENTS = 2;

    /**
     * Private constructor forces the use of factory methods to ensure valid state.
     */
    private function __construct(
        private Uuid $uuid,
        private string $tipoDeComprobante, // 'I', 'E'
        private string $metodoPago,        // 'PUE', 'PPD'
        private Money $subtotal,
        private Money $total,
        private Money $balanceDue,
        private SatStatus $satStatus = SatStatus::ACTIVE,
        private LifecycleState $lifecycleState = LifecycleState::IMPORTED,
        private array $taxes = []          // Array of Tax Value Objects (to be built)
    ) {}

    /** @var array */
    private array $domainEvents = [];

    /**
     * Named Constructor for new Invoices arriving from the Ingestion layer.
     */
    public static function createFromIngestion(
        Uuid $uuid,
        string $tipoDeComprobante,
        string $metodoPago,
        Money $subtotal,
        Money $total
    ): self {
        // Enforce Cash Flow Rules immediately
        $balanceDue = ($metodoPago === 'PUE') ? new Money(0) : $total;

        return new self(
            $uuid,
            $tipoDeComprobante,
            $metodoPago,
            $subtotal,
            $total,
            $balanceDue
        );
    }

    /**
     * Reconstitutes an existing Invoice from persistence.
     * Unlike creation, this bypasses initial business logic to restore exact saved state.
     */
    public static function reconstitute(
        Uuid $uuid,
        string $tipoDeComprobante,
        string $metodoPago,
        Money $subtotal,
        Money $total,
        Money $balanceDue,
        SatStatus $satStatus,
        LifecycleState $lifecycleState
    ): self {
        return new self(
            $uuid,
            $tipoDeComprobante,
            $metodoPago,
            $subtotal,
            $total,
            $balanceDue,
            $satStatus,
            $lifecycleState
        );
    }

    /**
     * Domain Behavior: Applying a Payment Complement (REP)
     */
    public function applyPayment(Money $amountApplied): void
    {
        if ($this->lifecycleState === LifecycleState::LOCKED) {
            throw new DomainException("Cannot apply payments to a LOCKED invoice.");
        }

        if ($this->satStatus === SatStatus::CANCELLED) {
            throw new DomainException("Cannot modify an Invoice that is in CANCELLED status.");
        }

        if ($this->metodoPago === 'PUE') {
            throw new DomainException("Cannot apply a Payment Complement to a PUE invoice.");
        }

        if ($amountApplied->getCents() > $this->balanceDue->getCents()) {
            throw new DomainException("Payment amount exceeds the balance due.");
        }

        $this->balanceDue = $this->balanceDue->subtract($amountApplied);
    }

    /**
     * Domain Behavior: SAT Cancellation
     */
    public function markAsCancelledBySat(): void
    {
        if ($this->lifecycleState === LifecycleState::LOCKED) {
            // In a real system, this triggers a "Fiscal Discrepancy" Domain Event
            throw new DomainException("SAT cancelled a LOCKED invoice. Manual fiscal reconciliation required.");
        }

        $this->satStatus = SatStatus::CANCELLED;
        $this->balanceDue = new Money(0); // Cancelled invoices hold no balance

        // Record the event internally
        $this->domainEvents[] = new InvoiceCancelledEvent(
            $this->uuid,
            new DateTimeImmutable()
        );
    }

    /**
     * Domain Behavior: Reversing a Payment Complement
     */
    public function reversePayment(Money $amountReversed): void
    {
        if ($this->lifecycleState === LifecycleState::LOCKED) {
            throw new DomainException("Cannot reverse payments on a LOCKED invoice. Manual accounting adjustment required.");
        }

        $this->balanceDue = $this->balanceDue->add($amountReversed);

        if ($this->balanceDue->getCents() > $this->total->getCents()) {
            throw new DomainException("Fatal State: Reversing this payment pushes the balance due above the invoice total.");
        }
    }

    /**
     * Domain Behavior: Internal Lifecycle Advancement
     */
    public function markAsProcessed(): void
    {
        if ($this->lifecycleState !== LifecycleState::IMPORTED) {
            throw new DomainException("Invoice must be in IMPORTED state to be processed.");
        }
        $this->lifecycleState = LifecycleState::PROCESSED;
    }

    // Getters
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getTipoDeComprobante(): string
    {
        return $this->tipoDeComprobante;
    }

    public function getMetodoPago(): string
    {
        return $this->metodoPago;
    }

    public function getSubtotal(): Money
    {
        return $this->subtotal;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getBalanceDue(): Money
    {
        return $this->balanceDue;
    }

    public function getSatStatus(): SatStatus
    {
        return $this->satStatus;
    }

    public function getLifecycleState(): LifecycleState
    {
        return $this->lifecycleState;
    }

    public function addTax(Tax $tax): void
    {
        if ($this->lifecycleState === LifecycleState::LOCKED) {
            throw new DomainException("Cannot add taxes to a LOCKED invoice.");
        }
        
        if ($this->satStatus === SatStatus::CANCELLED) {
            throw new DomainException("Cannot modify an Invoice that is in CANCELLED status.");
        }

        $this->taxes[] = $tax;
    }

    /**
     * Retrieves taxes for reporting.
     * Senior Practice: Returns a defensive copy to prevent accidental mutation of the array.
     * @return Tax[]
     */
    public function getTaxes(): array
    {
        return [...$this->taxes]; 
    }

    public function calculateExpectedTotal(): Money
    {
        $runningTotal = $this->subtotal;

        foreach ($this->taxes as $tax) {
            $runningTotal = $tax->applyToTotal($runningTotal);
        }

        return $runningTotal;
    }

    /**
     * Detects if the XML's stated total differs from our strict mathematical total,
     * accounting for real-world SAT rounding anomalies.
     */
    public function hasFiscalDiscrepancy(): bool
    {
        $expectedTotalCents = $this->calculateExpectedTotal()->getCents();
        $actualTotalCents = $this->total->getCents();
        
        $difference = abs($expectedTotalCents - $actualTotalCents);
        
        return $difference > self::MAX_DISCREPANCY_TOLERANCE_CENTS;
    }

    /**
     * Valida que la suma matemática estricta coincida con el total, de lo contrario
     * aborta el dominio para evitar corromper los libros contables.
     */
    public function ensureMathematicalIntegrity(): void
    {
        if ($this->hasFiscalDiscrepancy()) {
            throw new DomainException("Irrecoverable fiscal discrepancy: Subtotal + Taxes != Total within the 2 cents SAT tolerance.");
        }
    }

    /**
     * Extracts and clears the events so they can be dispatched by the infrastructure.
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }
}
