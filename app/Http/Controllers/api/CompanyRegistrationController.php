<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Providers\CompanyDatabaseProvider;
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
            // =========================
            // ✅ 1. COMPANY LOGIC
            // =========================
            $companyId = $request->input('company.id') ?? $request->input('company_id');
            $companyName = $request->input('company.name') ?? $request->input('company_name');
            $companyData = $request->input('company', []);
            $uid = null;
            $isNewCompany = false;
            $connName = null;

            if ($companyId) {
                $company = DB::table('companies')->where('id', $companyId)->first();
                if (!$company) {
                    throw new \Exception("Company with ID $companyId not found.");
                }
                $companyId = $company->id;
                $uid = $company->uid;
                $connName = $company->db_conn_name;
            } elseif ($companyName) {
                $company = DB::table('companies')->where('name', $companyName)->first();
                if ($company) {
                    $companyId = $company->id;
                    $uid = $company->uid;
                    $connName = $company->db_conn_name;
                } else {
                    // --- CREATE NEW COMPANY ---
                    // DDL statements (CREATE DATABASE, CREATE USER, GRANT) must run
                    // OUTSIDE any transaction because MySQL auto-commits on DDL.
                    $uid = strtolower(preg_replace('/[^A-Za-z0-9]/', '_', $companyName));
                    $dbNamePrefix = config('database.name_prefix', 'company_');
                    $dbUserPrefix = config('database.user_prefix', 'company_');
                    $dbName = $dbNamePrefix . $uid;
                    $dbUser = $dbUserPrefix . $uid;
                    $dbPass = $this->generateDbPassword();
                    $dbHost = env('DB_HOST', '127.0.0.1');
                    $dbPort = env('DB_PORT', '3306');

                    // Step 1: Create MySQL database + user (DDL - no transaction)
                    $this->createCompanyDatabase($dbName, $dbUser, $dbPass, $dbHost);

                    // Step 2: Insert company record into main DB
                    $companyId = DB::table('companies')->insertGetId([
                        'name' => $companyName,
                        'uid' => $uid,
                        'db_name' => $dbName,
                        'db_conn_name' => $dbName,
                        'db_host' => $dbHost,
                        'db_port' => $dbPort,
                        'db_pass' => $dbPass,
                        'username' => $dbUser,
                        'address' => $companyData['address'] ?? null,
                        'mobile' => $companyData['mobile'] ?? null,
                        'email' => $companyData['email'] ?? null,
                        'status' => $companyData['status'] ?? 'Active',
                    ]);

                    // Step 3: Dynamically register this new connection
                    $newCompany = DB::table('companies')->where('id', $companyId)->first();
                    CompanyDatabaseProvider::registerConnection($newCompany);
                    $connName = $dbName;

                    // Step 4: Import the template schema into the new database
                    $this->importTemplateSchema($dbName, $dbUser, $dbPass, $dbHost, $dbPort);

                    $isNewCompany = true;
                }
            } else {
                throw new \Exception("Neither company ID nor company name provided.");
            }

            // =========================
            // ✅ 2. PLAN LOGIC (main DB)
            // =========================
            $planId = $request->input('company_plan_id') ?? $request->input('user.company_plan_id');
            $planName = $request->input('plan_name');

            if ($planId) {
                $plan = DB::table('company_plan')->where('id', $planId)->where('company_id', $companyId)->first();
                if (!$plan) {
                    $plan = DB::table('company_plan')->where('id', $planId)->first();
                }
                $planId = $plan ? $plan->id : $planId;
            } elseif ($planName) {
                $plan = DB::table('company_plan')->where('plan_name', $planName)->where('company_id', $companyId)->first();
                if ($plan) {
                    $planId = $plan->id;
                } else {
                    $planId = DB::table('company_plan')->insertGetId([
                        'plan_name' => $planName,
                        'company_id' => $companyId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // =========================
            // ✅ 3. MODULE LOGIC (main DB)
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
            // ✅ 4. USER LOGIC (company DB)
            // =========================
            $userId = null;
            $userData = $request->input('user');

            if (!empty($userData) && !empty($connName)) {
                $username = $userData['username'] ?? null;
                $siteId = $userData['site_id'] ?? 45; // Default to 45 if not provided
                $roleId = $userData['role_id'] ?? 1;

                // Ensure site exists in tenant DB
                $existsS = DB::connection($connName)->table('sites')->where('id', $siteId)->exists();
                if (!$existsS) {
                    DB::connection($connName)->table('sites')->insert([
                        'id' => $siteId,
                        'name' => 'Head Office',
                        'address' => 'N/A',
                        'status' => 'Active',
                        'create_datetime' => now()
                    ]);
                }

                if ($username) {
                    $existingUser = DB::connection($connName)->table('users')
                        ->where('username', $username)
                        ->first();

                    if ($existingUser) {
                        // Update existing user
                        DB::connection($connName)->table('users')->where('id', $existingUser->id)->update([
                            'name' => $userData['name'] ?? $existingUser->name,
                            'pass' => $userData['pass'] ?? $existingUser->pass,
                            'company_id' => $companyId,
                            'company_plan_id' => $planId ?? $existingUser->company_plan_id,
                            'site_id' => $userData['site_id'] ?? $existingUser->site_id,
                            'role_id' => $userData['role_id'] ?? $existingUser->role_id,
                            'status' => $userData['status'] ?? $existingUser->status,
                            'mobile_only' => $userData['mobile_only'] ?? $existingUser->mobile_only,
                        ]);
                        $userId = $existingUser->id;
                    } else {
                        // Create new user in company's database
                        $roleId = $userData['role_id'] ?? 1;
                        $siteId = $userData['site_id'] ?? null;

                        $userId = DB::connection($connName)->table('users')->insertGetId([
                            'name' => $userData['name'] ?? $username,
                            'username' => $username,
                            'pass' => $userData['pass'] ?? '123456',
                            'company_id' => $companyId,
                            'company_plan_id' => $planId,
                            'site_id' => $siteId,
                            'role_id' => $roleId,
                            'status' => $userData['status'] ?? 'Active',
                            'mobile_only' => $userData['mobile_only'] ?? 'no',
                            'create_datetime' => now()
                        ]);
                    }

                    // =========================
                    // ✅ 4.5 COPY DB ROLES
                    // =========================
                    if ($isNewCompany) {
                        $masterRoles = DB::table('roles')->get()->map(function($role) {
                            return (array)$role;
                        })->toArray();
                        DB::connection($connName)->table('roles')->truncate();
                        DB::connection($connName)->table('roles')->insert($masterRoles);
                    }

                    // =========================
                    // ✅ 5. ROLE & USER PERMISSIONS (company DB)
                    // =========================
                    $roleId = $userData['role_id'] ?? 1;

                    if (!empty($moduleIds) && $roleId != 1) {
                        // Grant other roles full access to all assigned modules on registration
                        foreach ($moduleIds as $moduleId) {
                            $existsRP = DB::connection($connName)->table('role_permission')
                                ->where('role_id', $roleId)
                                ->where('module_id', $moduleId)
                                ->exists();

                            if (!$existsRP) {
                                DB::connection($connName)->table('role_permission')->insert([
                                    'role_id' => $roleId,
                                    'module_id' => $moduleId,
                                    'can_view' => 1,
                                    'can_add' => 1,
                                    'can_edit' => 1,
                                    'can_certify' => 1,
                                    'can_pay' => 1,
                                    'can_delete' => 1,
                                    'can_report' => 1,
                                    'create_datetime' => now()
                                ]);
                            }
                        }

                        // Grant user full access to all assigned modules
                        foreach ($moduleIds as $moduleId) {
                            $existsUP = DB::connection($connName)->table('user_permission')
                                ->where('user_id', $userId)
                                ->where('module_id', $moduleId)
                                ->exists();

                            if (!$existsUP) {
                                DB::connection($connName)->table('user_permission')->insert([
                                    'user_id' => $userId,
                                    'company_plan_id' => $planId,
                                    'module_id' => $moduleId,
                                    'can_view' => 1,
                                    'can_add' => 1,
                                    'can_edit' => 1,
                                    'can_certify' => 1,
                                    'can_pay' => 1,
                                    'can_delete' => 1,
                                    'can_report' => 1,
                                    'create_datetime' => now()
                                ]);
                            }
                        }
                    }
                }
            }

            return response()->json([
                'status' => true,
                'message' => $isNewCompany
                    ? 'Company created with new database, user, and permissions successfully'
                    : 'Registration processed successfully',
                'company_id' => $companyId,
                'company_uid' => $uid,
                'plan_id' => $planId,
                'user_id' => $userId,
                'is_new_company' => $isNewCompany,
                'db_conn_name' => $connName
            ]);

        } catch (\Exception $e) {
            \Log::error('Company Registration Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new MySQL database and user for a company.
     * NOTE: DDL statements auto-commit in MySQL, so do NOT call this inside a transaction.
     */
    private function createCompanyDatabase($dbName, $dbUser, $dbPass, $dbHost)
    {
        try {
            \Log::info("Starting creation of database: {$dbName}");
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            \Log::info("Database `{$dbName}` created or already exists.");

            // Determine the host for the MySQL user
            // If the DB host is local, we use 'localhost'. 
            // Otherwise, we use '%' to allow connection from any host (common for App/DB separation).
            $host = ($dbHost === '127.0.0.1' || $dbHost === 'localhost') ? 'localhost' : '%';
            \Log::info("Using host '{$host}' for user '{$dbUser}'");

            try {
                DB::statement("CREATE USER '{$dbUser}'@'{$host}' IDENTIFIED BY '{$dbPass}'");
                \Log::info("User '{$dbUser}'@'{$host}' created.");
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false || $e->getCode() == 'HY000') {
                    DB::statement("ALTER USER '{$dbUser}'@'{$host}' IDENTIFIED BY '{$dbPass}'");
                    \Log::info("User '{$dbUser}'@'{$host}' already exists, password updated.");
                } else {
                    \Log::error("Failed to create/alter user: " . $e->getMessage());
                    throw $e;
                }
            }

            DB::statement("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$host}'");
            \Log::info("Privileges granted on `{$dbName}` to '{$dbUser}'@'{$host}'");
            
            // Cross-database JOIN support: Grant SELECT on main DB to company user
            $mainDb = config('database.connections.mysql.database');
            try {
                DB::statement("GRANT SELECT ON `{$mainDb}`.* TO '{$dbUser}'@'{$host}'");
                \Log::info("Select privileges on main DB `{$mainDb}` granted to '{$dbUser}'@'{$host}'");
            } catch (\Exception $e) {
                \Log::warning("Could not grant SELECT on main DB: " . $e->getMessage() . ". Cross-DB joins might fail.");
            }
            
            DB::statement("FLUSH PRIVILEGES");
            \Log::info("Privileges flushed.");

        } catch (\Exception $e) {
            \Log::error("Failed to create database/user: " . $e->getMessage());
            throw new \Exception("Failed to create database: " . $e->getMessage());
        }
    }

    /**
     * Import the template schema SQL into a new company database.
     * Uses the mysql CLI tool for reliable import (handles comments, multi-line statements).
     */
    private function importTemplateSchema($dbName, $dbUser, $dbPass, $dbHost, $dbPort)
    {
        $templatePath = base_path('database/company_template.sql');

        if (!file_exists($templatePath)) {
            \Log::error("Template SQL not found: {$templatePath}");
            throw new \Exception("Company template SQL file not found at: {$templatePath}");
        }

        \Log::info("Attempting to import template schema to {$dbName} using CLI...");

        // Build mysql command (handle empty password case)
        $passArg = !empty($dbPass) ? '--password=' . escapeshellarg($dbPass) : '';
        $cmd = sprintf(
            'mysql --host=%s --port=%s --user=%s %s %s < %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbPort),
            escapeshellarg($dbUser),
            $passArg,
            escapeshellarg($dbName),
            escapeshellarg($templatePath)
        );

        $output = null;
        $returnCode = null;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $errorMsg = implode("\n", $output);
            \Log::warning("Template SQL import via CLI failed (code {$returnCode}): " . substr($errorMsg, 0, 500));

            // Fallback: try PHP-based import if mysql CLI is not available
            \Log::info("Trying PHP-based SQL import as fallback for {$dbName}...");
            $this->importTemplateSchemaPHP($dbName, $dbUser, $dbPass, $dbHost, $dbPort);
        } else {
            \Log::info("Template SQL import via CLI successful for {$dbName}.");
        }
    }

    /**
     * Fallback: PHP-based template schema import.
     * Used when mysql CLI tool is not available.
     */
    private function importTemplateSchemaPHP($dbName, $dbUser, $dbPass, $dbHost, $dbPort)
    {
        $templatePath = base_path('database/company_template.sql');
        $sql = file_get_contents($templatePath);

        $tempConnName = 'temp_new_company';
        config([
            "database.connections.{$tempConnName}" => [
                'driver'    => 'mysql',
                'host'      => $dbHost,
                'port'      => $dbPort,
                'database'  => $dbName,
                'username'  => $dbUser,
                'password'  => $dbPass,
                'charset'   => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix'    => '',
                'strict'    => false,
            ]
        ]);

        try {
            // Remove comment-only lines from the SQL before splitting
            $lines = explode("\n", $sql);
            $cleanedLines = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                // Keep empty lines and non-comment lines
                if ($trimmed === '' || substr($trimmed, 0, 2) !== '--') {
                    $cleanedLines[] = $line;
                }
            }
            $cleanedSql = implode("\n", $cleanedLines);

            // Split by semicolons
            $statements = array_filter(
                array_map('trim', explode(';', $cleanedSql)),
                function ($stmt) {
                    return !empty(trim($stmt));
                }
            );

            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if (!empty($stmt)) {
                    try {
                        DB::connection($tempConnName)->unprepared($stmt);
                    } catch (\Exception $e) {
                        \Log::warning("Template SQL: " . substr($stmt, 0, 80) . " - " . $e->getMessage());
                    }
                }
            }

            DB::purge($tempConnName);

        } catch (\Exception $e) {
            DB::purge($tempConnName);
            throw new \Exception("Failed to import template schema: " . $e->getMessage());
        }
    }

    /**
     * Generate a secure random password for the database user.
     */
    private function generateDbPassword()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < 12; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return '*' . $password . '#';
    }
}