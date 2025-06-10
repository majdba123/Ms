<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodType_ProductProvider extends Model
{
    use HasFactory;


    protected $fillable = [
        'food_type_id',
        'provider__product_id'
    ];


    public function food_type()
    {
        return $this->belongsTo(FoodType::class,'food_type_id');
    }

    public function product_provider()
    {
        return $this->belongsTo(Provider_Product::class,'provider__product_id');
    }
}
