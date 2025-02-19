<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscribe extends Model
{
    use HasFactory;
    protected $fillable = [
        'Provider_Service_id',
        'time',
        'end_date',
        'start_date',
        'status',

    ];

    public function Category_Subscribe()
    {
        return $this->hasMany(Category_Subscribe::class);
    }
    public function Provider_Service()
    {
        return $this->hasMany(Provider_Service::class ,'Provider_Service_id');
    }
}
