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

    public function getUser(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = DB::connection($conn)->table('users')
                ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
                ->leftJoin('sites', 'sites.id', '=', 'users.site_id')
                ->select('users.*', 'roles.name as role_name', 'sites.name as site_name')
                ->where('users.id', $id)
                ->first();

            if (!$user) {
                return response()->json(['status' => 'Error', 'message' => 'User not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $user]);
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
                    $filename = time() . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images/app_images/' . $conn . '/users'), $filename);
                    $data['image'] = 'images/app_images/' . $conn . '/users/' . $filename;
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
                $filename = time() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('images/app_images/' . $conn . '/users'), $filename);
                $updateData['image'] = 'images/app_images/' . $conn . '/users/' . $filename;
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

            // Handle comma-separated IDs for bulk deletion
            $ids = explode(',', $id);
            $ids = array_map('trim', $ids);
            $ids = array_filter($ids);

            if (count($ids) > 1) {
                // Bulk Deletion Logic
                $deletableIds = [];
                $skippedIds = [];
                foreach ($ids as $uid) {
                    if (isUserDeletable($uid)) {
                        $deletableIds[] = $uid;
                    } else {
                        $skippedIds[] = $uid;
                    }
                }

                if (empty($deletableIds)) {
                    return response()->json([
                        'status' => 'Failed',
                        'message' => 'Selected users cannot be deleted.',
                        'skipped_ids' => $skippedIds
                    ], 400);
                }

                DB::connection($conn)->table('users')->whereIn('id', $deletableIds)->delete();
                foreach ($deletableIds as $uid) {
                    addActivity($uid, 'users', "User Deleted via Bulk API", 1, $user->id, $conn);
                }

                return response()->json([
                    'status' => 'Ok',
                    'message' => count($deletableIds) . ' Users deleted successfully.',
                    'skipped_count' => count($skippedIds)
                ]);
            } else {
                // Single Deletion Logic
                $staff = DB::connection($conn)->table('users')->where('id', $id)->first();
                if (!$staff) return response()->json(['status' => 'Failed', 'message' => 'User not found'], 404);

                if (!isUserDeletable($id)) {
                    return response()->json(['status' => 'Failed', 'message' => 'This user cannot be deleted.'], 400);
                }

                DB::connection($conn)->table('users')->where('id', $id)->delete();
                addActivity($id, 'users', "User Deleted via API", 1, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'User deleted successfully']);
            }
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUserStatus(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $status = $request->input('status');

            if (!$status) {
                return response()->json(['status' => 'Error', 'message' => 'Status is required'], 400);
            }

            $staff = DB::connection($conn)->table('users')->where('id', $id)->first();
            if (!$staff) return response()->json(['status' => 'Failed', 'message' => 'User not found'], 404);

            DB::connection($conn)->table('users')->where('id', $id)->update(['status' => $status]);
            addActivity($id, 'users', "User status updated to $status via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => "User status updated to $status successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDeleteUsers(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            $data = $request->all();
            if ($request->getContent()) {
                $jsonData = json_decode($request->getContent(), true);
                if ($jsonData) $data = array_merge($data, $jsonData);
            }

            $ids = $data['ids'] ?? [];
            if (is_string($ids)) $ids = explode(',', $ids);

            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No User IDs provided'], 400);
            }

            $deletableIds = [];
            $skippedIds = [];
            foreach ($ids as $id) {
                if (isUserDeletable($id)) {
                    $deletableIds[] = $id;
                } else {
                    $skippedIds[] = $id;
                }
            }

            if (empty($deletableIds)) {
                return response()->json([
                    'status' => 'Failed',
                    'message' => 'Selected users cannot be deleted (they may be primary admins or have dependencies).',
                    'skipped_ids' => $skippedIds
                ], 400);
            }

            DB::connection($conn)->table('users')->whereIn('id', $deletableIds)->delete();

            foreach ($deletableIds as $id) {
                addActivity($id, 'users', "User Deleted via Bulk API", 1, $user->id, $conn);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => count($deletableIds) . ' Users deleted successfully.',
                'skipped_count' => count($skippedIds),
                'skipped_ids' => $skippedIds
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function bulkUpdateUsersStatus(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;
            $status = $input['status'] ?? null;

            if (empty($ids) || !$status) {
                return response()->json(['status' => 'Error', 'message' => 'IDs and Status are required'], 400);
            }

            DB::connection($conn)->table('users')->whereIn('id', $ids)->update(['status' => $status]);
            
            foreach ($ids as $id) {
                addActivity($id, 'users', "User status updated to $status via Bulk API", 1, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => "Users updated to $status successfully"]);
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

            // --- HELPER: Delegate to bulk actions if 'ids' is present ---
            // This handles cases where user hits /api/v1/sites with bulk data
            if ($request->has('ids')) {
                if ($request->has('status')) {
                    return $this->bulkUpdateSitesStatus($request);
                } else {
                    return $this->bulkDeleteSites($request);
                }
            }
            // ------------------------------------------------------------
            
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

            // Handle comma-separated IDs for bulk deletion via single route
            $ids = explode(',', $id);
            $ids = array_map('trim', $ids);
            $ids = array_filter($ids);

            if (count($ids) > 1) {
                return $this->processBulkSiteDelete($ids, $conn, $user);
            }

            // Single Deletion
            if (!isSiteDeletable($id)) {
                return response()->json(['status' => 'Failed', 'message' => 'This site cannot be deleted.'], 400);
            }

            DB::connection($conn)->table('sites')->where('id', $id)->delete();
            addActivity($id, 'sites', "Site Deleted via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Site deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDeleteSites(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            $ids = $request->input('ids');

            if (is_string($ids)) $ids = explode(',', $ids);
            if (empty($ids)) return response()->json(['status' => 'Error', 'message' => 'No Site IDs provided'], 400);

            return $this->processBulkSiteDelete($ids, $conn, $user);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    private function processBulkSiteDelete($ids, $conn, $user)
    {
        $deletableIds = [];
        $skippedIds = [];
        foreach ($ids as $sid) {
            if (isSiteDeletable($sid)) {
                $deletableIds[] = $sid;
            } else {
                $skippedIds[] = $sid;
            }
        }

        if (empty($deletableIds)) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Selected sites cannot be deleted.',
                'skipped_ids' => $skippedIds
            ], 400);
        }

        DB::connection($conn)->table('sites')->whereIn('id', $deletableIds)->delete();
        foreach ($deletableIds as $sid) {
            addActivity($sid, 'sites', "Site Deleted via Bulk API", 1, $user->id, $conn);
        }

        return response()->json([
            'status' => 'Ok',
            'message' => count($deletableIds) . ' Sites deleted successfully.',
            'skipped_count' => count($skippedIds),
            'skipped_ids' => $skippedIds
        ]);
    }

    public function bulkUpdateSitesStatus(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $ids = $request->input('ids');
            $status = $request->input('status');

            if (empty($ids) || !$status) {
                return response()->json(['status' => 'Error', 'message' => 'IDs and Status are required'], 400);
            }

            DB::connection($conn)->table('sites')->whereIn('id', $ids)->update(['status' => $status]);
            
            foreach ($ids as $id) {
                addActivity($id, 'sites', "Site status updated to $status via Bulk API", 1, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => "Sites updated to $status successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function listSitePayments(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            // If POST and has amount, user probably wants to RECORD a payment
            if ($request->isMethod('post') && $request->has('amount')) {
                return $this->recordSitePayment($request);
            }

            $site_id = $request->site_id ?? $request->id;
            $format = $request->format ?? 'json';
            $start_date = $request->start_date ?? $request->from_date;
            $end_date = $request->end_date ?? $request->to_date;

            if (!$site_id) return response()->json(['status' => 'Error', 'message' => 'site_id is required'], 400);

            $query = DB::connection($conn)->table('site_payments')
                ->where('site_id', $site_id);

            $search = $request->search;
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('remark', 'like', "%$search%")
                      ->orWhere('amount', 'like', "%$search%")
                      ->orWhere('date', 'like', "%$search%");
                });
            }

            if ($format == 'json') {
                $payments = $query->orderBy('date', 'desc')->paginate($request->per_page ?? 10);
                return response()->json([
                    'status' => 'Ok',
                    'site_name' => DB::connection($conn)->table('sites')->where('id', $site_id)->value('name'),
                    'payments' => $payments
                ]);
            }

            // For Exports, we get all records
            $payments = $query->orderBy('date', 'desc')->get();
            $site_name = DB::connection($conn)->table('sites')->where('id', $site_id)->value('name');
            if ($format == 'pdf') {
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('layouts.users.pdfs.sitePayments', [
                    'data' => $payments,
                    'site_name' => $site_name,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                return $pdf->download("Payments_{$site_name}.pdf");
            } elseif ($format == 'csv') {
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=payments_{$site_id}.csv"];
                return response()->stream(function() use($payments) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Remark', 'Amount']);
                    foreach ($payments as $p) fputcsv($file, [$p->date, $p->remark, $p->amount]);
                    fclose($file);
                }, 200, $headers);
            }

            return response()->json(['status' => 'Error', 'message' => 'Format not supported via API'], 400);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
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
            
            // Detect Company by checking db_conn_name, db_name, or UID
            $company = DB::connection('mysql')->table('companies')
                ->where('db_conn_name', $conn)
                ->orWhere('db_name', $conn)
                ->orWhere('uid', str_replace('company_', '', $conn))
                ->first();

            if (!$company || !$company->plan_name) {
                return response()->json(['status' => 'Error', 'message' => 'Subscription not found for connection: ' . $conn], 404);
            }

            $plan = DB::connection('mysql')->table('subscription_plans')->where('plan_name', $company->plan_name)->first();
            if (!$plan) return response()->json(['status' => 'Error', 'message' => 'Plan not found for: ' . $company->plan_name], 404);

            $rawModules = $plan->modules;
            $allowedModuleIds = [];
            if (is_array($rawModules)) {
                $allowedModuleIds = $rawModules;
            } elseif (is_string($rawModules)) {
                $decoded = json_decode($rawModules, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $allowedModuleIds = $decoded;
                } else {
                    $allowedModuleIds = explode(',', str_replace(['[', ']', '"', ' '], '', $rawModules));
                }
            }

            $modules = DB::connection('mysql')->table('modules')->whereIn('id', $allowedModuleIds)->get();

            $permissions = DB::connection($conn)->table('role_permission')->where('role_id', $id)->get()->keyBy('module_id');

            $data = [];
            foreach ($modules as $m) {
                $p = $permissions->get($m->id);
                $data[] = [
                    'role_id' => (int)$id,
                    'module_id' => $m->id,
                    'module_name' => $m->name,
                    'can_view' => $p ? (int)$p->can_view : 0,
                    'can_add' => $p ? (int)$p->can_add : 0,
                    'can_edit' => $p ? (int)$p->can_edit : 0,
                    'can_delete' => $p ? (int)$p->can_delete : 0,
                    'can_pay' => $p ? (int)$p->can_pay : 0,
                    'can_certify' => $p ? (int)$p->can_certify : 0,
                    'can_report' => $p ? (int)$p->can_report : 0
                ];
            }

            return response()->json([
                'status' => 'Ok',
                'role_id' => $id,
                'role_name' => DB::connection($conn)->table('roles')->where('id', $id)->value('name'),
                'permissions' => $data
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
                ->leftJoin('expense_head', 'expense_party.cost_category_id', '=', 'expense_head.id')
                ->select('expense_party.*', 'sites.name as site_name', 'expense_head.name as category_name');

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('expense_party.name', 'like', "%$search%")
                      ->orWhere('expense_party.address', 'like', "%$search%")
                      ->orWhere('expense_party.pan_no', 'like', "%$search%")
                      ->orWhere('sites.name', 'like', "%$search%")
                      ->orWhere('expense_head.name', 'like', "%$search%");
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

    public function bulkUpdateExpenseParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            // Raw JSON detection
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $parties = $input['parties'] ?? null;

            if (empty($parties)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided'], 400);
            }

            DB::connection($conn)->beginTransaction();
            foreach ($parties as $party) {
                if (!isset($party['id'])) continue;
                
                $updateData = array_intersect_key($party, array_flip(['name', 'address', 'pan_no', 'cost_category_id', 'status']));
                if (!empty($updateData)) {
                    DB::connection($conn)->table('expense_party')->where('id', $party['id'])->update($updateData);
                    addActivity($party['id'], 'expense_party', "Expense Party Updated via Bulk API", 1, $user->id, $conn);
                }
            }
            DB::connection($conn)->commit();

            return response()->json(['status' => 'Ok', 'message' => 'Parties updated successfully']);
        } catch (\Exception $e) { 
            DB::connection($conn)->rollBack();
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); 
        }
    }

    public function bulkDeleteExpenseParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            // Raw JSON detection
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;

            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No IDs provided'], 400);
            }

            // Check if any party is in use
            $inUse = DB::connection($conn)->table('expenses')
                ->where('party_type', 'expense')
                ->whereIn('party_id', $ids)
                ->pluck('party_id')
                ->toArray();

            $deletableIds = array_diff($ids, $inUse);

            if (empty($deletableIds)) {
                return response()->json([
                    'status' => 'Failed', 
                    'message' => 'Selected parties are in use and cannot be deleted!',
                    'skipped_ids' => $inUse
                ], 400);
            }

            DB::connection($conn)->table('expense_party')->whereIn('id', $deletableIds)->delete();
            
            foreach ($deletableIds as $id) {
                addActivity($id, 'expense_party', "Expense Party Deleted via Bulk API", 1, $user->id, $conn);
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => count($deletableIds) . ' Parties deleted successfully.',
                'skipped_count' => count($inUse),
                'skipped_ids' => $inUse
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function bulkUpdateExpensePartiesStatus(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            // Raw JSON detection
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;
            $status = $input['status'] ?? null;

            if (empty($ids) || !$status) {
                return response()->json(['status' => 'Error', 'message' => 'IDs and Status are required'], 400);
            }

            DB::connection($conn)->beginTransaction();
            foreach ($ids as $id) {
                $party = DB::connection($conn)->table('expense_party')->where('id', $id)->first();
                if (!$party) continue;

                DB::connection($conn)->table('expense_party')->where('id', $id)->update(['status' => $status]);
                addActivity($id, 'expense_party', "Status changed to $status via Bulk API", 1, $user->id, $conn);

                if ($status == 'Active' && $party->status == 'Pending') {
                    DB::connection($conn)->table('contact_profile')->insert([
                        'comp_name' => $party->name,
                        'contact_name' => $party->name,
                        'category' => 'Expense Party'
                    ]);
                }
            }
            DB::connection($conn)->commit();

            return response()->json(['status' => 'Ok', 'message' => "Parties updated to $status successfully"]);
        } catch (\Exception $e) { 
            DB::connection($conn)->rollBack();
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); 
        }
    }


    // ==========================================
    // PERMISSIONS MANAGEMENT
    // ==========================================

    public function listUserPermissions(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            
            // Detect Company
            $company = DB::connection('mysql')->table('companies')
                ->where('db_conn_name', $conn)
                ->orWhere('db_name', $conn)
                ->orWhere('uid', str_replace('company_', '', $conn))
                ->first();

            if (!$company || !$company->plan_name) {
                return response()->json(['status' => 'Error', 'message' => 'Subscription not found for connection: ' . $conn], 404);
            }

            $plan = DB::connection('mysql')->table('subscription_plans')->where('plan_name', $company->plan_name)->first();
            if (!$plan) return response()->json(['status' => 'Error', 'message' => 'Plan not found for: ' . $company->plan_name], 404);

            $rawModules = $plan->modules;
            $allowedModuleIds = [];
            if (is_array($rawModules)) {
                $allowedModuleIds = $rawModules;
            } elseif (is_string($rawModules)) {
                $decoded = json_decode($rawModules, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $allowedModuleIds = $decoded;
                } else {
                    $allowedModuleIds = explode(',', str_replace(['[', ']', '"', ' '], '', $rawModules));
                }
            }

            $modules = DB::connection('mysql')->table('modules')->whereIn('id', $allowedModuleIds)->get();
            $permissions = DB::connection($conn)->table('user_permission')->where('user_id', $id)->get()->keyBy('module_id');

            $data = [];
            foreach ($modules as $m) {
                $p = $permissions->get($m->id);
                $data[] = [
                    'user_id' => (int)$id,
                    'module_id' => $m->id,
                    'module_name' => $m->name,
                    'can_view' => $p ? (int)$p->can_view : 0,
                    'can_add' => $p ? (int)$p->can_add : 0,
                    'can_edit' => $p ? (int)$p->can_edit : 0,
                    'can_delete' => $p ? (int)$p->can_delete : 0,
                    'can_pay' => $p ? (int)$p->can_pay : 0,
                    'can_certify' => $p ? (int)$p->can_certify : 0,
                    'can_report' => $p ? (int)$p->can_report : 0
                ];
            }

            return response()->json([
                'status' => 'Ok',
                'user_id' => $id,
                'user_name' => DB::connection($conn)->table('users')->where('id', $id)->value('name'),
                'permissions' => $data
            ]);
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



    public function recordSitePayment(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            
            $site_id = $request->site_id;
            $amount = $request->amount;
            $remark = $request->remark ?? $request->description;
            $date = $request->date ?? $request->payment_date ?? date('Y-m-d');

            if (!$site_id || !$amount) {
                return response()->json(['status' => 'Error', 'message' => 'site_id and amount are required'], 400);
            }

            return DB::transaction(function() use ($conn, $site_id, $amount, $remark, $date, $user) {
                $pay_id = DB::connection($conn)->table('site_payments')->insertGetId([
                    'site_id' => $site_id,
                    'amount' => $amount,
                    'remark' => $remark,
                    'date' => $date
                ]);

                DB::connection($conn)->table('sites_transaction')->insert([
                    'site_id' => $site_id,
                    'type' => 'Credit',
                    'payment_id' => $pay_id
                ]);

                addActivity($pay_id, 'site_payments', "Site Payment Recorded via API. Amount: $amount", 1, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Payment recorded successfully', 'id' => $pay_id]);
            });
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function transferSiteCash(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');

            $from_site = $request->from_site_id ?? $request->from_site;
            $to_site = $request->to_site_id ?? $request->to_site;
            $amount = $request->amount;
            $date = $request->date ?? date('Y-m-d');
            $remark = "Balance Transfer - " . ($request->remark ?? $request->remarks ?? "API Transfer");

            if (!$from_site || !$to_site || !$amount) {
                return response()->json(['status' => 'Error', 'message' => 'from_site_id, to_site_id and amount are required'], 400);
            }

            return DB::transaction(function() use($conn, $user, $from_site, $to_site, $amount, $date, $remark) {
                // Record for FROM site (Debit)
                $pay_id1 = DB::connection($conn)->table('site_payments')->insertGetId([
                    'site_id' => $from_site,
                    'amount' => $amount,
                    'remark' => $remark,
                    'date' => $date
                ]);

                DB::connection($conn)->table('sites_transaction')->insert([
                    'site_id' => $from_site,
                    'type' => 'Debit',
                    'payment_id' => $pay_id1
                ]);

                // Record for TO site (Credit)
                $pay_id2 = DB::connection($conn)->table('site_payments')->insertGetId([
                    'site_id' => $to_site,
                    'amount' => $amount,
                    'remark' => $remark,
                    'date' => $date
                ]);

                DB::connection($conn)->table('sites_transaction')->insert([
                    'site_id' => $to_site,
                    'type' => 'Credit',
                    'payment_id' => $pay_id2
                ]);

                addActivity(0, 'sites', "Site To Site Balance Transfer via API. Amount: $amount", 1, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Balance transferred successfully']);
            });
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function siteStatement(Request $request)
    {
        try {
            $conn = config('database.default');
            
            // Detect Company by checking db_conn_name, db_name, or UID
            $company = DB::connection('mysql')->table('companies')
                ->where('db_conn_name', $conn)
                ->orWhere('db_name', $conn)
                ->orWhere('uid', str_replace('company_', '', $conn))
                ->first();
            
            $site_id = $request->site_id;
            $start_date = $request->start_date ?? $request->from_date;
            $end_date = $request->end_date ?? $request->to_date;
            $format = $request->format ?? 'json'; // json or pdf or excel or csv

            if (!$site_id) return response()->json(['status' => 'Error', 'message' => 'site_id is required'], 400);

            $site = DB::connection($conn)->table('sites')->where('id', $site_id)->first();
            if (!$site) return response()->json(['status' => 'Error', 'message' => 'Site not found'], 404);

            // Fetch ALL transactions to calculate correct opening balance
            $transactions = DB::connection($conn)->table('sites_transaction')
                ->where('site_id', $site_id)
                ->orderBy('id', 'asc')->get();

            $allData = [];
            foreach ($transactions as $t) {
                $row = ['date' => null, 'type' => $t->type, 'ref' => '', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'amount' => 0, 'particular' => '', 'image' => ''];
                
                if ($t->payment_id) {
                    $p = DB::connection($conn)->table('site_payments')->where('id', $t->payment_id)->first();
                    if ($p) {
                        $row['date'] = $p->date;
                        $row['ref'] = ($t->type == 'Credit' ? 'Payment Credit' : 'Payment Debit');
                        $row['amount'] = (float)$p->amount;
                        $row['particular'] = $p->remark;
                    }
                } elseif ($t->payment_voucher_id) {
                    $pv = DB::connection($conn)->table('payment_vouchers')->where('id', $t->payment_voucher_id)->first();
                    if ($pv) {
                        $row['date'] = $pv->date;
                        $row['ref'] = 'Payment Vouchers';
                        $row['ref_no'] = $pv->voucher_no;
                        $row['amount'] = (float)$pv->amount;
                        $row['particular'] = $pv->remark;
                        $row['image'] = $pv->image;
                        $row['user_name'] = getUserDetailsById($pv->created_by)->name ?? '';
                    }
                } elseif ($t->expense_id) {
                    $e = DB::connection($conn)->table('expenses')->where('id', $t->expense_id)->first();
                    if ($e) {
                        $row['date'] = $e->date;
                        $row['ref'] = 'Expense';
                        $row['amount'] = (float)$e->amount;
                        $row['particular'] = $e->particular;
                        $row['image'] = $e->image;
                        $row['user_name'] = getUserDetailsById($e->user_id)->name ?? '';
                    }
                }
                
                if ($row['date']) {
                    $row['credit'] = ($t->type == 'Credit' ? $row['amount'] : '');
                    $row['debit'] = ($t->type == 'Debit' ? $row['amount'] : '');
                    $allData[] = $row;
                }
            }

            // Sort by Date (matching website usort)
            usort($allData, function ($a, $b) {
                return strtotime($a['date']) - strtotime($b['date']);
            });

            // Calculate Opening Balance and Filter Data
            $openingBalance = 0;
            $filteredData = [];
            $start = $start_date ? new \DateTime($start_date) : null;
            $end = $end_date ? (new \DateTime($end_date))->modify('+1 day') : null;

            foreach ($allData as $row) {
                $rowDate = new \DateTime($row['date']);
                if ($start && $rowDate < $start) {
                    $openingBalance += ($row['type'] == 'Credit' ? $row['amount'] : -$row['amount']);
                } elseif ((!$start || $rowDate >= $start) && (!$end || $rowDate < $end)) {
                    $filteredData[] = $row;
                }
            }

            if ($format == 'json') {
                return response()->json([
                    'status' => 'Ok',
                    'site_name' => $site->name,
                    'opening_balance' => $openingBalance,
                    'statement' => array_values($filteredData),
                    'current_balance' => getSiteBalance($site_id, $conn)
                ]);
            }

            // PDF/Excel/CSV Export Logic
            if ($format == 'pdf') {
                $user = $request->user('sanctum');
                $pdfData = [
                    'site_name' => $site->name,
                    'openingBalance' => $openingBalance,
                    'filteredData' => $filteredData,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'sitebalance' => getSiteBalance($site_id, $conn),
                    'primary_color' => $request->session()->get('primary_color')[0] ?? '#34495e',
                    'secondry_color' => $request->session()->get('secondry_color')[0] ?? '#2c3e50',
                    'comp_name' => $company->name ?? 'N/A',
                    'comp_add' => $company->address ?? 'N/A',
                    'comp_mobile' => $company->mobile ?? 'N/A',
                    'comp_email' => $company->email ?? 'N/A',
                    'generator_name' => $user->name ?? 'API User'
                ];
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('layouts.users.pdfs.siteStatement', $pdfData);
                return $pdf->download("Statement_{$site->name}.pdf");
            } elseif ($format == 'excel' || $format == 'xlsx') {
                $file_name = "Statement_{$site->name}.xlsx";
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PaymentExport($conn, $start_date, $end_date, 5, $site_id), $file_name);
            } elseif ($format == 'csv') {
                $file_name = "Statement_{$site->name}.csv";
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\PaymentExport($conn, $start_date, $end_date, 5, $site_id), $file_name);
            }

            return response()->json(['status' => 'Error', 'message' => 'Format not supported via API'], 400);

        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function getSitePayments(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $site = DB::connection($conn)->table('sites')->where('id', $id)->first();
            if (!$site) return response()->json(['status' => 'Error', 'message' => 'Site not found'], 404);

            $data = DB::connection($conn)->table('site_payments')->where('site_id', $id)->orderBy('id', 'desc')->get();
            return response()->json(['status' => 'Ok', 'site_name' => $site->name, 'data' => $data]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function exportSitePayments(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $format = $request->format ?? 'pdf';
            $site = DB::connection($conn)->table('sites')->where('id', $id)->first();
            if (!$site) return response()->json(['status' => 'Error', 'message' => 'Site not found'], 404);

            $data = DB::connection($conn)->table('site_payments')->where('site_id', $id)->orderBy('date', 'desc')->get();

            if ($format == 'pdf') {
                $html = "<h1>Payments for {$site->name}</h1><table border='1' width='100%'><thead><tr><th>Date</th><th>Amount</th><th>Remark</th></tr></thead><tbody>";
                foreach($data as $p) $html .= "<tr><td>{$p->date}</td><td>{$p->amount}</td><td>{$p->remark}</td></tr>";
                $html .= "</tbody></table>";
                return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download("Payments_{$site->name}.pdf");
            } elseif ($format == 'csv') {
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=payments_{$id}.csv"];
                return response()->stream(function() use($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Date', 'Amount', 'Remark']);
                    foreach ($data as $p) fputcsv($file, [$p->date, $p->amount, $p->remark]);
                    fclose($file);
                }, 200, $headers);
            }

            return response()->json(['status' => 'Error', 'message' => 'Format not supported via API'], 400);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    // ==========================================
    // COST CATEGORY MANAGEMENT
    // ==========================================

    public function listCostCategories(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('expense_head');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $data = $query->orderBy('id', 'desc')->paginate(10);
            return response()->json(['status' => 'Ok', 'data' => $data, 'applied_search' => $search]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function storeCostCategory(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $name = $request->input('name');

            if (!$name) return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);

            $id = DB::connection($conn)->table('expense_head')->insertGetId([
                'name' => $name
            ]);

            addActivity($id, 'expense_head', "New Cost Category Created: $name", 12, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Cost Category created successfully', 'id' => $id]);
        } catch (\Exception $e) { 
            if ($e->getCode() == 23000) return response()->json(['status' => 'Error', 'message' => 'Cost Category already exists'], 400);
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); 
        }
    }

    public function updateCostCategory(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $name = $request->input('name');

            if (!$name) return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);

            DB::connection($conn)->table('expense_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'expense_head', "Cost Category Updated: $name", 12, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Cost Category updated successfully']);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function deleteCostCategory(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            // Handle bulk delete if comma separated
            $ids = explode(',', $id);
            $deleted = 0; $skipped = 0;

            foreach($ids as $cid) {
                // Check if in use
                $inUse = DB::connection($conn)->table('expenses')->where('head_id', $cid)->exists();
                if ($inUse) {
                    $skipped++;
                    continue;
                }
                
                $head = DB::connection($conn)->table('expense_head')->where('id', $cid)->first();
                if ($head) {
                    DB::connection($conn)->table('expense_head')->where('id', $cid)->delete();
                    addActivity(0, 'expense_head', "Cost Category Deleted: " . $head->name, 12, $user->id, $conn);
                    $deleted++;
                }
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => "$deleted Cost Categories deleted successfully. $skipped skipped (in use)."
            ]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function bulkUpdateCostCategories(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $updates = $request->input('updates'); // Array of {id, name}

            if (!is_array($updates)) return response()->json(['status' => 'Error', 'message' => 'Updates array required'], 400);

            return DB::transaction(function() use ($updates, $conn, $user) {
                foreach ($updates as $upd) {
                    DB::connection($conn)->table('expense_head')->where('id', $upd['id'])->update(['name' => $upd['name']]);
                    addActivity($upd['id'], 'expense_head', "Cost Category Updated via Bulk API", 12, $user->id, $conn);
                }
                return response()->json(['status' => 'Ok', 'message' => count($updates) . ' Cost Categories updated successfully']);
            });
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function bulkDeleteCostCategories(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $ids = $request->input('ids');

            if (!is_array($ids)) return response()->json(['status' => 'Error', 'message' => 'IDs array required'], 400);

            $deleted = 0; $skipped = 0;
            foreach($ids as $cid) {
                $inUse = DB::connection($conn)->table('expenses')->where('head_id', $cid)->exists();
                if ($inUse) { $skipped++; continue; }
                
                $head = DB::connection($conn)->table('expense_head')->where('id', $cid)->first();
                if ($head) {
                    DB::connection($conn)->table('expense_head')->where('id', $cid)->delete();
                    addActivity(0, 'expense_head', "Cost Category Deleted via Bulk API: " . $head->name, 12, $user->id, $conn);
                    $deleted++;
                }
            }

            return response()->json(['status' => 'Ok', 'message' => "$deleted Cost Categories deleted. $skipped skipped (in use)."]);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }

    public function exportCostCategories(Request $request)
    {
        try {
            $conn = config('database.default');
            $format = $request->format ?? 'pdf';
            $data = DB::connection($conn)->table('expense_head')->orderBy('name', 'asc')->get();

            if ($format == 'pdf') {
                $html = "<h1>Cost Categories</h1><table border='1' width='100%'><thead><tr><th>ID</th><th>Name</th></tr></thead><tbody>";
                foreach($data as $r) $html .= "<tr><td>{$r->id}</td><td>{$r->name}</td></tr>";
                $html .= "</tbody></table>";
                return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->download("Cost_Categories.pdf");
            } elseif ($format == 'csv') {
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=cost_categories.csv"];
                return response()->stream(function() use($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name']);
                    foreach ($data as $r) fputcsv($file, [$r->id, $r->name]);
                    fclose($file);
                }, 200, $headers);
            } elseif ($format == 'excel' || $format == 'xlsx') {
                $headings = ['ID', 'Name'];
                $query = "SELECT id, name FROM expense_head ORDER BY name ASC";
                return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\TableSheetExport($conn, $headings, $query, 'Cost_Categories'), 'Cost_Categories.xlsx');
            }

            return response()->json(['status' => 'Error', 'message' => 'Format not supported via API'], 400);
        } catch (\Exception $e) { return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500); }
    }
}
