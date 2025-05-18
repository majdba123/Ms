<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Provider_Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status'

    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function products()
    {
        return $this->morphMany(Product::class, 'providerable');
    }


    public function Subscribe()
    {
        return $this->hasMany(Subscribe::class);
    }


    public function reservations()
    {
        return $this->hasManyThrough(
            Rseevation::class,      // The intermediate model (Reservation)
            Product::class,         // The related model (Product)
            'providerable_id',     // Foreign key in Product that references the provider
            'product_id',           // Foreign key in Reservation that references the product
            'id',                   // Local key on Provider_Service
            'id'                    // Local key on Product
        )->where('products.providerable_type', Provider_Service::class);
    }



    public function getCompletedReservationsCountAttribute()
    {
        return $this->reservations()->where('status', 'complete')->count();
    }



    public function getPendingReservationsCountAttribute()
    {
        return $this->reservations()->where('status', 'pending')->count();
    }

    public function getCancelledReservationsCountAttribute()
    {
        return $this->reservations()->where('status', 'cancelled')->count();
    }

    public function getTotalReservationsRevenueAttribute()
    {
        return $this->reservations()
            ->where('status', 'complete')
            ->with(['product'])
            ->get()
            ->sum(function($reservation) {
                return $reservation->product->price;
            });
    }

    public function getPendingReservationsRevenueAttribute()
    {
        return $this->reservations()
            ->where('status', 'pending')
            ->with(['product'])
            ->get()
            ->sum(function($reservation) {
                return $reservation->product->price;
            });
    }


    // Additional method to get all reservations with product details
    public function getReservationsWithProductsAttribute()
    {
        return $this->reservations()
            ->with(['product', 'user'])
            ->get()
            ->map(function($reservation) {
                return [
                    'reservation' => $reservation,
                    'product' => $reservation->product,
                    'user' => $reservation->user
                ];
            });
    }


}
