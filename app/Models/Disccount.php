<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Disccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'status',
        'time',
        'value',
        'providerable1_id',
        'providerable1_type'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }

    public function providerable1()
    {
        return $this->morphTo();
    }
}
