<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscribe extends Model
{
    use HasFactory;
    protected $fillable = [
        'provider__service_id',
        'web_sub_id',
        'end_date',
        'start_date',
        'status',
    ];

    public function WebSub()
    {
        return $this->belongsTo(WebSub::class , 'web_sub_id');
    }
    public function Provider_Service()
    {
        return $this->belongsTo(Provider_Service::class ,'provider__service_id');
    }
}
