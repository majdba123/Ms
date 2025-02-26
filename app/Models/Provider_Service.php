<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider_Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',

    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function products()
    {
        return $this->morphMany(Product::class, 'providerable');
    }

    public function category_vendor()
    {
        return $this->morphMany(Category_Vendor::class, 'vendorable');
    }

    public function Subscribe()
    {
        return $this->hasMany(Subscribe::class);
    }


}
