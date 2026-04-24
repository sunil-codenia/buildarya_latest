<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SanctumTenantMiddleware
{
    /**
     * Handle an incoming request for a Tenant (API version).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json(['message' => 'Unauthorized. Please login again.'], 401);
        }

        // 1. Get Company details using the current token name (which stores the connection name)
        $token = $user->currentAccessToken();
        if (!$token) {
             return response()->json(['message' => 'Token not found.'], 401);
        }
        
        $connName = $token->name;

        // 2. Fetch Company Database details from the main DB
        $company = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $connName)
            ->orWhere('db_name', $connName)
            ->first();

        if (!$company || empty($company->db_conn_name)) {
            return response()->json(['message' => 'Company database configuration not found for ' . $connName], 403);
        }

        // 3. Switch Connection
        // We set the default connection for the duration of the request
        Config::set('database.default', $company->db_conn_name);
        
        // 4. Inject Session-like variables into the Request object for use in old code
        // This helps maintain compatibility with helpers that use session()
        $request->merge([
            'tenant_conn' => $company->db_conn_name,
            'tenant_uid' => $user->id,
            'tenant_role' => $user->role_id,
            'tenant_site_id' => $user->site_id,
        ]);

        // Also mock the session if needed (though API shouldn't rely on it, some legacy helpers might)
        session([
            'comp_db_conn_name' => $company->db_conn_name,
            'uid' => $user->id,
            'role' => $user->role_id,
            'site_id' => $user->site_id,
            'is_superadmin' => $user->role_id == 1 ? 'yes' : 'no'
        ]);

        return $next($request);
    }
}
