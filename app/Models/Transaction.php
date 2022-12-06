<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'out_sum',
        'success',
        'case_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id','id');
    }

    public function case()
    {
        return $this->belongsTo(Cases::class, 'case_id','id');
    }
}
