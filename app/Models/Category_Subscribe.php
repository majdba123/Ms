<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category_Subscribe extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_id',
        'subscribe_id',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class,'category_id');
    }
    public function subscribe()
    {
        return $this->belongsTo(Subscribe::class,'subscribe_id');
    }
}
