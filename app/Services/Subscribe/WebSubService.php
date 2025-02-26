<?php
namespace App\Services\Subscribe;

use App\Models\WebSub;
use Illuminate\Support\Facades\Auth;

class WebSubService
{
    public function createWebSub(array $data)
    {
        $user_id = Auth::user()->id; // جلب ID الخاص بـ Service Provider من Auth

        $WebSub = WebSub::create([
            'user_id' => $user_id,
            'time' => $data['time'],
            'price' => $data['price']
        ]);

        return $WebSub;

    }

    public function updateWebSub(WebSub $webSub, array $data)
    {
        $webSub->update($data);
        return $webSub;
    }

    public function getAllWebSubs()
    {
        return WebSub::all();
    }
}
