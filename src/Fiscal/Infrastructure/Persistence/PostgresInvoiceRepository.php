<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence;

use App\Fiscal\Domain\Repositories\InvoiceRepositoryInterface;
use App\Fiscal\Domain\Entities\Invoice;
use App\Fiscal\Infrastructure\Persistence\Models\InvoiceModel;
use App\Shared\Domain\ValueObjects\Uuid;
use App\Shared\Domain\ValueObjects\Money;
use App\Fiscal\Domain\Enums\SatStatus;
use App\Fiscal\Domain\Enums\LifecycleState;

class PostgresInvoiceRepository implements InvoiceRepositoryInterface
{
    public function findById(Uuid $uuid): ?Invoice
    {
        $model = InvoiceModel::find($uuid->getValue());

        if (!$model) {
            return null;
        }

        // Data Mapping: Primitives to Value Objects
        return Invoice::reconstitute(
            new Uuid($model->id),
            $model->tipo_de_comprobante,
            $model->metodo_pago,
            new Money((int) $model->subtotal_cents),
            new Money((int) $model->total_cents),
            new Money((int) $model->balance_due_cents),
            SatStatus::from($model->sat_status),
            LifecycleState::from($model->lifecycle_state)
        );
    }

    public function save(Invoice $invoice): void
    {
        // Data Mapping: Value Objects back to Primitives
        InvoiceModel::updateOrCreate(
            ['id' => $invoice->getUuid()->getValue()],
            [
                'tipo_de_comprobante' => $invoice->getTipoDeComprobante(),
                'metodo_pago'         => $invoice->getMetodoPago(),
                'subtotal_cents'      => $invoice->getSubtotal()->getCents(),
                'total_cents'         => $invoice->getTotal()->getCents(),
                'balance_due_cents'   => $invoice->getBalanceDue()->getCents(),
                'sat_status'          => $invoice->getSatStatus()->value,
                'lifecycle_state'     => $invoice->getLifecycleState()->value,
            ]
        );
    }

    public function exists(Uuid $uuid): bool
    {
        return InvoiceModel::where('id', $uuid->getValue())->exists();
    }
}
