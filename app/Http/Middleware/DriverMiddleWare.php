<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Models\User;
class DriverMiddleWare
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // التحقق من أن المستخدم سائق
        if (!$user || $user->type != 'driver') {
            return response()->json(['error' => 'You are not a Driver'], 401);
        }

        // التحقق من وجود بيانات السائق وحالته
        if ($user->Driver) {
            $status = $user->Driver->status;

            switch ($status) {
                case 'pending':
                    return response()->json([
                        'error' => 'Your driver account is pending admin approval',
                        'status' => 'pending'
                    ], 403);

                case 'pand':
                    return response()->json([
                        'error' => 'Your driver account has been pand',
                        'status' => 'pand'
                    ], 403);

                case 'active':
                    return $next($request);

                default:
                    return response()->json([
                        'error' => 'Your driver account status is invalid',
                        'status' => $status
                    ], 403);
            }
        }

        // إذا لم يكن لديه بيانات سائق
        return response()->json(['error' => 'Driver profile not found'], 403);
    }

}
