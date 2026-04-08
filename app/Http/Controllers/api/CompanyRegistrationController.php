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
            'company.name' => 'required|string|max:255',
            'company.db_conn_name' => 'sometimes|string|max:50',

            'modules' => 'sometimes|array',
            'modules.*' => 'integer',

            'user.name' => 'sometimes|string|max:255',
            'user.username' => 'sometimes|string|max:255',
            'user.pass' => 'sometimes|string|min:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $companyData = $request->company;

            // ✅ GENERATE UID
            $uid = strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $companyData['name']));

            // =========================
            // ✅ FIND COMPANY BY NAME
            // =========================
            $company = DB::table('companies')
                ->where('name', $companyData['name'])
                ->first();

            if ($company) {

                // 🔥 UPDATE COMPANY
                DB::table('companies')
                    ->where('id', $company->id)
                    ->update([
                        'uid' => $uid,
                        'db_conn_name' => $companyData['db_conn_name'] ?? $company->db_conn_name,
                        'status' => $companyData['status'] ?? $company->status,
                    ]);

                $companyId = $company->id;

            } else {

                // 🔥 INSERT COMPANY
                $companyId = DB::table('companies')->insertGetId([
                    'name' => $companyData['name'],
                    'uid' => $uid,
                    'db_conn_name' => $companyData['db_conn_name'] ?? 'mysql',
                    'status' => $companyData['status'] ?? 'Active',
                ]);
            }

            // =========================
            // ✅ MODULE LOGIC
            // =========================
            if ($request->has('modules')) {

                foreach ($request->modules as $moduleId) {

                    $exists = DB::table('company_modules')
                        ->where('company_id', $companyId)
                        ->where('module_id', $moduleId)
                        ->exists();

                    if (!$exists) {
                        DB::table('company_modules')->insert([
                            'company_id' => $companyId,
                            'module_id' => $moduleId
                        ]);
                    }
                }
            }

            // =========================
            // ✅ USER LOGIC
            // =========================
            $userId = null;

            if ($request->has('user')) {

                $user = $request->user;

                $existingUser = DB::table('users')
                    ->where('username', $user['username'])
                    ->where('company_id', $companyId)
                    ->first();

                if ($existingUser) {

                    // 🔥 UPDATE USER
                    DB::table('users')
                        ->where('id', $existingUser->id)
                        ->update([
                            'name' => $user['name'] ?? $existingUser->name,
                            'pass' => $user['pass'] ?? $existingUser->pass,
                            'site_id' => $user['site_id'] ?? $existingUser->site_id,
                            'role_id' => $user['role_id'] ?? $existingUser->role_id,
                            'status' => $user['status'] ?? $existingUser->status,
                            'mobile_only' => $user['mobile_only'] ?? $existingUser->mobile_only,
                        ]);

                    $userId = $existingUser->id;

                } else {

                    // 🔥 INSERT USER
                    $userId = DB::table('users')->insertGetId([
                        'name' => $user['name'],
                        'username' => $user['username'],
                        'pass' => $user['pass'],
                        'company_id' => $companyId,
                        'site_id' => $user['site_id'] ?? null,
                        'role_id' => $user['role_id'] ?? null,
                        'status' => $user['status'] ?? 'Active',
                        'mobile_only' => $user['mobile_only'] ?? 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Processed successfully',
                'company_id' => $companyId,
                'company_uid' => $uid,
                'user_id' => $userId
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}