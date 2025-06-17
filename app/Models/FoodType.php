<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FoodType extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];



    public function FoodType_ProductProvider()
    {
        return $this->belongsTo(FoodType_ProductProvider::class);
    }



}
