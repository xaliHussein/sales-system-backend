<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;
    use Uuids;
    protected $guarded = [];

    protected $hidden = ['created_at', 'updated_at','user_id'];
}
