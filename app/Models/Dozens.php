<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dozens extends Model
{
    use HasFactory;
    use Uuids;
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(Items::class, 'dozen_id', 'id');
    }


}
