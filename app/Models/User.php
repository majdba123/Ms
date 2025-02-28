<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'google_id',
        'facebook_id',
        'phone',
        'email',
        'otp',
        'type',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
    public function Provider_service()
    {
        return $this->hasOne(Provider_Service::class);
    }
    public function Provider_Product()
    {
        return $this->hasOne(Provider_Product::class);
    }
    public function Driver()
    {
        return $this->hasOne(Driver::class);
    }
    public function Profile()
    {
        return $this->hasOne(Profile::class);
    }
    public function favourite_user()
    {
        return $this->hasMany(Favourite_user::class);
    }
    public function answere()
    {
        return $this->hasMany(Answer_Rating::class);
    }

    public function websub()
    {
        return $this->hasMany(WebSub::class);
    }

    public function reservation()
    {
        return $this->hasMany(Rseevation::class);
    }
}
