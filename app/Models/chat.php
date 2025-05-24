<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'is_read',
    ];

    // علاقة المرسل
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // علاقة المستقبل
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
