<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CustomAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // For API requests, check web session first, then Sanctum guard
        if ($request->is('api/*')) {
            if (session()->has('key') || session()->has('uid')) {
                return $next($request);
            }
            $apiUser = \Illuminate\Support\Facades\Auth::guard('sanctum')->user();
            if ($apiUser) {
                \Illuminate\Support\Facades\Auth::setUser($apiUser);
                return $next($request);
            }
            return response()->json([
                'status' => 'Failed',
                'message' => 'Unauthorized. Please login again.'
            ], 401);
        }

        if(! session()->has('key')) {
            return redirect('/login');
        }

        // Dynamically refresh view_duration and add_duration from database on every web request
        $uid = session()->get('uid');
        $conn = session()->get('comp_db_conn_name');
        if ($uid && $conn) {
            try {
                $user_details = \Illuminate\Support\Facades\DB::connection($conn)->table('users')->where('id', $uid)->first();
                if ($user_details) {
                    $role_details = \Illuminate\Support\Facades\DB::connection($conn)->table('roles')->where('id', $user_details->role_id)->first();
                    
                    $view_duration = getUserViewDuration($user_details, $conn);
                    $add_duration = getUserAddDuration($user_details, $conn);
                    
                    session()->put('view_duration', $view_duration);
                    session()->put('add_duration', $add_duration);
                }
            } catch (\Exception $e) {
                // Fail silently if there's any DB issue during check
            }
        }

        // Live Expiry Check (Auto-Logout) - Allows access on the day of expiration
        $raw_expiry = session('raw_expiry_date');
        if (!empty($raw_expiry)) {
            $expiry = Carbon::parse($raw_expiry)->startOfDay();
            if (Carbon::now()->startOfDay()->gt($expiry)) {
                $request->session()->flush();
                return redirect('/login')->with('errorcode', "Your session has ended because your subscription expired on " . $expiry->format('d M Y') . ". Please renew to continue.");
            }
        }

        return $next($request);
    }
}
