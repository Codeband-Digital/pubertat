<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cases extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'site_number',
        'stripe_price_id',
    ];
}
