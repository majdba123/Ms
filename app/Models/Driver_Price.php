<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver_Price extends Model
{
    use HasFactory;


        protected $fillable = [
        'from_distance',
        'to_distance',
        'price',
    ];
}
