<?php
namespace App\Services\Subscribe;

use App\Models\Subscribe;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SubscribeService
{
    public function createSubscription($data)
    {
        $providerServiceId = Auth::user()->Provider_service->id; // جلب ID الخاص بـ Service Provider من Auth

        $subscription = Subscribe::create([
            'provider__service_id' => $providerServiceId,
            'web_sub_id' => $data['web_sub_id'],
            'status' => 'pending'
        ]);

        // ربط الفئات بالاشتراك

        return $subscription;
    }

    public function getUserSubscriptionsByStatus($status)
    {
        $Provider_service_id = Auth::user()->Provider_service->id; // جلب ID المستخدم من Auth

        if (!in_array($status, ['pending', 'active', 'finished'])) {
            throw new \InvalidArgumentException('Invalid status provided.');
        }

        $subscriptions = Subscribe::where('provider__service_id', $Provider_service_id)->where('status', $status)->with('WebSub')->get();

        // تخصيص البيانات المرجعة
        return $subscriptions->map(function ($subscription) {
            return [
                'id' => $subscription->id,
                'provider__service_id' => $subscription->provider__service_id,
                'web_sub_id' => $subscription->web_sub_id,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'status' => $subscription->status,
                'price' => $subscription->WebSub->price,
                'time' => $subscription->WebSub->time,
            ];
        });
    }


    public function getSubscriptionsByStatus($status)
    {

        if (!in_array($status, ['pending', 'active', 'finished'])) {
            throw new \InvalidArgumentException('Invalid status provided.');
        }

        $subscriptions = Subscribe::where('status', $status)->with('WebSub')->get();

        // تخصيص البيانات المرجعة
        return $subscriptions->map(function ($subscription) {
            return [
                'id' => $subscription->id,
                'provider__service_id' => $subscription->provider__service_id,
                'provider_name' => $subscription->Provider_Service->user->name,
                'provider_email' => $subscription->Provider_Service->user->email,
                'provider_status' => $subscription->Provider_Service->status,
                'web_sub_id' => $subscription->web_sub_id,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'status' => $subscription->status,
                'price' => $subscription->WebSub->price,
                'time' => $subscription->WebSub->time,
            ];
        });
    }


    public function updateSubscriptionStatus($id, $newStatus)
    {
        $subscription = Subscribe::find($id);

        $subscription->status = $newStatus;
        $subscription->start_date = Carbon::now();
        $subscription->end_date = Carbon::now()->addMonths($subscription->WebSub->time);

        $subscription->save();

        return $subscription;
    }

}
