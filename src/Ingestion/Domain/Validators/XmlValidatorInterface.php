<?php

declare(strict_types=1);

namespace App\Ingestion\Domain\Validators;

interface XmlValidatorInterface
{
    public function validate(string $xmlContent): bool;
}
