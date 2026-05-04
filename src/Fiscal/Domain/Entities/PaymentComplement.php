<?php

declare(strict_types=1);

namespace App\Fiscal\Domain\Entities;

use App\Shared\Domain\ValueObjects\Uuid;
use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\Enums\SatStatus;
use DateTimeImmutable;
use DomainException;

class PaymentComplement
{
    /** * Tolerancia de 2 centavos para discrepancias de truncamiento 
     * al sumar múltiples parcialidades.
     */
    private const MAX_DISCREPANCY_TOLERANCE_CENTS = 2;

    /** @var PaymentApplication[] */
    private array $applications = [];

    public function __construct(
        private Uuid $uuid,
        private DateTimeImmutable $paymentDate,
        private Money $totalReceived,
        private SatStatus $satStatus = SatStatus::ACTIVE
    ) {}

    public function addApplication(PaymentApplication $application): void
    {
        $currentAppliedCents = $this->calculateTotalApplied()->getCents();
        $newAmountCents = $application->amountPaid->getCents();
        
        // Sumamos la tolerancia al límite superior para permitir que una 
        // aplicación cierre el saldo exacto a pesar de un centavo de diferencia.
        $limitCents = $this->totalReceived->getCents() + self::MAX_DISCREPANCY_TOLERANCE_CENTS;

        if (($currentAppliedCents + $newAmountCents) > $limitCents) {
            throw new DomainException(
                "Cannot apply payment: The amount exceeds the remaining unapplied balance of this REP."
            );
        }

        $this->applications[] = $application;
    }

    public function calculateTotalApplied(): Money
    {
        $totalCents = 0;
        foreach ($this->applications as $app) {
            $totalCents += $app->amountPaid->getCents();
        }
        
        return new Money($totalCents);
    }

    /**
     * Deferred Invariant (Pre-Persist): Verifica la integridad matemática final 
     * aplicando el modelo de valor absoluto con tolerancia.
     */
    public function verifyTotalIntegrity(): void
    {
        $appliedCents = $this->calculateTotalApplied()->getCents();
        $receivedCents = $this->totalReceived->getCents();

        $difference = abs($receivedCents - $appliedCents);

        if ($difference > self::MAX_DISCREPANCY_TOLERANCE_CENTS) {
            throw new DomainException(
                "Fiscal Discrepancy: The sum of applied amounts ({$appliedCents}) does not match the total received ({$receivedCents}) within the allowed tolerance."
            );
        }
    }

    /**
     * Domain Behavior: SAT Cancellation
     */
    public function markAsCancelledBySat(): void
    {
        $this->satStatus = SatStatus::CANCELLED;
        $this->applications = []; // Wipe the applications to sever the links
    }
    
    public function getApplications(): array
    {
        return [...$this->applications];
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getPaymentDate(): DateTimeImmutable
    {
        return $this->paymentDate;
    }

    public function getTotalReceived(): Money
    {
        return $this->totalReceived;
    }

    public function getSatStatus(): SatStatus
    {
        return $this->satStatus;
    }
}
