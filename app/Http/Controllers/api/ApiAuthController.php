<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\User;
use Carbon\Carbon;

class ApiAuthController extends Controller
{
    /**
     * Flutter API Login
     * Accepts: companyid, username, password
     */
    public function login(Request $request)
    {
        \Log::info("Flutter API Login request parameters: " . json_encode($request->except(['password'])));

        $request->validate([
            'username' => 'required',
            'password' => 'required',
            // Allow either companyid or company_name
        ]);

        $companyId = $request->companyid;
        $companyName = $request->company_name;
        $username = $request->username;
        $password = $request->password;

        // 1. Find Company in Main Database (by UID or Name)
        $query = DB::table('companies');
        if ($companyId) {
            $query->where('uid', $companyId);
        } elseif ($companyName) {
            $query->where('name', $companyName);
        } else {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Company ID or Company Name is required.'
            ], 400);
        }

        $company = $query->first();

        if (!$company) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Company does not exist.'
            ], 404);
        }

        if ($company->status !== 'Active') {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Your company account is inactive.'
            ], 403);
        }

        // 2. Check Expiry
        if (!empty($company->expired)) {
            $expiry = Carbon::parse($company->expired)->startOfDay();
            if (Carbon::now()->startOfDay()->gt($expiry)) {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Your subscription expired on ' . $expiry->format('d M Y') . '.'
                ], 403);
            }
        }

        // 3. Switch to Company Database and find User
        try {
            $userData = DB::connection($company->db_conn_name)
                ->table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->leftJoin('sites', 'sites.id', '=', 'users.site_id')
                ->select('users.*', 'roles.name as role_name', 'sites.name as site_name')
                ->where('users.username', $username)
                ->where('users.pass', $password) // Using plain 'pass' as per existing project logic
                ->first();

            if (!$userData) {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Invalid username or password.'
                ], 401);
            }

            if ($userData->status !== 'Active') {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Your user account is inactive.'
                ], 403);
            }

            // Automatically save/register FCM token if present in login payload
            $fcm_id = $request->input('fcm_id') ?? $request->input('fcm_token') ?? $request->input('fcm');
            if ($fcm_id) {
                try {
                    DB::connection($company->db_conn_name)->table('users')->where('id', '=', $userData->id)->update(['fcm_id' => $fcm_id]);
                    $userData->fcm_id = $fcm_id;

                    if (!\Illuminate\Support\Facades\Schema::connection($company->db_conn_name)->hasTable('user_devices')) {
                        DB::connection($company->db_conn_name)->statement("
                            CREATE TABLE IF NOT EXISTS `user_devices` (
                                `id` bigint(20) unsigned AUTO_INCREMENT PRIMARY KEY,
                                `user_id` bigint(20) unsigned NOT NULL,
                                `platform` enum('android','ios','web') NOT NULL,
                                `device_name` varchar(255) NULL,
                                `fcm_token` text NOT NULL,
                                `is_active` tinyint(1) DEFAULT 1,
                                `created_at` timestamp NULL,
                                `updated_at` timestamp NULL,
                                INDEX (`user_id`),
                                INDEX (`is_active`)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                        ");
                    }

                    $platform = $request->input('platform');
                    if (!$platform || !in_array(strtolower($platform), ['android', 'ios', 'web'])) {
                        $userAgent = strtolower($request->header('User-Agent', ''));
                        if (strpos($userAgent, 'android') !== false) {
                            $platform = 'android';
                        } elseif (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false || strpos($userAgent, 'ipod') !== false) {
                            $platform = 'ios';
                        } else {
                            $platform = 'web';
                        }
                    } else {
                        $platform = strtolower($platform);
                    }

                    $deviceName = $request->input('device_name') ?: $request->header('User-Agent', 'Unknown Device');

                    DB::connection($company->db_conn_name)->table('user_devices')
                        ->where('fcm_token', '=', $fcm_id)
                        ->where('user_id', '!=', $userData->id)
                        ->update(['is_active' => 0]);

                    $existing = DB::connection($company->db_conn_name)->table('user_devices')
                        ->where('user_id', '=', $userData->id)
                        ->where('fcm_token', '=', $fcm_id)
                        ->first();

                    if ($existing) {
                        DB::connection($company->db_conn_name)->table('user_devices')
                            ->where('id', '=', $existing->id)
                            ->update([
                                'platform' => $platform,
                                'device_name' => $deviceName,
                                'is_active' => 1,
                                'updated_at' => \Carbon\Carbon::now()->toDateTimeString()
                            ]);
                    } else {
                        DB::connection($company->db_conn_name)->table('user_devices')->insert([
                            'user_id' => $userData->id,
                            'platform' => $platform,
                            'device_name' => $deviceName,
                            'fcm_token' => $fcm_id,
                            'is_active' => 1,
                            'created_at' => \Carbon\Carbon::now()->toDateTimeString(),
                            'updated_at' => \Carbon\Carbon::now()->toDateTimeString()
                        ]);
                    }
                } catch (\Exception $ex) {
                    \Log::error("FCM save during API login failed: " . $ex->getMessage());
                }
            }

            // 4. Generate Sanctum Token
            // We create a temporary User model instance to generate the token
            $user = new User();
            $user->id = $userData->id;
            $user->name = $userData->name;
            $user->username = $userData->username;
            // Set the connection so Eloquent knows which DB to use if needed
            $user->setConnection($company->db_conn_name);

            // Create token named after the company database connection to allow bootstrapping
            $token = $user->createToken($company->db_conn_name)->plainTextToken;

            // 5. Fetch Subscription & Module Permissions
            // Mirroring web logic: prioritize user's specific subscription_plan_id
            $planId = $userData->subscription_plan_id;
            
            $plan = null;
            if ($planId) {
                $plan = DB::table('subscription_plans')->where('id', $planId)->first();
            }

            // Fallback for cases where plan is not directly linked to user
            if (!$plan) {
                $plan = DB::table('subscription_plans')
                    ->where('company_id', $company->id)
                    ->where('status', 'Active')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            $authorizedModules = [];
            if ($plan && !empty($plan->modules)) {
                $allowedModuleIds = json_decode($plan->modules, true);
                if (is_array($allowedModuleIds)) {
                    // Fetch module definitions (Main DB)
                    $modules = DB::table('modules')
                        ->whereIn('id', $allowedModuleIds)
                        ->get();

                    // Fetch user permissions (Tenant DB)
                    $userPermissions = DB::connection($company->db_conn_name)
                        ->table('user_permission')
                        ->where('user_id', $userData->id)
                        ->get();

                    // Fallback to role_permission if user_permission is empty
                    if ($userPermissions->isEmpty()) {
                        $userPermissions = DB::connection($company->db_conn_name)
                            ->table('role_permission')
                            ->where('role_id', $userData->role_id)
                            ->get();
                    }

                    $userPermissions = $userPermissions->keyBy('module_id');

                    foreach ($modules as $module) {
                        $p = $userPermissions->get($module->id);
                        $canView = 0;

                        // Admin (Role 1) logic: See all subscribed modules
                        if ($userData->role_id == 1) {
                            $canView = 1;
                            $perms = [
                                'can_view' => 1, 'can_add' => 1, 'can_edit' => 1,
                                'can_delete' => 1, 'can_certify' => 1, 'can_pay' => 1, 'can_report' => 1
                            ];
                        } else {
                            $canView = ($p && $p->can_view == 1) ? 1 : 0;
                            $perms = [
                                'can_view' => $canView,
                                'can_add' => ($p && $p->can_add == 1) ? 1 : 0,
                                'can_edit' => ($p && $p->can_edit == 1) ? 1 : 0,
                                'can_delete' => ($p && $p->can_delete == 1) ? 1 : 0,
                                'can_certify' => ($p && $p->can_certify == 1) ? 1 : 0,
                                'can_pay' => ($p && $p->can_pay == 1) ? 1 : 0,
                                'can_report' => ($p && $p->can_report == 1) ? 1 : 0,
                            ];
                        }

                        if ($canView) {
                            $authorizedModules[] = [
                                'module_id' => $module->id,
                                'module_name' => $module->name,
                                'permissions' => $perms
                            ];
                        }
                    }
                }
            }

            // Get full user detail with absolute image URL
            $userData->image = !empty($userData->image) ? url($userData->image) : url('images/noprofile.jpg');
            
            // Get Plan details using plan_name
            $planInfo = DB::connection('mysql')->table('subscription_plans')
                ->where('plan_name', $company->plan_name)
                ->first();

            $responseData = [
                'status' => 'Ok',
                'message' => 'Login successful',
                'token' => $token,
                'user' => $userData,
                'company' => [
                    'uid' => $company->uid,
                    'name' => $company->name,
                    'db_conn' => $company->db_conn_name,
                    'expiry_date' => $company->expired,
                    'plan_id' => $planInfo ? $planInfo->id : null,
                    'plan_name' => $company->plan_name ?? 'N/A',
                    'plan_details' => $planInfo
                ],
                'authorized_modules' => $authorizedModules
            ];

            return response()->json($responseData);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Database connection error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Flutter API Logout
     * Revokes the current token
     */
    public function logout(Request $request)
    {
        $user = $request->user('sanctum');
        \Illuminate\Support\Facades\Log::info('Logout API called. User ID: ' . ($user ? $user->id : 'Not authenticated'));
        try {
            
            if ($user && $user->currentAccessToken()) {
                // Revoke the token that was used to authenticate the current request
                $user->currentAccessToken()->delete();
            }

            // Also clear session data if any exists (for mixed state apps)
            // Using a check to avoid errors if session driver is not enabled
            if ($request->hasSession()) {
                $request->session()->flush();
            }

            return response()->json([
                'status' => 'Ok',
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
