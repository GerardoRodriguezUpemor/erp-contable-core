<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence;

use App\Fiscal\Domain\Repositories\PaymentComplementRepositoryInterface;
use App\Fiscal\Domain\Entities\PaymentComplement;
use App\Fiscal\Domain\Entities\PaymentApplication;
use App\Fiscal\Infrastructure\Persistence\Models\PaymentComplementModel;
use App\Shared\Domain\ValueObjects\Uuid;
use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\Enums\SatStatus;
use DateTimeImmutable;

class PostgresPaymentComplementRepository implements PaymentComplementRepositoryInterface
{
    public function exists(Uuid $uuid): bool
    {
        return PaymentComplementModel::where('id', $uuid->getValue())->exists();
    }

    public function findById(Uuid $uuid): ?PaymentComplement
    {
        $model = PaymentComplementModel::with('applications')->find($uuid->getValue());

        if (!$model) {
            return null;
        }

        $payment = new PaymentComplement(
            new Uuid($model->id),
            new DateTimeImmutable($model->paymentDate),
            new Money((int) $model->totalReceivedCents),
            SatStatus::from($model->sat_status)
        );

        foreach ($model->applications as $app) {
            $payment->addApplication(new PaymentApplication(
                new Uuid($app->invoiceUuid),
                $app->installmentNumber,
                new Money((int) $app->previousBalanceCents),
                new Money((int) $app->amountPaidCents),
                new Money((int) $app->outstandingBalanceCents)
            ));
        }

        return $payment;
    }

    public function save(PaymentComplement $paymentComplement): void
    {
        // 1. Upsert the Aggregate Root
        $model = PaymentComplementModel::updateOrCreate(
            ['id' => $paymentComplement->getUuid()->getValue()],
            [
                'paymentDate'        => $paymentComplement->getPaymentDate()->format('Y-m-d H:i:s'),
                'totalReceivedCents' => $paymentComplement->getTotalReceived()->getCents(),
                'sat_status'         => $paymentComplement->getSatStatus()->value,
            ]
        );

        // 2. Sync the Applications (Wipe and replace is safest for immutable value-like objects)
        $model->applications()->delete();

        $applicationRecords = [];
        foreach ($paymentComplement->getApplications() as $app) {
            $applicationRecords[] = [
                'paymentUuid'             => $paymentComplement->getUuid()->getValue(),
                'invoiceUuid'             => $app->invoiceUuid->getValue(),
                'installmentNumber'       => $app->installmentNumber,
                'previousBalanceCents'    => $app->previousBalance->getCents(),
                'amountPaidCents'         => $app->amountPaid->getCents(),
                'outstandingBalanceCents' => $app->outstandingBalance->getCents(),
                'created_at'              => now(),
                'updated_at'              => now(),
            ];
        }

        $model->applications()->insert($applicationRecords);
    }
}
