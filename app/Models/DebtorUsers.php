<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DebtorUsers extends Model
{
    use HasFactory;
    use Uuids;
    protected $guarded = [];

    public function paymentDates()
    {
        return $this->hasMany(PaymentDate::class, 'debtor_user_id', 'id');
    }

    public function sales()
    {
        return $this->hasMany(Sales::class, 'debtor_user_id', 'id');
    }
}
