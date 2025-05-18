<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rseevation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'status',
        'product_id',
        'total_price'
    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }




}
