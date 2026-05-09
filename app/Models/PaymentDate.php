<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentDate extends Model
{
    use HasFactory;
    use Uuids;
    protected $guarded = [];


    public function customer_payment_histories()
    {
        return $this->hasMany(CustomerPaymentHistories::class, 'payment_date_id', 'id');
    }
}
