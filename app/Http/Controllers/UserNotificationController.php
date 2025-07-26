<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserNotificationController extends Controller
{
        public function index()
    {
        $user=Auth::user();

        if (!$user) {
            // Return an error response or redirect to a login page
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $notifications = UserNotification::where('user_id',$user->id)
            ->where('status' , 'pending')
            ->latest()->get();
        return response()->json($notifications);
    }


    public function readable_massege()
    {
        $user=Auth::user()->id;
        $notifications = UserNotification::where('user_id',$user)
            ->where('status' , 'read')
            ->latest()->get();
        return response()->json($notifications);
    }


    public function read()
    {
        $user_id = Auth::id();

        // جعل جميع إشعارات المستخدم pending
        UserNotification::where('user_id', $user_id)
            ->where('status', '=', 'pending')
            ->update(['status' => 'read']);

        return response()->json([
            'message' => 'All notifications marked as read',
        ]);
    }
}
