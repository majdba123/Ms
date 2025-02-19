<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category_Vendor extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_id',
        'vendorable_id',
        'vendorable_type',

    ];

    public function category()
    {
        return $this->belongsTo(Category::class ,'category_id');
    }

    public function vendorable()
    {
        return $this->morphTo();
    }
}
