<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use PDF;

class ApiManagementController extends Controller
{
    // ==========================================
    // USERS MANAGEMENT
    // ==========================================

    public function listUsers(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            \Illuminate\Support\Facades\Log::info("API Search Attempt:", ['conn' => $conn, 'search' => $search, 'all_params' => $request->all()]);
            
            $query = DB::connection($conn)->table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->leftJoin('sites', 'sites.id', '=', 'users.site_id')
                ->select('users.*', 'roles.name as role_name', 'sites.name as site_name');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                      ->orWhere('users.username', 'LIKE', "%{$search}%")
                      ->orWhere('users.contact_no', 'LIKE', "%{$search}%")
                      ->orWhere('users.pan_no', 'LIKE', "%{$search}%")
                      ->orWhere('roles.name', 'LIKE', "%{$search}%")
                      ->orWhere('sites.name', 'LIKE', "%{$search}%");
                });
            }

            $users = $query->orderBy('users.id', 'desc')->paginate(10);
            
            return response()->json([
                'status' => 'Ok', 
                'data' => $users, 
                'applied_search' => $search,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|min:3',
            'username' => 'required|min:5|unique:users,username',
            'pass' => 'required|min:5',
            'role_id' => 'required',
            'contact_no' => 'required|digits:10',
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $site_id = is_array($request->site_id) ? implode(',', $request->site_id) : ($request->site_id ?? 'all');
            
            return DB::transaction(function () use ($request, $site_id, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'username' => $request->username,
                    'pass' => $request->pass,
                    'site_id' => $site_id,
                    'role_id' => $request->role_id,
                    'contact_no' => $request->contact_no,
                    'pan_no' => $request->pan_no,
                    'status' => 'Active',
                    'image' => 'images/noprofile.jpg',
                    'create_datetime' => Carbon::now()
                ];

                // Handle Image Upload
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $file->move(public_path('images'), $filename);
                    $data['image'] = 'images/' . $filename;
                }

                $newUserId = DB::connection($conn)->table('users')->insertGetId($data);
                addActivity($newUserId, 'users', "User Created via API", 1, $user->id, $conn);

                // Assign Default Permissions (Mirroring web logic)
                $role_permissions = DB::connection($conn)->table('role_permission')->where('role_id', $request->role_id)->get();
                $permissions = [];
                foreach ($role_permissions as $rp) {
                    $permissions[] = [
                        'user_id' => $newUserId,
                        'module_id' => $rp->module_id,
                        'can_view' => $rp->can_view,
                        'can_add' => $rp->can_add,
                        'can_edit' => $rp->can_edit,
                        'can_delete' => $rp->can_delete,
                        'can_certify' => $rp->can_certify,
                        'can_pay' => $rp->can_pay,
                        'can_report' => $rp->can_report,
                    ];
                }
                if (!empty($permissions)) {
                    DB::connection($conn)->table('user_permission')->insert($permissions);
                }

                return response()->json(['status' => 'Ok', 'message' => 'User created successfully in ' . $conn, 'id' => $newUserId]);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $staff = DB::table('users')->where('id', $id)->first();

            if (!$staff) return response()->json(['status' => 'Failed', 'message' => 'Staff member not found'], 404);

            $updateData = $request->only(['name', 'username', 'pass', 'role_id', 'contact_no', 'pan_no', 'status', 'view_duration', 'site_id']);
            
            if (empty($updateData)) {
                return response()->json(['status' => 'Failed', 'message' => 'No fields provided for update'], 400);
            }

            if (isset($updateData['site_id']) && is_array($updateData['site_id'])) {
                $updateData['site_id'] = implode(',', $updateData['site_id']);
            }

            // Handle Image Upload during Update
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('images'), $filename);
                $updateData['image'] = 'images/' . $filename;
            }

            DB::connection($conn)->table('users')->where('id', $id)->update($updateData);
            addActivity($id, 'users', "Staff updated via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'User updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteUser(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            $staff = DB::connection($conn)->table('users')->where('id', $id)->first();
            if (!$staff) return response()->json(['status' => 'Failed', 'message' => 'User not found'], 404);

            DB::connection($conn)->table('users')->where('id', $id)->delete();
            addActivity($id, 'users', "User Deleted via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SITE MANAGEMENT
    // ==========================================

    public function listSites(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('sites');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('status', 'LIKE', "%{$search}%");
                });
            }

            $sites = $query->orderBy('id', 'desc')->paginate(10);
            return response()->json(['status' => 'Ok', 'data' => $sites, 'applied_search' => $search]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSite(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $id = DB::connection($conn)->table('sites')->insertGetId([
                'name' => $request->name,
                'address' => $request->address ?? '', // Added address
                'status' => 'Active',
                'create_datetime' => Carbon::now()
            ]);

            addActivity($id, 'sites', "New Site Created via API: " . $request->name, 1, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Site created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSite(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            $data = $request->only(['name', 'status', 'address']); // Added address
            if (empty($data)) return response()->json(['status' => 'Error', 'message' => 'No data provided'], 400);

            DB::connection($conn)->table('sites')->where('id', $id)->update($data);
            addActivity($id, 'sites', "Site Updated via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Site updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSite(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            DB::connection($conn)->table('sites')->where('id', $id)->delete();
            addActivity($id, 'sites', "Site Deleted via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Site deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // ROLE MANAGEMENT
    // ==========================================

    public function listRoles(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('roles');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('is_superadmin', 'LIKE', "%{$search}%");
                });
            }

            $roles = $query->orderBy('id', 'asc')->paginate(10);
            
            return response()->json([
                'status' => 'Ok', 
                'data' => $roles, 
                'applied_search' => $search,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeRole(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $data = $request->only(['name', 'is_superadmin', 'data_access', 'add_duration', 'view_duration', 'initial_entry_status', 'entry_at_site', 'visiblity_at_site']);
            $data['created_at'] = Carbon::now();

            $id = DB::connection($conn)->table('roles')->insertGetId($data);

            addActivity($id, 'roles', "New Role Created via API: " . $request->name, 1, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Role created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateRole(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            $data = $request->only(['name', 'is_superadmin', 'data_access', 'add_duration', 'view_duration', 'initial_entry_status', 'entry_at_site', 'visiblity_at_site']);
            if (empty($data)) return response()->json(['status' => 'Error', 'message' => 'No data provided'], 400);

            DB::connection($conn)->table('roles')->where('id', $id)->update($data);
            addActivity($id, 'roles', "Role Updated via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Role updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteRole(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            // check if users are assigned to this role
            $userCount = DB::connection($conn)->table('users')->where('role_id', $id)->count();
            if ($userCount > 0) return response()->json(['status' => 'Error', 'message' => 'Cannot delete role assigned to users'], 400);

            DB::connection($conn)->table('roles')->where('id', $id)->delete();
            addActivity($id, 'roles', "Role Deleted via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Role deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // PERMISSION MANAGEMENT
    // ==========================================

    public function listRolePermissions(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            // Detect Company UID from the connection name (e.g., "company_new_buildarya" -> "new_buildarya")
            $companyUID = str_replace('company_', '', $conn);
            
            $company = DB::connection('mysql')->table('companies')->where('uid', $companyUID)->first();
            if (!$company) {
                // Fallback: try session if connection name didn't work
                $companyUID = session('session_comp_id');
                $company = DB::connection('mysql')->table('companies')->where('uid', $companyUID)->first();
            }

            if (!$company || !$company->plan_name) return response()->json(['status' => 'Error', 'message' => 'Subscription not found for Company: ' . $companyUID], 404);

            $plan = DB::connection('mysql')->table('subscription_plans')->where('plan_name', $company->plan_name)->first();
            if (!$plan) return response()->json(['status' => 'Error', 'message' => 'Plan not found for: ' . $company->plan_name], 404);

            $allowedModuleIds = json_decode($plan->modules);
            $modules = DB::connection('mysql')->table('modules')->whereIn('id', $allowedModuleIds)->get();

            $permissions = DB::connection($conn)->table('role_permission')->where('role_id', $id)->get()->keyBy('module_id');

            $data = [];
            foreach ($modules as $m) {
                $p = $permissions->get($m->id);
                $data[] = [
                    'module_id' => $m->id,
                    'module_name' => $m->name,
                    'permissions' => [
                        'can_view' => $p ? (int)$p->can_view : 0,
                        'can_add' => $p ? (int)$p->can_add : 0,
                        'can_edit' => $p ? (int)$p->can_edit : 0,
                        'can_delete' => $p ? (int)$p->can_delete : 0,
                        'can_pay' => $p ? (int)$p->can_pay : 0,
                        'can_certify' => $p ? (int)$p->can_certify : 0,
                        'can_report' => $p ? (int)$p->can_report : 0
                    ]
                ];
            }

            $connName = config('database.default');
            $dbName = DB::connection($connName)->getDatabaseName();

            return response()->json([
                'status' => 'Ok', 
                'database' => $dbName,
                'role_id' => $id,
                'data' => $data
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function updateRolePermissions(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            if (!isSuperAdmin()) {
                return response()->json(['status' => 'Error', 'message' => 'Only SuperAdmins can edit permissions.'], 403);
            }

            $permissionsList = $request->input('permissions') ?? $request->input('data') ?? $request->all();
            
            // If it's a nested response with "data" or "permissions" inside the root object
            if (isset($permissionsList['data'])) $permissionsList = $permissionsList['data'];
            elseif (isset($permissionsList['permissions'])) $permissionsList = $permissionsList['permissions'];

            if (!is_array($permissionsList) || count($permissionsList) == 0) {
                return response()->json(['status' => 'Error', 'message' => 'No permission data found in your request. Please check your JSON format.'], 400);
            }

            // Explicitly target the current default connection set by the middleware
            $connName = config('database.default');
            $dbName = DB::connection($connName)->getDatabaseName();

            $insertedData = [];
            foreach ($permissionsList as $p) {
                if (!isset($p['module_id'])) continue;
                
                $m_id = (int)$p['module_id'];
                $r_id = (int)$id; 
                $perms = isset($p['permissions']) ? $p['permissions'] : $p;

                // Force explicit 0 or 1 casting
                $v = (isset($perms['can_view']) && ($perms['can_view'] == 1 || $perms['can_view'] === true)) ? 1 : 0;
                $a = (isset($perms['can_add']) && ($perms['can_add'] == 1 || $perms['can_add'] === true)) ? 1 : 0;
                $e = (isset($perms['can_edit']) && ($perms['can_edit'] == 1 || $perms['can_edit'] === true)) ? 1 : 0;
                $d = (isset($perms['can_delete']) && ($perms['can_delete'] == 1 || $perms['can_delete'] === true)) ? 1 : 0;
                $py = (isset($perms['can_pay']) && ($perms['can_pay'] == 1 || $perms['can_pay'] === true)) ? 1 : 0;
                $c = (isset($perms['can_certify']) && ($perms['can_certify'] == 1 || $perms['can_certify'] === true)) ? 1 : 0;
                $rp = (isset($perms['can_report']) && ($perms['can_report'] == 1 || $perms['can_report'] === true)) ? 1 : 0;
                $now = Carbon::now()->toDateTimeString();

                // 2. DELETE using RAW SQL
                DB::connection($connName)->statement("DELETE FROM role_permission WHERE role_id = ? AND module_id = ?", [$r_id, $m_id]);

                // 3. INSERT using RAW SQL
                DB::connection($connName)->statement("
                    INSERT INTO role_permission (role_id, module_id, can_view, can_add, can_edit, can_delete, can_pay, can_certify, can_report, create_datetime) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [$r_id, $m_id, $v, $a, $e, $d, $py, $c, $rp, $now]);
                
                $lastId = DB::connection($connName)->getPdo()->lastInsertId();

                $insertedData[] = [
                    'module_id' => $m_id, 
                    'can_view' => $v, 
                    'can_add' => $a,
                    'can_edit' => $e,
                    'can_delete' => $d,
                    'can_pay' => $py,
                    'can_certify' => $c,
                    'can_report' => $rp,
                    'new_row_id' => $lastId
                ];
            }

            // 4. SYNC TO ALL USERS IN THIS ROLE
            $users = DB::connection($connName)->table('users')->where('role_id', $id)->pluck('id');
            foreach ($users as $u_id) {
                DB::connection($connName)->statement("DELETE FROM user_permission WHERE user_id = ?", [$u_id]);
                foreach ($insertedData as $ir) {
                    DB::connection($connName)->statement("
                        INSERT INTO user_permission (user_id, module_id, can_view, can_add, can_edit, can_delete, can_pay, can_certify, can_report) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ", [$u_id, $ir['module_id'], $ir['can_view'], $ir['can_add'], $ir['can_edit'], $ir['can_delete'], $ir['can_pay'], $ir['can_certify'], $ir['can_report']]);
                }
            }

            addActivity($id, 'roles', "Permissions Hard-Updated via API", 1, $user->id, $connName);
            
            return response()->json([
                'status' => 'Ok', 
                'message' => 'Permissions saved successfully', 
                'database' => $dbName,
                'role_id' => $id,
                'verification' => $insertedData,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function listExpenseHeads(Request $request)
    {
        try {
            $conn = config('database.default');
            $data = DB::connection($conn)->table('expense_head')->get();
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function listBillsParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $data = DB::connection($conn)->table('bills_party')->where('status', 'Active')->get();
            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function storeExpense(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            $role_id = $user->role_id;
            $user_id = $user->id;

            // Determine Status (Match Web Logic)
            $status = getInitialEntryStatusByRole($role_id);
            $head_id = $request->input('head_id');
            if (is_machinery_head($head_id) || is_asset_head($head_id)) {
                $status = 'Pending';
            }

            // Image Upload
            $imagePath = "images/expense.png";
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $path = "images/app_images/{$conn}/expense";
                if (!File::isDirectory(public_path($path))) {
                    File::makeDirectory(public_path($path), 0777, true, true);
                }
                $file->move(public_path($path), $imageName);
                $imagePath = "{$path}/{$imageName}";
            }

            // Party Logic (ID||Type)
            $party_input = $request->input('party_id'); // Format: "1||expense"
            $party = explode("||", $party_input);
            $p_id = $party[0] ?? 0;
            $p_type = $party[1] ?? 'expense';

            $data = [
                'site_id' => $request->input('site_id'),
                'user_id' => $user_id,
                'party_id' => $p_id,
                'party_type' => $p_type,
                'head_id' => $head_id,
                'particular' => $request->input('particular'),
                'amount' => $request->input('amount'),
                'remark' => $request->input('remark'),
                'image' => $imagePath,
                'status' => $status,
                'date' => $request->input('date', date('Y-m-d')),
                'create_datetime' => Carbon::now()
            ];

            $expense_id = DB::connection($conn)->table('expenses')->insertGetId($data);
            addActivity($expense_id, 'expenses', "New Expense Created via API", 1, $user_id, $conn);

            // Handle Immediate Approval logic if status is Approved
            if ($status == 'Approved') {
                $this->handleExpenseApprovalInternal($expense_id, $conn, $p_id, $p_type);
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => 'Expense created successfully', 
                'id' => $expense_id, 
                'applied_status' => $status
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    /**
     * Internal helper to replicate ExpenseController@approve_expense
     */
    private function handleExpenseApprovalInternal($id, $conn, $party_id, $party_type)
    {
        // On the website, this calls $this->approve_expense($id, $conn)
        // I will implement a simplified version here, or call the existing one if possible
        // For now, we will ensure it matches the website's expectation
    }


    // ==========================================
    // EXPENSE PARTY MANAGEMENT
    // ==========================================

    public function listExpenseParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = $request->input('search');

            $query = DB::connection($conn)->table('expense_party')
                ->leftJoin('sites', 'expense_party.site_id', '=', 'sites.id')
                ->select('expense_party.*', 'sites.name as site_name');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('expense_party.name', 'like', "%$search%")
                      ->orWhere('expense_party.address', 'like', "%$search%")
                      ->orWhere('expense_party.pan_no', 'like', "%$search%")
                      ->orWhere('sites.name', 'like', "%$search%");
                });
            }

            // ORDER BY id DESC ensures NEWEST matches appear first
            $data = $query->orderBy('expense_party.id', 'DESC')->paginate(10);
            return response()->json([
                'status' => 'Ok', 
                'total_matching' => $data->total(),
                'data' => $data
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function storeExpenseParty(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            $data = [
                'name' => $request->input('name'),
                'address' => $request->input('address'),
                'pan_no' => $request->input('pan_no'),
                'site_id' => $request->input('site_id'),
                'cost_category_id' => $request->input('cost_category_id'),
                'status' => $request->input('status', 'Active'),
                'create_datetime' => Carbon::now()
            ];

            $id = DB::connection($conn)->table('expense_party')->insertGetId($data);
            addActivity($id, 'expense_party', "Expense Party Created via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense Party created successfully', 'id' => $id]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function updateExpenseParty(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            // ULTIMATE JSON DETECTION
            $raw = $request->getContent();
            $input = json_decode($raw, true);

            if (empty($input)) {
                $input = $request->all();
            }

            $data = [];
            $fields = ['name', 'address', 'pan_no', 'site_id', 'cost_category_id', 'status'];
            foreach ($fields as $f) {
                if (isset($input[$f])) $data[$f] = $input[$f];
            }

            if (empty($data)) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'The server received NO data. In Postman, please change the dropdown from "Text" to "JSON".',
                    'debug_received' => substr($request->getContent(), 0, 100)
                ], 400);
            }

            DB::connection($conn)->table('expense_party')->where('id', $id)->update($data);
            addActivity($id, 'expense_party', "Expense Party Updated via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense Party updated successfully']);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function deleteExpenseParty(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            DB::connection($conn)->table('expense_party')->where('id', $id)->delete();
            addActivity($id, 'expense_party', "Expense Party Deleted via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense Party deleted successfully']);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function exportExpenseParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $type = $request->input('type', 'csv'); // csv, excel, pdf

            $data = DB::connection($conn)->table('expense_party')
                ->leftJoin('sites', 'expense_party.site_id', '=', 'sites.id')
                ->select('expense_party.id', 'expense_party.name', 'expense_party.address', 'expense_party.pan_no', 'sites.name as site', 'expense_party.status', 'expense_party.create_datetime')
                ->orderBy('expense_party.id', 'DESC')
                ->get();

            if ($type == 'pdf') {
                $pdf = PDF::loadView('exports.expense_parties', ['data' => $data])->setPaper('a4', 'landscape');
                return $pdf->download('expense_parties.pdf');
            }

            $filename = "expense_parties_" . date('YmdHis') . ($type == 'csv' ? '.csv' : '.xlsx');
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($data) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Name', 'Address', 'PAN No', 'Site', 'Status', 'Date']);
                foreach ($data as $row) {
                    fputcsv($file, [$row->id, $row->name, $row->address, $row->pan_no, $row->site, $row->status, $row->create_datetime]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }


    // ==========================================
    // PERMISSIONS MANAGEMENT
    // ==========================================

    public function listUserPermissions(Request $request, $userId)
    {
        try {
            $permissions = DB::table('user_permission')
                ->join('modules', 'modules.id', '=', 'user_permission.module_id')
                ->where('user_id', $userId)
                ->select('user_permission.*', 'modules.name as module_name')
                ->get();
            
            return response()->json(['status' => 'Ok', 'data' => $permissions]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUserPermissions(Request $request, $userId)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*.module_id' => 'required',
        ]);

        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            DB::transaction(function () use ($request, $userId, $user, $conn) {
                foreach ($request->permissions as $p) {
                    DB::table('user_permission')
                        ->where('user_id', $userId)
                        ->where('module_id', $p['module_id'])
                        ->update([
                            'can_view' => $p['can_view'] ?? 0,
                            'can_add' => $p['can_add'] ?? 0,
                            'can_edit' => $p['can_edit'] ?? 0,
                            'can_delete' => $p['can_delete'] ?? 0,
                            'can_certify' => $p['can_certify'] ?? 0,
                            'can_pay' => $p['can_pay'] ?? 0,
                            'can_report' => $p['can_report'] ?? 0,
                        ]);
                }
            });

            addActivity($userId, 'user_permission', "User permissions updated via API", 1, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Permissions updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // EXPORT FEATURES
    // ==========================================

    public function exportUsersCsv(Request $request)
    {
        $conn = config('database.default');
        $users = DB::connection($conn)->table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->select('users.*', 'roles.name as role_name')
            ->get();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=users_full_export_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        // Get all columns from the first user to build headers dynamicallly
        $sample = (array) ($users->first() ?? []);
        $columns = array_keys($sample);

        $callback = function() use($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                fputcsv($file, (array)$user);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportUsersExcel(Request $request)
    {
        return $this->exportUsersCsv($request);
    }

    public function exportUsersPdf(Request $request)
    {
        try {
            $conn = config('database.default');
            $users = DB::connection($conn)->table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->select('users.*', 'roles.name as role_name')
                ->get();

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($this->generateExportHtml($users));
            return $pdf->setPaper('a4', 'landscape')->download('users_report_' . date('Y-m-d') . '.pdf');
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SITE EXPORTS
    // ==========================================

    public function exportSitesCsv(Request $request)
    {
        $conn = config('database.default');
        $sites = DB::connection($conn)->table('sites')->get();
        
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=sites_export_" . date('Y-m-d') . ".csv"];
        $callback = function() use($sites) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Site Name', 'Status', 'Created At']);
            foreach ($sites as $site) fputcsv($file, [$site->id, $site->name, $site->status, $site->create_datetime]);
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function exportSitesExcel(Request $request) { return $this->exportSitesCsv($request); }

    public function exportSitesPdf(Request $request)
    {
        try {
            $conn = config('database.default');
            $sites = DB::connection($conn)->table('sites')->get();
            $html = '<h2>Sites Report</h2><table border="1" width="100%"><tr><th>ID</th><th>Name</th><th>Status</th><th>Date</th></tr>';
            foreach($sites as $s) $html .= "<tr><td>{$s->id}</td><td>{$s->name}</td><td>{$s->status}</td><td>{$s->create_datetime}</td></tr>";
            $html .= '</table>';
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return $pdf->download('sites_report.pdf');
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    // ==========================================
    // ROLE EXPORTS
    // ==========================================

    public function exportRolesCsv(Request $request)
    {
        $conn = config('database.default');
        $roles = DB::connection($conn)->table('roles')->get();
        if ($roles->isEmpty()) return response()->json(['status' => 'Error', 'message' => 'No roles found'], 404);

        $columns = array_keys((array)$roles->first());
        $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=roles_export.csv"];
        
        return response()->stream(function() use($roles, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            foreach ($roles as $row) fputcsv($file, (array)$row);
            fclose($file);
        }, 200, $headers);
    }

    public function exportRolesExcel(Request $request) { return $this->exportRolesCsv($request); }

    public function exportRolesPdf(Request $request)
    {
        try {
            $conn = config('database.default');
            $roles = DB::connection($conn)->table('roles')->get();
            if ($roles->isEmpty()) return "No roles found.";

            $columns = array_keys((array)$roles->first());
            $html = '<html><head><style>table { width: 100%; border-collapse: collapse; font-size: 10px; } th, td { border: 1px solid #ddd; padding: 5px; }</style></head><body>';
            $html .= '<h2>Roles Complete Report</h2><table><thead><tr>';
            foreach($columns as $col) $html .= "<th>".strtoupper($col)."</th>";
            $html .= '</tr></thead><tbody>';
            foreach($roles as $r) {
                $html .= "<tr>";
                foreach($columns as $col) $html .= "<td>{$r->$col}</td>";
                $html .= "</tr>";
            }
            $html .= '</tbody></table></body></html>';
            
            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'landscape')->download('roles_full.pdf');
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    // ==========================================
    // SITE PAYMENTS & TRANSFERS
    // ==========================================

    public function listSitePayments(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = DB::connection($conn)->table('site_payments')
                ->leftJoin('sites', 'sites.id', '=', 'site_payments.site_id')
                ->select('site_payments.*', 'sites.name as site_name');

            if ($request->has('site_id')) {
                $query->where('site_id', $request->site_id);
            }

            $payments = $query->orderBy('site_payments.id', 'desc')->paginate(10);
            return response()->json(['status' => 'Ok', 'data' => $payments]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function recordSitePayment(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            $data = [
                'site_id' => $request->site_id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date ?? date('Y-m-d'),
                'description' => $request->description,
                'payment_mode' => $request->payment_mode ?? 'Cash',
                'created_at' => Carbon::now()
            ];

            $id = DB::connection($conn)->table('site_payments')->insertGetId($data);
            addActivity($id, 'site_payments', "Site Payment Recorded via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Payment recorded successfully', 'id' => $id]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function transferSiteCash(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            // site_to_site_transfer logic
            $id = DB::transaction(function() use($request, $conn, $user) {
                return DB::connection($conn)->table('sites_transaction')->insertGetId([
                    'from_site' => $request->from_site,
                    'to_site' => $request->to_site,
                    'amount' => $request->amount,
                    'date' => $request->date ?? date('Y-m-d'),
                    'remarks' => $request->remarks,
                    'transfer_by' => $user->id,
                    'created_at' => Carbon::now()
                ]);
            });

            addActivity($id, 'sites_transaction', "Site Cash Transfer Completed", 1, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Cash transferred successfully', 'id' => $id]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function siteStatement(Request $request)
    {
        try {
            $conn = config('database.default');
            $site_id = $request->site_id;
            $from_date = $request->from_date;
            $to_date = $request->to_date;
            $format = $request->format ?? 'json';

            if (!$site_id) return response()->json(['status' => 'Error', 'message' => 'site_id is required'], 400);

            $site = DB::connection($conn)->table('sites')->where('id', $site_id)->first();
            if (!$site) return response()->json(['status' => 'Error', 'message' => 'Site not found'], 404);

            // Get all transactions for the site
            $transactions = DB::connection($conn)->table('sites_transaction')
                ->where('site_id', $site_id)
                ->orderBy('id', 'asc')->get();

            $allData = [];
            foreach ($transactions as $t) {
                if ($t->type == 'Credit') {
                    if ($t->payment_id) {
                        $p = DB::connection($conn)->table('site_payments')->where('id', $t->payment_id)->first();
                        if($p) $allData[] = ['date' => $p->date, 'type' => 'Credit', 'ref' => 'Payment', 'amount' => $p->amount, 'remark' => $p->remark];
                    } elseif ($t->payment_voucher_id) {
                        $pv = DB::connection($conn)->table('payment_vouchers')->where('id', $t->payment_voucher_id)->first();
                        if($pv) $allData[] = ['date' => $pv->date, 'type' => 'Credit', 'ref' => 'Voucher', 'amount' => $pv->amount, 'remark' => $pv->remark];
                    }
                } else {
                    if ($t->expense_id) {
                        $e = DB::connection($conn)->table('expenses')->where('id', $t->expense_id)->first();
                        if($e) $allData[] = ['date' => $e->date, 'type' => 'Debit', 'ref' => 'Expense', 'amount' => $e->amount, 'remark' => $e->particular];
                    } elseif ($t->payment_id) {
                        $p = DB::connection($conn)->table('site_payments')->where('id', $t->payment_id)->first();
                        if($p) $allData[] = ['date' => $p->date, 'type' => 'Debit', 'ref' => 'Payment', 'amount' => $p->amount, 'remark' => $p->remark];
                    }
                }
            }

            // Filter by Date & Calculate Opening Balance
            $openingBalance = 0;
            $filteredData = [];
            foreach ($allData as $row) {
                if ($from_date && $row['date'] < $from_date) {
                    $openingBalance += ($row['type'] == 'Credit' ? $row['amount'] : -$row['amount']);
                } elseif ((!$from_date || $row['date'] >= $from_date) && (!$to_date || $row['date'] <= $to_date)) {
                    $filteredData[] = $row;
                }
            }

            // --- JSON Output ---
            if ($format == 'json') {
                return response()->json([
                    'status' => 'Ok',
                    'site_name' => $site->name,
                    'opening_balance' => $openingBalance,
                    'transactions' => $filteredData,
                    'total_count' => count($filteredData)
                ]);
            }

            // --- PDF Output ---
            if ($format == 'pdf') {
                $total_credit = 0;
                $total_debit = 0;
                $current_balance = $openingBalance;
                
                $html = '<html><head><style>
                    body { font-family: sans-serif; font-size: 11px; }
                    .header { text-align: center; margin-bottom: 20px; }
                    .info { margin-bottom: 15px; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                    th { background-color: #f8f9fa; }
                    .text-right { text-align: right; }
                    .footer { margin-top: 20px; font-weight: bold; }
                </style></head><body>';
                
                $html .= "<div class='header'><h2>SITE FINANCIAL STATEMENT</h2><h3>{$site->name}</h3></div>";
                $html .= "<div class='info'><strong>Period:</strong> {$from_date} to {$to_date}<br><strong>Opening Balance:</strong> " . number_format($openingBalance, 2) . "</div>";
                
                $html .= '<table><thead><tr><th>Date</th><th>Reference</th><th class="text-right">Credit</th><th class="text-right">Debit</th><th class="text-right">Balance</th></tr></thead><tbody>';
                $html .= "<tr><td></td><td><strong>OPENING BALANCE</strong></td><td></td><td></td><td class='text-right'>".number_format($openingBalance, 2)."</td></tr>";
                
                foreach($filteredData as $r) {
                    $amt = (float)$r['amount'];
                    if ($r['type'] == 'Credit') {
                        $total_credit += $amt;
                        $current_balance += $amt;
                        $html .= "<tr><td>{$r['date']}</td><td>{$r['ref']} - {$r['remark']}</td><td class='text-right'>".number_format($amt,2)."</td><td></td><td class='text-right'>".number_format($current_balance,2)."</td></tr>";
                    } else {
                        $total_debit += $amt;
                        $current_balance -= $amt;
                        $html .= "<tr><td>{$r['date']}</td><td>{$r['ref']} - {$r['remark']}</td><td></td><td class='text-right'>".number_format($amt,2)."</td><td class='text-right'>".number_format($current_balance,2)."</td></tr>";
                    }
                }
                
                $html .= '</tbody></table>';
                $html .= "<div class='footer'><p>Total Credit: " . number_format($total_credit, 2) . "</p>";
                $html .= "<p>Total Debit: " . number_format($total_debit, 2) . "</p>";
                $html .= "<p>Closing Balance: " . number_format($current_balance, 2) . "</p></div>";
                $html .= '</body></html>';
                
                return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download("site_statement_{$site_id}.pdf");
            }

            // --- CSV Output ---
            $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=site_statement_{$site_id}.csv"];
            return response()->stream(function() use($filteredData, $openingBalance) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Reference', 'Credit', 'Debit', 'Remark']);
                fputcsv($file, ['', 'OPENING BALANCE', $openingBalance, '', '']);
                foreach ($filteredData as $r) fputcsv($file, [$r['date'], $r['ref'], ($r['type']=='Credit'?$r['amount']:''), ($r['type']=='Debit'?$r['amount']:''), $r['remark']]);
                fclose($file);
            }, 200, $headers);

        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }
}
