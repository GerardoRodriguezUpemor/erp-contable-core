<?php

declare(strict_types=1);

namespace App\Fiscal\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentApplicationModel extends Model
{
    protected $table = 'PaymentApplications';
    protected $guarded = [];
}
