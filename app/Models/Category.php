<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',    /** 0 = product         1=service */
        'price',
        'imag',

    ];

    public function product()
    {
        return $this->hasMany(Product::class);
    }



    public function Favourite_user()
    {
        return $this->morphMany(Favourite_user::class, 'favoritable');
    }

}
