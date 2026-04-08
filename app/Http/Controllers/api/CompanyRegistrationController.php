<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CompanyRegistrationController extends Controller
{
    /**
     * Handle onboarding of a new company, its modules, and its primary user.
     *
     * URL: POST /api/register_company
     */
    public function register_company(Request $request)
    {
        // 1. Validation
        $validator = Validator::make($request->all(), [
            'company.name' => 'required|string|max:255',
            'company.address' => 'nullable|string',
            'company.mobile' => 'nullable|string|max:20',
            'company.email' => 'nullable|email|max:255',
            'company.db_conn_name' => 'required|string|max:50',
            'modules' => 'required|array',
            'modules.*' => 'integer',
            'user.name' => 'required|string|max:255',
            'user.username' => 'required|string|unique:users,username|max:255',
            'user.email' => 'required|email|unique:users,email|max:255',
            'user.password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // 2. Create Company
            $companyData = $request->input('company');
            $companyId = DB::table('companies')->insertGetId([
                'name' => $companyData['name'],
                'address' => $companyData['address'] ?? null,
                'mobile' => $companyData['mobile'] ?? null,
                'email' => $companyData['email'] ?? null,
                'db_conn_name' => $companyData['db_conn_name'],
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Assign Modules
            $modules = $request->input('modules');
            foreach ($modules as $moduleId) {
                DB::table('company_modules')->insert([
                    'company_id' => $companyId,
                    'module_id' => $moduleId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Create User
            $userData = $request->input('user');
            $userId = DB::table('users')->insertGetId([
                'name' => $userData['name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'company_id' => $companyId,
                'role_id' => 1, // Assuming Role 1 is Admin/Superadmin for the company context
                'project_id' => 1, // Default project, usually updated by user later
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Company and user successfully onboarded',
                'data' => [
                    'company_id' => $companyId,
                    'user_id' => $userId
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Onboarding failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
