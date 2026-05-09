<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale_items extends Model
{
    use HasFactory;
    use Uuids;
    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(Items::class, 'item_id', 'id');
    }

     public function sale()
    {
        return $this->belongsTo(Sales::class, 'sale_id', 'id');
    }
}
