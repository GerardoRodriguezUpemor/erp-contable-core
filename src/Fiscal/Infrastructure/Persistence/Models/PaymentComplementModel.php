<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentComplementModel extends Model
{
    protected $table = 'PaymentComplements';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $guarded = [];

    public function applications(): HasMany
    {
        return $this->hasMany(PaymentApplicationModel::class, 'paymentUuid', 'id');
    }
}
