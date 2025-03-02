<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'price',
        'category_id',
        'providerable_id',
        'providerable_type'

    ];
    public function providerable()
    {
        return $this->morphTo();
    }
    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }
    public function rating()
    {
        return $this->hasMany(Rating::class);
    }
    public function images()
    {
        return $this->hasMany(imag_product::class);
    }
    public function Discount()
    {
        return $this->hasone(Disccount::class);
    }
    public function Order_Product()
    {
        return $this->hasMany(Order_Product::class);
    }
    public function Favourite_user()
    {
        return $this->morphMany(Favourite_user::class, 'favoritable');
    }

    public function reservation()
    {
        return $this->hasMany(Rseevation::class);
    }
}
