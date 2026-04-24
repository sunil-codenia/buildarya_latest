<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTenantBootstrapper
{
    /**
     * Handle an incoming request.
     * Switch database connection based on the Sanctum token before authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $tokenStr = $request->bearerToken();

        if ($tokenStr) {
            // 1. Find the token record in the main database
            // Sanctum tokens are hashed in the DB if the app uses default settings,
            // but the ID is usually passed as 'ID|token'.
            $tokenId = null;
            if (strpos($tokenStr, '|') !== false) {
                [$tokenId, $tokenStr] = explode('|', $tokenStr, 2);
            }

            // Query the central PersonalAccessToken (managed in mysql DB)
            $token = \App\PersonalAccessToken::where('id', $tokenId)->first();

            if (!$token) {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Invalid or expired session token.'
                ], 401);
            }

            if (!empty($token->name)) {
                $connName = $token->name;

                // 2. Validate that this connection exists in the companies table
                $company = DB::connection('mysql')->table('companies')
                    ->where('db_conn_name', $connName)
                    ->orWhere('db_name', $connName)
                    ->first();

                if ($company) {
                    // 3. Switch the default connection
                    Config::set('database.default', $connName);
                    
                    // Purge to ensure fresh connection with new default
                    DB::connection($connName)->reconnect();
                    DB::purge();
                }
            }
        }

        return $next($request);
    }
}
