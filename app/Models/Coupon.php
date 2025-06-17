<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'code',
        'discount_percent',
        'status',
        'expires_at',
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE &&
               ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function coupon_orders()
    {
        return $this->hasMany(Order_Coupon::class);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    // دالة مساعدة للبحث بالكود
    public static function findByCode($code)
    {
        return static::where('code', $code)->first();
    }
}