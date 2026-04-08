<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CompanyRegistrationController extends Controller
{
    public function register_company(Request $request)
    {
        // ✅ VALIDATION
        $validator = Validator::make($request->all(), [
            'company.name' => 'required|string|max:255',
            'company.db_conn_name' => 'required|string|max:50',

            'modules' => 'required|array',
            'modules.*' => 'integer',

            'user.name' => 'required|string|max:255',
            'user.username' => 'required|string|unique:users,username|max:255',
            'user.pass' => 'required|string|min:4', // ✅ password ONLY here
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

            // ✅ CHECK EXISTING COMPANY
            $company = DB::table('companies')
                ->where('name', $companyData['name'])
                ->first();

            if ($company) {
                $companyId = $company->id;
                $uid = $company->uid;
            } else {
                // ✅ INSERT COMPANY (NO PASSWORD HERE)
                $companyId = DB::table('companies')->insertGetId([
                    'name' => $companyData['name'],
                    'uid' => $uid,
                    'db_conn_name' => $companyData['db_conn_name'],
                    'status' => 1
                ]);
            }

            // ✅ INSERT MODULES (NO DUPLICATE)
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

            // ✅ INSERT USER (PASSWORD HERE)
            $user = $request->user;

            $userId = DB::table('users')->insertGetId([
                'name' => $user['name'],
                'username' => $user['username'],
                'pass' => $user['pass'], // 🔥 correct place
                'company_id' => $companyId,
                'site_id' => $user['site_id'],
                "role_id" => $user['role_id'],
                'status' => 1
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Success',
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