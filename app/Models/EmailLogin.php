<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailLogin extends Model
{
    use HasFactory;
    public $fillable = ['email', 'token'];

    public function user()
    {
        return $this->hasOne(\App\Models\User::class, 'email', 'email');
    }

    public static function createForEmail($email)
    {
        return self::create([
            'email' => $email,
            'token' => Str::random(20)
        ]);
    }

    public static function validFromToken($token)
    {
        return self::where('token', $token)
            //  ->where('created_at', '>', Carbon::parse('-15 minutes'))
            ->firstOrFail();
    }
}
