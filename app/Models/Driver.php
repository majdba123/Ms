<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',

    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function Order_Driver()
    {
        return $this->hasMany(Order_Driver::class);
    }
}
