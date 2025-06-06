<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'product_id',
        'num',
        'comment',
    ];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id');
    }
    public function answer_rating()
    {
        return $this->hasMany(Answer_Rating::class);
    }


        public function toArray()
        {
            return [
                'id' => $this->id,
                'num' => $this->num,
                'comment' => $this->comment,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'user' => $this->user ? [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'image' => $this->user->profile->image ?? null
                ] : null,
                'answers' => $this->answer_rating
            ];
        }
}
