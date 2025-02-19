<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Answer_Rating extends Model
{
    use HasFactory;
    protected $fillable = [
        'rate_id',
        'comment',
    ];
    public function rate()
    {
        return $this->belongsTo(Rating::class,'rate_id');
    }
}
