<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sales extends Model
{
      use HasFactory;
    use Uuids;
    protected $guarded = [];
    protected $appends = ['partial_return'];

    public function sale_items()
    {
        return $this->hasMany(Sale_items::class, 'sale_id', 'id');
    }


    public function getPartialReturnAttribute()
    {
        return $this->sale_items()->where('returned_quantity', '>', 0)->exists();
    }


    public function paymentDates()
    {
        return $this->belongsTo(PaymentDate::class, 'id', 'sale_id');
    }



    public function getPaymentStatusAttribute()
    {
        $payment = $this->paymentDates()->latest()->first();

        if (!$payment) {
            return 'unpaid';
        }

        if ($payment->amount_paid == 0) {
            return 'unpaid';
        }

        if ($payment->remaining_amount == 0) {
            return 'paid';
        }

        if ($payment->amount_paid > 0 && $payment->remaining_amount > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }

    public function debtorUser()
    {
        return $this->belongsTo(DebtorUsers::class, 'debtor_user_id', 'id');
    }
}
