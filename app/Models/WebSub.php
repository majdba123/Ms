<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebSub extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'time',
        'price',

    ];

    public function Subscribe()
    {
        return $this->hasMany(Subscribe::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class ,'user_id');
    }
}
