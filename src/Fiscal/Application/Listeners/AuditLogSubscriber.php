<?php

declare(strict_types=1);

namespace App\Fiscal\Application\Listeners;

use App\Fiscal\Domain\Events\InvoiceCancelledEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditLogSubscriber
{
    public function handleInvoiceCancelled(InvoiceCancelledEvent $event): void
    {
        DB::table('FiscalAuditLogs')->insert([
            'id'            => (string) Str::uuid(),
            'aggregateUuid' => $event->invoiceUuid->getValue(),
            'eventName'     => 'InvoiceCancelledEvent',
            'payload'       => json_encode([
                'reason' => $event->reason
            ]),
            'occurredOn'    => $event->occurredOn->format('Y-m-d H:i:s'),
            'created_at'    => now(),
        ]);
    }
}
