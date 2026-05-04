<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Auth;

use App\Shared\Application\TenantContextInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class LaravelTenantContext implements TenantContextInterface
{
    public function getCurrentRfc(): string
    {
        // Assumes your authenticatable User model has an 'rfc' property
        $user = Auth::user();

        if (!$user || empty($user->rfc)) {
            // Hard failure. The system should never execute fiscal logic without a tenant.
            throw new RuntimeException("Security Violation: No active fiscal tenant context found.");
        }

        return $user->rfc;
    }
}
