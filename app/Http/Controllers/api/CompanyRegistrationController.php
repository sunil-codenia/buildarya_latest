<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CompanyRegistrationController extends Controller
{
    public function register_company(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company.id' => 'sometimes|integer',
            'company.name' => 'sometimes|string|max:255',
            'company_name' => 'sometimes|string|max:255',
            'company.db_conn_name' => 'sometimes|string|max:50',

            'company_plan_id' => 'sometimes|integer',
            'plan_name' => 'sometimes|string|max:255',

            'modules' => 'sometimes|array',
            'modules.*' => 'integer',
            'module_names' => 'sometimes|array',
            'module_names.*' => 'string',

            'user.name' => 'sometimes|string|max:255',
            'user.username' => 'sometimes|string|max:255',
            'user.pass' => 'sometimes|string|min:4',
            'user.company_plan_id' => 'sometimes|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // =========================
            // ✅ 1. COMPANY LOGIC
            // =========================
            $companyId = $request->input('company.id') ?? $request->input('company_id');
            $companyName = $request->input('company.name') ?? $request->input('company_name');
            $companyData = $request->input('company', []);
            $uid = null;

            if ($companyId) {
                $company = DB::table('companies')->where('id', $companyId)->first();
                if (!$company) {
                    throw new \Exception("Company with ID $companyId not found.");
                }
                $companyId = $company->id;
                $uid = $company->uid;
            } elseif ($companyName) {
                $company = DB::table('companies')->where('name', $companyName)->first();
                if ($company) {
                    $companyId = $company->id;
                    $uid = $company->uid;
                } else {
                    // Create New Company
                    $uid = strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $companyName));
                    $companyId = DB::table('companies')->insertGetId([
                        'name' => $companyName,
                        'uid' => $uid,
                        'db_conn_name' => $companyData['db_conn_name'] ?? 'mysql',
                        'status' => $companyData['status'] ?? 'Active',
                    ]);
                }
            } else {
                throw new \Exception("Neither company ID nor company name provided.");
            }

            // =========================
            // ✅ 2. PLAN LOGIC
            // =========================
            $planId = $request->input('company_plan_id') ?? $request->input('user.company_plan_id');
            $planName = $request->input('plan_name');

            if ($planId) {
                $plan = DB::table('company_plan')->where('id', $planId)->where('company_id', $companyId)->first();
                if (!$plan) {
                    // If plan not found for THIS company, check if it was intended to be global or create it
                    $plan = DB::table('company_plan')->where('id', $planId)->first();
                    if ($plan && $plan->company_id != $companyId) {
                        // Plan exists but belongs to another company? Handle or throw.
                        // For now, assume it's valid if ID is explicitly passed.
                    }
                }
                $planId = $plan ? $plan->id : $planId;
            } elseif ($planName) {
                $plan = DB::table('company_plan')->where('plan_name', $planName)->where('company_id', $companyId)->first();
                if ($plan) {
                    $planId = $plan->id;
                } else {
                    // Create New Plan
                    $planId = DB::table('company_plan')->insertGetId([
                        'plan_name' => $planName,
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // =========================
            // ✅ 3. MODULE LOGIC
            // =========================
            $moduleIds = $request->input('modules', []);
            $moduleNames = $request->input('module_names', []);

            if (!empty($moduleNames)) {
                $foundModuleIds = DB::table('modules')->whereIn('name', $moduleNames)->pluck('id')->toArray();
                $moduleIds = array_unique(array_merge($moduleIds, $foundModuleIds));
            }

            if (!empty($moduleIds)) {
                foreach ($moduleIds as $moduleId) {
                    $exists = DB::table('company_modules')
                        ->where('company_id', $companyId)
                        ->where('module_id', $moduleId)
                        ->where('company_plan_id', $planId)
                        ->exists();

                    if (!$exists) {
                        DB::table('company_modules')->insert([
                            'company_id' => $companyId,
                            'module_id' => $moduleId,
                            'company_plan_id' => $planId,
                            'created_at' => now()
                        ]);
                    }
                }
            }

            // =========================
            // ✅ 4. USER LOGIC
            // =========================
            $userId = null;
            if ($request->has('user')) {
                $userData = $request->user;
                $username = $userData['username'] ?? null;

                if ($username) {
                    $existingUser = DB::table('users')
                        ->where('username', $username)
                        ->where('company_id', $companyId)
                        ->first();

                    if ($existingUser) {
                        // Update existing user
                        DB::table('users')->where('id', $existingUser->id)->update([
                            'name' => $userData['name'] ?? $existingUser->name,
                            'pass' => $userData['pass'] ?? $existingUser->pass,
                            'company_plan_id' => $planId ?? $existingUser->company_plan_id,
                            'site_id' => $userData['site_id'] ?? $existingUser->site_id,
                            'role_id' => $userData['role_id'] ?? $existingUser->role_id,
                            'status' => $userData['status'] ?? $existingUser->status,
                            'mobile_only' => $userData['mobile_only'] ?? $existingUser->mobile_only,
                        ]);
                        $userId = $existingUser->id;
                    } else {
                        // Create new user
                        $userId = DB::table('users')->insertGetId([
                            'name' => $userData['name'] ?? $username,
                            'username' => $username,
                            'pass' => $userData['pass'] ?? '123456',
                            'company_id' => $companyId,
                            'company_plan_id' => $planId,
                            'site_id' => $userData['site_id'] ?? null,
                            'role_id' => $userData['role_id'] ?? null,
                            'status' => $userData['status'] ?? 'Active',
                            'mobile_only' => $userData['mobile_only'] ?? 'yes',
                            'create_datetime' => now()
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Registration processed successfully',
                'company_id' => $companyId,
                'company_uid' => $uid,
                'plan_id' => $planId,
                'user_id' => $userId
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}