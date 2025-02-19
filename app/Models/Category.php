<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'month_subscribe',
        'commision',

    ];

    public function product()
    {
        return $this->hasMany(Product::class);
    }

    public function Category_Vendor()
    {
        return $this->hasMany(Category_Vendor::class);
    }
    public function Category_Subscribe()
    {
        return $this->hasMany(Category_Subscribe::class);
    }

    public function Favourite_user()
    {
        return $this->morphMany(Favourite_user::class, 'favoritable');
    }

}
