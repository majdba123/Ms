<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favourite_user extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'favoritable_id',
        'favoritable_type'

    ];
    public function user()
    {
        return $this->belongsTo(User::class , 'user_id');
    }
    public function favoritable()
    {
        return $this->morphTo();
    }

}
