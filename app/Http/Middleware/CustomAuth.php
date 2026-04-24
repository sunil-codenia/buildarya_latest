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
        // For API requests, use Sanctum guard to authenticate
        if ($request->is('api/*')) {
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
