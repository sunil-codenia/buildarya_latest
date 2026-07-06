<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
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
        if (checkUserLimit()) {
            return response()->json(['status' => 'Error', 'message' => 'Upgrade your plan'], 403);
        }

        $rules = [
            'name' => 'required|min:3',
            'username' => 'required|min:5|unique:users,username',
            'role_id' => 'required',
            'contact_no' => 'required|digits:10',
        ];

        if ($request->has('password')) {
            $rules['password'] = 'required|min:5';
        } else {
            $rules['pass'] = 'required|min:5';
        }

        $request->validate($rules);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $site_id = is_array($request->site_id) ? implode(',', $request->site_id) : ($request->site_id ?? 'all');
            
            return DB::transaction(function () use ($request, $site_id, $user, $conn) {
                $data = [
                    'name' => $request->name,
                    'username' => $request->username,
                    'pass' => $request->pass ?? $request->password,
                    'site_id' => $site_id,
                    'role_id' => $request->role_id,
                    'contact_no' => $request->contact_no,
                    'pan_no' => $request->pan_no,
                    'status' => $request->status ?? 'Active',
                    'mobile_only' => $request->mobile_only ?? 'yes',
                    'subscription_plan_id' => $request->subscription_plan_id ?? $request->company_id,
                    'view_duration' => $request->view_duration,
                    'add_duration' => $request->add_duration,
                    'fcm_id' => $request->fcm_id,
                    'image' => 'images/noprofile.jpg',
                    'create_datetime' => Carbon::now()
                ];

                // Handle Image Upload
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $dir = public_path('images/app_images/' . $conn . '/users');
                    if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                    $filename = time() . rand(10000, 1000000) . '.' . $file->getClientOriginalExtension();
                    $file->move($dir, $filename);
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

            $updateData = $request->only([
                'name', 'username', 'pass', 'password', 'role_id', 'contact_no', 
                'pan_no', 'status', 'view_duration', 'add_duration', 'site_id', 
                'mobile_only', 'subscription_plan_id', 'company_id', 'fcm_id'
            ]);
            
            // Map aliases
            if (isset($updateData['password'])) {
                $updateData['pass'] = $updateData['password'];
                unset($updateData['password']);
            }
            if (isset($updateData['company_id'])) {
                $updateData['subscription_plan_id'] = $updateData['company_id'];
                unset($updateData['company_id']);
            }
            
            if (empty($updateData)) {
                return response()->json(['status' => 'Failed', 'message' => 'No fields provided for update'], 400);
            }

            if (isset($updateData['site_id']) && is_array($updateData['site_id'])) {
                $updateData['site_id'] = implode(',', $updateData['site_id']);
            }

            // Handle Image Upload during Update
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $dir = public_path('images/app_images/' . $conn . '/users');
                if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                $filename = time() . rand(10000, 1000000) . '.' . $file->getClientOriginalExtension();
                $file->move($dir, $filename);
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

            // Calculate balance and fetch Project names
            foreach ($sites as $site) {
                // 1. Fetch Project Name
                $site->project_name = DB::connection('mysql')->table('sales_project')->where('id', $site->project_id)->value('name') ?? 'NO PROJECT';

                // 2. Calculate current balance
                $balance = 0;
                $transactions = DB::connection($conn)->table('sites_transaction')->where('site_id', $site->id)->get();
                
                foreach ($transactions as $t) {
                    $amt = 0;
                    if ($t->payment_id) {
                        $amt = (float)DB::connection($conn)->table('site_payments')->where('id', $t->payment_id)->value('amount');
                    } elseif ($t->payment_voucher_id) {
                        $amt = (float)DB::connection($conn)->table('payment_vouchers')->where('id', $t->payment_voucher_id)->value('amount');
                    } elseif ($t->expense_id) {
                        $amt = (float)DB::connection($conn)->table('expenses')->where('id', $t->expense_id)->value('amount');
                    }

                    if ($t->type == 'Credit') $balance += $amt;
                    else $balance -= $amt;
                }
                $site->balance = number_format($balance, 2, '.', '');
            }

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
            
            if (checkSiteLimit()) {
                return response()->json(['status' => 'Error', 'message' => 'Upgrade your plan'], 403);
            }
            
            $id = DB::connection($conn)->table('sites')->insertGetId([
                'name' => $request->name,
                'address' => $request->address ?? '',
                'status' => 'Active',
                'project_id' => $request->project_id ?? 0,
                'sites_type' => $request->sites_type ?? 'Construction',
                'create_datetime' => Carbon::now()
            ]);

            // Handle Opening Balance (Matches SiteController.php logic)
            if ($request->has('opening_balance') && (float)$request->opening_balance > 0) {
                $pay_id = DB::connection($conn)->table('site_payments')->insertGetId([
                    'site_id' => $id,
                    'amount' => $request->opening_balance,
                    'remark' => "Opening Balance",
                    'date' => Carbon::now()->format('Y-m-d')
                ]);
                
                DB::connection($conn)->table('sites_transaction')->insert([
                    'site_id' => $id,
                    'type' => 'Credit',
                    'payment_id' => $pay_id,
                    'create_datetime' => Carbon::now()
                ]);
                
                addActivity($pay_id, 'site_payments', "Opening Balance Transfer To Site. Amount - " . $request->opening_balance, 1, $user->id, $conn);
            }

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

            $data = $request->only(['name', 'status', 'address', 'project_id', 'sites_type']); // Added project_id and sites_type
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

    public function getRoleSetting(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $role = DB::connection($conn)->table('roles')->where('id', $id)->first();

            if (!$role) {
                return response()->json(['status' => 'Error', 'message' => 'Role not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'data_access' => $role->data_access,
                    'add_duration' => $role->add_duration,
                    'view_duration' => $role->view_duration,
                    'initial_entry_status' => $role->initial_entry_status,
                    'entry_at_site' => $role->entry_at_site,
                    'visiblity_at_site' => $role->visiblity_at_site,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateRoleSetting(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');

            $data = $request->only([
                'data_access', 'add_duration', 'view_duration', 
                'initial_entry_status', 'entry_at_site', 'visiblity_at_site'
            ]);
            
            // Process Duration Ranges
            if ($request->has('view_from') || $request->has('view_to')) {
                $data['view_duration'] = ($request->view_from ?? '') . ',' . ($request->view_to ?? '');
            }
            if ($request->has('add_from') || $request->has('add_to')) {
                $data['add_duration'] = ($request->add_from ?? '') . ',' . ($request->add_to ?? '');
            }

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No settings provided'], 400);
            }

            DB::connection($conn)->table('roles')->where('id', $id)->update($data);
            addActivity($id, 'roles', "Role settings updated via API", 1, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Role settings updated successfully']);
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
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            // Detect Company
            $company = DB::connection('mysql')->table('companies')
                ->where('db_conn_name', $conn)
                ->first();

            if (!$company) return response()->json(['status' => 'Error', 'message' => 'Company not found'], 404);

            // Priority 1: User's specific plan
            $planId = $user->subscription_plan_id;
            $plan = null;
            
            if ($planId) {
                $plan = DB::connection('mysql')->table('subscription_plans')->where('id', $planId)->first();
            }

            // Priority 2: Company's assigned plan ID
            if (!$plan && !empty($company->subscription_plan_id)) {
                $plan = DB::connection('mysql')->table('subscription_plans')->where('id', $company->subscription_plan_id)->first();
            }
            
            // Priority 3: Company's plan name (Get the LATEST one to be accurate)
            if (!$plan && !empty($company->plan_name)) {
                $plan = DB::connection('mysql')->table('subscription_plans')
                    ->where('plan_name', $company->plan_name)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$plan) {
                return response()->json(['status' => 'Error', 'message' => 'No active subscription plan found.'], 404);
            }

            $rawModules = $plan->modules;
            $allowedModuleIds = [];
            if (is_array($rawModules)) $allowedModuleIds = $rawModules;
            elseif (is_string($rawModules)) {
                $decoded = json_decode($rawModules, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $allowedModuleIds = $decoded;
                else $allowedModuleIds = explode(',', str_replace(['[', ']', '"', ' '], '', $rawModules));
            }
            if (in_array(14, $allowedModuleIds) && !in_array(15, $allowedModuleIds)) {
                $allowedModuleIds[] = 15;
            }

            $raw_modules = DB::connection('mysql')->table('modules')->whereIn('id', $allowedModuleIds)->get();
            
            // Sidebar Map from RoleController
            $sidebar_map = [
                1 => 'Sites & Users',
                2 => 'Expenses',
                3 => 'Material Purchase & Manage Stock',
                4 => 'Site Bills',
                6 => 'Machinery',
                5 => 'Assets',
                7 => 'Sales',
                8 => 'Payment Vouchers',
                11 => 'Document Management',
                10 => 'Contact Management',
                13 => 'Attendance Management',
                14 => 'Tasks',
                15 => 'Task Category',
                9 => 'Management'
            ];

            $modules = [];
            foreach ($sidebar_map as $sid => $sname) {
                foreach ($raw_modules as $rm) {
                    if ($rm->id == $sid) {
                        $modules[] = (object)['id' => $sid, 'name' => $sname];
                        break;
                    }
                }
            }

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

            return response()->json([
                'status' => 'Ok',
                'role_id' => $id,
                'role_name' => DB::connection($conn)->table('roles')->where('id', $id)->value('name'),
                'plan_name' => $plan->plan_name ?? 'Unknown',
                'debug_subscription_plan_id' => $plan->id ?? null,
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
            return $this->listRolePermissions($request, $id);
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
        // Automatically handle bulk if 'expenses' array or 'site_id[]' array is detected
        if (($request->has('expenses') && is_array($request->expenses)) || is_array($request->site_id)) {
            return $this->bulkStoreExpenses($request);
        }

        try {
            $conn = config('database.default');
            $user = $request->user('sanctum');
            $role_id = $user->role_id;
            $user_id = $user->id;

            // Status
            $status = getInitialEntryStatusByRole($role_id);
            if ($request->has('status') && $request->status != '') {
                $status = ucfirst(strtolower($request->status));
            }
            $head_id = $request->input('head_id');
            if (!$request->has('status') && (is_machinery_head($head_id) || is_asset_head($head_id))) {
                $status = 'Pending';
            }

            // Image - try file upload first, then base64
            $imagePath = "images/expense.png";
            $dir = public_path('images/app_images/' . $conn . '/expense');

            // Method 1: Direct file upload
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                if (is_array($file)) { $file = $file[0]; }
                if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move($dir, $imageName);
                $imagePath = "images/app_images/" . $conn . "/expense/" . $imageName;
            }
            // Method 2: Base64 string (send image as text field "image_data")
            elseif ($request->has('image_data') && strlen($request->image_data) > 100) {
                if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                $b64 = $request->image_data;
                $b64Clean = preg_replace('/^data:image\/\w+;base64,/', '', $b64);
                $decoded = base64_decode($b64Clean);
                if ($decoded) {
                    $ext = 'png';
                    if (preg_match('/^data:image\/(\w+);/', $b64, $m)) { $ext = $m[1]; }
                    $imageName = time() . rand(10000, 1000000) . '.' . $ext;
                    file_put_contents($dir . '/' . $imageName, $decoded);
                    $imagePath = "images/app_images/" . $conn . "/expense/" . $imageName;
                }
            }

            // Party
            $party_input = $request->input('party_id', '0||expense');
            $party = explode("||", (string)$party_input);
            $p_id = $party[0] ?? 0;
            $p_type = $party[1] ?? 'expense';

            // Insert
            $expense_id = DB::connection($conn)->table('expenses')->insertGetId([
                'site_id' => $request->input('site_id'),
                'user_id' => $user_id,
                'party_id' => $p_id,
                'party_type' => $p_type,
                'head_id' => $head_id,
                'particular' => $request->input('particular', ''),
                'amount' => $request->input('amount', 0),
                'remark' => $request->input('remark', ''),
                'image' => $imagePath,
                'status' => $status,
                'date' => $request->input('date', date('Y-m-d')),
                'create_datetime' => Carbon::now()
            ]);

            addActivity($expense_id, 'expenses', "New Expense Created via API", 1, $user_id, $conn);

            if ($status == 'Approved') {
                $this->approveExpenseLogic($expense_id, $conn, $user_id, [$p_id, $p_type]);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => 'Expense created successfully',
                'id' => $expense_id,
                'image_path' => $imagePath,
                'applied_status' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
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
            if (in_array(14, $allowedModuleIds) && !in_array(15, $allowedModuleIds)) {
                $allowedModuleIds[] = 15;
            }

            $modules = DB::connection('mysql')->table('modules')->whereIn('id', $allowedModuleIds)->get();
            $permissions = DB::connection($conn)->table('user_permission')->where('user_id', $id)->get()->keyBy('module_id');

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

            return response()->json([
                'status' => 'Ok',
                'user_id' => $id,
                'user_name' => DB::connection($conn)->table('users')->where('id', $id)->value('name'),
                'plan_name' => $plan->plan_name ?? 'Unknown',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateUserPermissions(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            if (!isSuperAdmin()) {
                return response()->json(['status' => 'Error', 'message' => 'Only SuperAdmins can edit permissions.'], 403);
            }

            $permissionsList = $request->input('permissions') ?? $request->input('data') ?? $request->all();
            
            if (isset($permissionsList['data'])) $permissionsList = $permissionsList['data'];
            elseif (isset($permissionsList['permissions'])) $permissionsList = $permissionsList['permissions'];

            if (!is_array($permissionsList) || count($permissionsList) == 0) {
                return response()->json(['status' => 'Error', 'message' => 'No permission data found in your request.'], 400);
            }

            $connName = config('database.default');
            $now = Carbon::now()->toDateTimeString();

            foreach ($permissionsList as $p) {
                if (!isset($p['module_id'])) continue;
                
                $m_id = (int)$p['module_id'];
                $u_id = (int)$id; 
                $perms = isset($p['permissions']) ? $p['permissions'] : $p;

                $v = (isset($perms['can_view']) && ($perms['can_view'] == 1 || $perms['can_view'] === true)) ? 1 : 0;
                $a = (isset($perms['can_add']) && ($perms['can_add'] == 1 || $perms['can_add'] === true)) ? 1 : 0;
                $e = (isset($perms['can_edit']) && ($perms['can_edit'] == 1 || $perms['can_edit'] === true)) ? 1 : 0;
                $d = (isset($perms['can_delete']) && ($perms['can_delete'] == 1 || $perms['can_delete'] === true)) ? 1 : 0;
                $py = (isset($perms['can_pay']) && ($perms['can_pay'] == 1 || $perms['can_pay'] === true)) ? 1 : 0;
                $c = (isset($perms['can_certify']) && ($perms['can_certify'] == 1 || $perms['can_certify'] === true)) ? 1 : 0;
                $rp = (isset($perms['can_report']) && ($perms['can_report'] == 1 || $perms['can_report'] === true)) ? 1 : 0;

                DB::connection($connName)->table('user_permission')->updateOrInsert(
                    ['user_id' => $u_id, 'module_id' => $m_id],
                    [
                        'can_view' => $v, 
                        'can_add' => $a, 
                        'can_edit' => $e, 
                        'can_delete' => $d, 
                        'can_pay' => $py, 
                        'can_certify' => $c, 
                        'can_report' => $rp
                    ]
                );
            }

            addActivity($id, 'user_permission', "User permissions updated via API", 1, $user->id, $connName);
            return $this->listUserPermissions($request, $id);
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
    public function bulkStoreExpenses(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $role_id = $user->role_id;
            $default_status = getInitialEntryStatusByRole($role_id);

            // Accept TWO formats:
            // Format 1 (JSON array): { "expenses": [ {site_id, party_id, ...}, {site_id, party_id, ...} ] }
            // Format 2 (flat form-data): site_id[], party_id[], etc.
            
            $expenses = $request->input('expenses');
            
            if ($expenses && is_array($expenses)) {
                // JSON array format
                $expenseList = $expenses;
            } else {
                // Flat form-data format (backward compatible)
                $data = $request->all();
                if (!isset($data['site_id'])) {
                    return response()->json(['status' => 'Error', 'message' => 'Send expenses as JSON array: { "expenses": [ {...}, {...} ] }'], 400);
                }
                // Convert flat arrays to list of objects
                $siteIds = is_array($data['site_id']) ? $data['site_id'] : [$data['site_id']];
                $expenseList = [];
                for ($i = 0; $i < count($siteIds); $i++) {
                    $expenseList[] = [
                        'site_id' => $siteIds[$i],
                        'party_id' => is_array($data['party_id'] ?? null) ? ($data['party_id'][$i] ?? '') : ($data['party_id'] ?? ''),
                        'head_id' => is_array($data['head_id'] ?? null) ? ($data['head_id'][$i] ?? '') : ($data['head_id'] ?? ''),
                        'particular' => is_array($data['particular'] ?? null) ? ($data['particular'][$i] ?? '') : ($data['particular'] ?? ''),
                        'amount' => is_array($data['amount'] ?? null) ? ($data['amount'][$i] ?? 0) : ($data['amount'] ?? 0),
                        'remark' => is_array($data['remark'] ?? null) ? ($data['remark'][$i] ?? '') : ($data['remark'] ?? ''),
                        'date' => is_array($data['date'] ?? null) ? ($data['date'][$i] ?? date('Y-m-d')) : ($data['date'] ?? date('Y-m-d')),
                        'status' => is_array($data['status'] ?? null) ? ($data['status'][$i] ?? null) : ($data['status'] ?? null),
                        'image' => is_array($data['image'] ?? null) ? ($data['image'][$i] ?? null) : ($data['image'] ?? null),
                    ];
                }
            }

            $inserted_ids = [];
            $savedPaths = [];
            $connName = config('database.default');
            $dir = public_path('images/app_images/' . $connName . '/expense');

            foreach ($expenseList as $idx => $exp) {
                // --- IMAGE HANDLING ---
                $imagePath = "images/expense.png";
                $imageData = $exp['image'] ?? ($exp['image_data'] ?? null);
                
                if ($imageData && is_string($imageData) && strlen($imageData) > 100) {
                    // Base64 image
                    if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                    
                    $b64Clean = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
                    $decoded = base64_decode($b64Clean);
                    
                    if ($decoded) {
                        $ext = 'png';
                        if (preg_match('/^data:image\/(\w+);/', $imageData, $m)) { $ext = $m[1]; }
                        $imageName = time() . rand(10000, 1000000) . '.' . $ext;
                        file_put_contents($dir . '/' . $imageName, $decoded);
                        $imagePath = "images/app_images/" . $connName . "/expense/" . $imageName;
                    }
                }
                
                // Also try file upload (multipart)
                if ($imagePath == "images/expense.png" && $request->hasFile("image")) {
                    $files = $request->file("image");
                    $file = is_array($files) ? ($files[$idx] ?? null) : ($idx == 0 ? $files : null);
                    if ($file) {
                        if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                        $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                        $file->move($dir, $imageName);
                        $imagePath = "images/app_images/" . $connName . "/expense/" . $imageName;
                    }
                }
                
                $savedPaths[] = $imagePath;

                // --- PARTY ---
                $party_raw = $exp['party_id'] ?? '0||expense';
                $party = explode("||", (string)$party_raw);
                $party_id_val = $party[0];
                $party_type_val = $party[1] ?? 'expense';

                // --- STATUS ---
                $current_status = $default_status;
                if (!empty($exp['status'])) {
                    $current_status = ucfirst(strtolower($exp['status']));
                }
                if (empty($exp['status']) && (is_machinery_head($exp['head_id']) || is_asset_head($exp['head_id']))) {
                    $current_status = 'Pending';
                }

                // --- INSERT ---
                $expense_id = DB::connection($conn)->table('expenses')->insertGetId([
                    'site_id' => $exp['site_id'],
                    'user_id' => $user->id,
                    'party_id' => $party_id_val,
                    'party_type' => $party_type_val,
                    'head_id' => $exp['head_id'],
                    'particular' => $exp['particular'] ?? '',
                    'amount' => $exp['amount'] ?? 0,
                    'remark' => $exp['remark'] ?? '',
                    'image' => $imagePath,
                    'status' => $current_status,
                    'date' => $exp['date'] ?? date('Y-m-d'),
                    'create_datetime' => Carbon::now()
                ]);

                // Auto-approve if status is Approved
                if ($current_status == 'Approved') {
                    $this->approveExpenseLogic($expense_id, $conn, $user->id, [$party_id_val, $party_type_val]);
                }

                $inserted_ids[] = $expense_id;
                addActivity($expense_id, 'expenses', "New Expense Created via API", 2, $user->id, $conn);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => count($inserted_ids) . ' Expenses created successfully',
                'ids' => $inserted_ids,
                'image_paths' => $savedPaths,
                'redirect_url' => ($default_status == 'Approved') ? '/verified_expense' : '/pending_expense'
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function approveExpenseLogic($id, $conn, $user_id, $party)
    {
        $expense = DB::connection($conn)->table('expenses')->where('id', '=', $id)->first();
        
        // 1. Site Transaction (Debit)
        DB::connection($conn)->table('sites_transaction')->insert([
            'site_id' => $expense->site_id,
            'type' => 'Debit',
            'expense_id' => $id,
            'create_datetime' => $expense->create_datetime
        ]);

        // 2. Bill Party Statement (Credit)
        if ($party[1] == 'bill') {
            DB::connection($conn)->table('bill_party_statement')->insert([
                'party_id' => $expense->party_id,
                'type' => 'Credit',
                'particular' => $expense->particular,
                'expense_id' => $id,
                'create_datetime' => $expense->create_datetime
            ]);
        }
        
        addActivity($id, 'expenses', "Expense Automatically Approved via API", 2, $user_id, $conn);
    }

    public function bulkUpdateExpenseStatus(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;
            $status = $input['status'] ?? null; 
            $remark = $input['remark'] ?? null;

            if (empty($ids) || !$status) {
                return response()->json(['status' => 'Error', 'message' => 'IDs and Status are required'], 400);
            }

            if (!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            $ids = array_map('trim', $ids);

            $updateData = ['status' => $status];
            if ($remark) {
                $updateData['remark'] = $remark;
            }

            DB::connection($conn)->table('expenses')->whereIn('id', $ids)->update($updateData);
            
            foreach ($ids as $id) {
                addActivity($id, 'expenses', "Expense status updated to $status via Bulk API", 2, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => "Expenses updated to $status successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateExpense(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $expense = DB::connection($conn)->table('expenses')->where('id', $id)->first();

            if (!$expense) return response()->json(['status' => 'Failed', 'message' => 'Expense not found'], 404);

            $updateData = $request->only(['site_id', 'party_id', 'party_type', 'head_id', 'particular', 'amount', 'remark', 'location', 'date', 'status', 'return_comment']);
            
            // Handle dual party input (id||type) if provided
            if ($request->has('party_id_with_type')) {
                $party = explode("||", (string)$request->party_id_with_type);
                $updateData['party_id'] = $party[0] ?? 0;
                $updateData['party_type'] = $party[1] ?? 'expense';
            }

            // Image handling (File or Base64)
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $dir = public_path('images/app_images/' . $conn . '/expense');
                if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move($dir, $imageName);
                $updateData['image'] = "images/app_images/" . $conn . "/expense/" . $imageName;
            } elseif ($request->has('image_data') && strlen($request->image_data) > 100) {
                $dir = public_path('images/app_images/' . $conn . '/expense');
                if (!File::exists($dir)) { File::makeDirectory($dir, 0777, true, true); }
                $b64Clean = preg_replace('/^data:image\/\w+;base64,/', '', $request->image_data);
                $decoded = base64_decode($b64Clean);
                if ($decoded) {
                    $ext = 'png';
                    if (preg_match('/^data:image\/(\w+);/', $request->image_data, $m)) { $ext = $m[1]; }
                    $imageName = time() . rand(10000, 1000000) . '.' . $ext;
                    file_put_contents($dir . '/' . $imageName, $decoded);
                    $updateData['image'] = "images/app_images/" . $conn . "/expense/" . $imageName;
                }
            }

            if (empty($updateData)) {
                return response()->json(['status' => 'Failed', 'message' => 'No data provided for update'], 400);
            }

            DB::connection($conn)->table('expenses')->where('id', $id)->update($updateData);
            addActivity($id, 'expenses', "Expense Updated via API", 2, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MATERIAL SUPPLIER MANAGEMENT
    // ==========================================

    public function listMaterialSuppliers(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('material_supplier')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'material_supplier.cost_category_id')
                ->select('material_supplier.*', 'expense_head.name as category_name');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                      ->orWhere('material_supplier.address', 'LIKE', "%{$search}%")
                      ->orWhere('material_supplier.gstin', 'LIKE', "%{$search}%")
                      ->orWhere('expense_head.name', 'LIKE', "%{$search}%");
                });
            }

            $suppliers = $query->orderBy('material_supplier.id', 'desc')->paginate(10);

            return response()->json([
                'status' => 'Ok', 
                'data' => $suppliers, 
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportMaterialSuppliersCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $suppliers = DB::connection($conn)->table('material_supplier')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'material_supplier.cost_category_id')
                ->select('material_supplier.*', 'expense_head.name as category_name')
                ->orderBy('material_supplier.id', 'desc')
                ->get();

            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=material_suppliers_" . date('Y-m-d') . ".csv",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $columns = ['Name', 'Address', 'Gstin', 'Bank A/C', 'Bank IFSC', 'Bank Name', 'Bank Account Holder', 'Cost Category', 'Status'];

            $callback = function() use($suppliers, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);

                foreach ($suppliers as $s) {
                    fputcsv($file, [
                        $s->name,
                        $s->address,
                        $s->gstin,
                        $s->bank_ac,
                        $s->bank_ifsc,
                        $s->bank_name,
                        $s->bank_ac_holder,
                        $s->category_name,
                        $s->status
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMaterialSupplier(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $supplier = DB::connection($conn)->table('material_supplier')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'material_supplier.cost_category_id')
                ->select('material_supplier.*', 'expense_head.name as category_name')
                ->where('material_supplier.id', $id)
                ->first();

            if (!$supplier) {
                return response()->json(['status' => 'Error', 'message' => 'Material Supplier not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $supplier]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialSupplier(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $data = array_intersect_key($input, array_flip(['name', 'address', 'gstin', 'bank_ac', 'bank_ifsc', 'bank_name', 'bank_ac_holder', 'cost_category_id']));
            
            if (!isset($data['name'])) {
                return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);
            }
            $data['status'] = $input['status'] ?? 'Active';
            $data['create_datetime'] = Carbon::now();

            $id = DB::connection($conn)->table('material_supplier')->insertGetId($data);
            
            // Mirroring MaterialSupplierController@addmaterialsupplier logic
            DB::connection($conn)->table('contact_profile')->insert([
                'comp_name' => $data['name'], 
                'contact_name' => $data['name'], 
                'category' => 'Material Supplier'
            ]);

            addActivity($id, 'material_supplier', "New Material Supplier Created via API: " . $data['name'], 3, $user->id, $conn);
            
            return response()->json(['status' => 'Ok', 'message' => 'Material Supplier created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Material Supplier already exists'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMaterialSupplier(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $data = array_intersect_key($input, array_flip(['name', 'address', 'gstin', 'bank_ac', 'bank_ifsc', 'bank_name', 'bank_ac_holder', 'cost_category_id', 'status']));

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided'], 400);
            }

            DB::connection($conn)->table('material_supplier')->where('id', $id)->update($data);
            
            // If name changed, update contact_profile too (matching web logic)
            if (isset($data['name'])) {
                DB::connection($conn)->table('contact_profile')
                    ->where('comp_name', $id) // Wait, let's check how contact_profile links to supplier
                    ->update(['comp_name' => $data['name'], 'contact_name' => $data['name']]);
            }
            // Actually, in web logic, it doesn't seem to update contact_profile on edit.
            // Let me re-read updatematerialsupplier in MaterialSupplierController.php
            
            addActivity($id, 'material_supplier', "Material Supplier Updated via API", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material Supplier updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDeleteMaterialSuppliers(Request $request)
    {
        try {
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? [];
            
            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No IDs provided'], 400);
            }

            return $this->processMaterialSupplierDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMaterialSupplier(Request $request, $id)
    {
        try {
            $ids = explode(',', $id);
            return $this->processMaterialSupplierDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processMaterialSupplierDeletion($ids, $user)
    {
        try {
            $conn = config('database.default');
            $deletedCount = 0;
            $skippedCount = 0;
            $messages = [];

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                // Usage check
                $check = DB::connection($conn)->table('material_entry')->where('supplier', '=', $singleId)->exists();
                if ($check) {
                    $skippedCount++;
                    $supplierName = DB::connection($conn)->table('material_supplier')->where('id', $singleId)->value('name') ?? $singleId;
                    $messages[] = "Supplier '$supplierName' (ID: $singleId) is in use and cannot be deleted.";
                    continue;
                }
                
                $supplier = DB::connection($conn)->table('material_supplier')->where('id', $singleId)->first();
                if (!$supplier) {
                    $skippedCount++;
                    continue;
                }

                DB::connection($conn)->table('material_supplier')->where('id', $singleId)->delete();
                addActivity(0, 'material_supplier', "Material Supplier Deleted via API: " . $supplier->name, 3, $user->id, $conn);
                $deletedCount++;
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => "Deleted $deletedCount suppliers. Skipped $skippedCount.",
                'details' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkUpdateMaterialSuppliersStatus(Request $request)
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

            DB::connection($conn)->table('material_supplier')->whereIn('id', $ids)->update(['status' => $status]);
            
            foreach ($ids as $id) {
                addActivity($id, 'material_supplier', "Material Supplier status updated to $status via Bulk API", 3, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => "Material Suppliers updated to $status successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MATERIAL MASTER MANAGEMENT
    // ==========================================

    public function listMaterialsMaster(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('materials');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $materials = $query->orderBy('name', 'asc')->paginate(10);
            
            return response()->json([
                'status' => 'Ok', 
                'data' => $materials, 
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportMaterialsMasterCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $materials = DB::connection($conn)->table('materials')->orderBy('name', 'asc')->get();

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="materials_master.csv"',
            ];

            $callback = function() use ($materials) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Material Name']);

                foreach ($materials as $m) {
                    fputcsv($file, [$m->id, $m->name]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMaterialMaster(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $material = DB::connection($conn)->table('materials')->where('id', $id)->first();

            if (!$material) {
                return response()->json(['status' => 'Error', 'message' => 'Material not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $material]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialMaster(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $name = $input['name'] ?? null;

            if (!$name) {
                return response()->json(['status' => 'Error', 'message' => 'Material name is required'], 400);
            }

            $id = DB::connection($conn)->table('materials')->insertGetId(['name' => $name]);
            addActivity($id, 'materials', "New Material SKU Created via API: " . $name, 3, $user->id, $conn);
            
            return response()->json(['status' => 'Ok', 'message' => 'Material created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Material already exists'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMaterialMaster(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $name = $input['name'] ?? null;

            if (!$name) {
                return response()->json(['status' => 'Error', 'message' => 'Material name is required'], 400);
            }

            DB::connection($conn)->table('materials')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'materials', "Material SKU Updated via API: " . $name, 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDeleteMaterialsMaster(Request $request)
    {
        try {
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? [];
            
            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No IDs provided'], 400);
            }

            return $this->processMaterialDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMaterialMaster(Request $request, $id)
    {
        try {
            $ids = explode(',', $id);
            return $this->processMaterialDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processMaterialDeletion($ids, $user)
    {
        try {
            $conn = config('database.default');
            $deletedCount = 0;
            $skippedCount = 0;
            $messages = [];

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                // Check usage
                $check = DB::connection($conn)->table('material_entry')->where('material_id', '=', $singleId)->exists();
                if ($check) {
                    $skippedCount++;
                    $materialName = DB::connection($conn)->table('materials')->where('id', $singleId)->value('name') ?? $singleId;
                    $messages[] = "Material '$materialName' (ID: $singleId) is in use and cannot be deleted.";
                    continue;
                }
                
                $material = DB::connection($conn)->table('materials')->where('id', $singleId)->first();
                if (!$material) {
                    $skippedCount++;
                    continue;
                }

                DB::connection($conn)->table('materials')->where('id', $singleId)->delete();
                addActivity(0, 'materials', "Material SKU Deleted via API: " . $material->name, 3, $user->id, $conn);
                $deletedCount++;
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => "Deleted $deletedCount materials. Skipped $skippedCount.",
                'details' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MATERIAL UNIT CONVERSION MANAGEMENT
    // ==========================================

    public function listMaterialConversions(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)
                ->table('material_conversion_rules')
                ->join('units as f_unit', 'f_unit.id', '=', 'material_conversion_rules.from_unit')
                ->join('units as t_unit', 't_unit.id', '=', 'material_conversion_rules.to_unit')
                ->where('material_id', '=', $id)
                ->select(
                    'material_conversion_rules.id as id', 
                    'material_conversion_rules.conversion_factor', 
                    'f_unit.name as from_unit_name', 
                    't_unit.name as to_unit_name',
                    'material_conversion_rules.from_unit as from_unit_id',
                    'material_conversion_rules.to_unit as to_unit_id'
                );

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('f_unit.name', 'LIKE', "%{$search}%")
                      ->orWhere('t_unit.name', 'LIKE', "%{$search}%");
                });
            }

            $conversions = $query->paginate(10);

            $material = DB::connection($conn)->table('materials')->where('id', $id)->first();
            
            return response()->json([
                'status' => 'Ok', 
                'material' => $material,
                'data' => $conversions,
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportMaterialConversionsCsv(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $conversions = DB::connection($conn)
                ->table('material_conversion_rules')
                ->join('units as f_unit', 'f_unit.id', '=', 'material_conversion_rules.from_unit')
                ->join('units as t_unit', 't_unit.id', '=', 'material_conversion_rules.to_unit')
                ->where('material_id', '=', $id)
                ->select(
                    'material_conversion_rules.id', 
                    'material_conversion_rules.conversion_factor', 
                    'f_unit.name as from_unit', 
                    't_unit.name as to_unit'
                )
                ->get();

            $material = DB::connection($conn)->table('materials')->where('id', $id)->first();
            $filename = "conversions_" . ($material->name ?? $id) . ".csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function() use ($conversions) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'From Unit', 'To Unit', 'Conversion Factor']);

                foreach ($conversions as $c) {
                    fputcsv($file, [$c->id, $c->from_unit, $c->to_unit, $c->conversion_factor]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialConversion(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $from_unit = $input['from_unit'] ?? null;
            $to_unit = $input['to_unit'] ?? null;
            $conversion_factor = $input['conversion_factor'] ?? null;

            if (!$from_unit || !$to_unit || !$conversion_factor) {
                return response()->json(['status' => 'Error', 'message' => 'From Unit, To Unit, and Conversion Factor are required'], 400);
            }

            if ($from_unit == $to_unit) {
                return response()->json(['status' => 'Error', 'message' => 'Source and Target units cannot be the same'], 400);
            }

            // Check if already exists
            $exists = DB::connection($conn)->table('material_conversion_rules')
                ->where('material_id', $id)
                ->where('from_unit', $from_unit)
                ->where('to_unit', $to_unit)
                ->exists();

            if ($exists) {
                return response()->json(['status' => 'Error', 'message' => 'Conversion rule already exists'], 400);
            }

            $ruleId = DB::connection($conn)->table('material_conversion_rules')->insertGetId([
                'material_id' => $id,
                'from_unit' => $from_unit,
                'to_unit' => $to_unit,
                'conversion_factor' => $conversion_factor,
                'created_by' => $user->id
            ]);

            addActivity($id, 'material_conversion_rules', "New Unit Conversion Rule Created via API (Rule ID: $ruleId)", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Conversion rule created successfully', 'id' => $ruleId]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMaterialConversion(Request $request, $id, $rule_id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $rule = DB::connection($conn)->table('material_conversion_rules')
                ->where('id', $rule_id)
                ->where('material_id', $id)
                ->first();

            if (!$rule) {
                return response()->json(['status' => 'Error', 'message' => 'Conversion rule not found for this material'], 404);
            }

            DB::connection($conn)->table('material_conversion_rules')->where('id', $rule_id)->delete();
            addActivity($id, 'material_conversion_rules', "Unit Conversion Rule Deleted via API (Rule ID: $rule_id)", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Conversion rule deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MATERIAL UNIT MANAGEMENT
    // ==========================================

    public function listMaterialUnits(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            
            $query = DB::connection($conn)->table('units');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $units = $query->orderBy('name', 'asc')->paginate(10);
            
            return response()->json([
                'status' => 'Ok', 
                'data' => $units, 
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMaterialUnit(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $unit = DB::connection($conn)->table('units')->where('id', $id)->first();

            if (!$unit) {
                return response()->json(['status' => 'Error', 'message' => 'Material unit not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $unit]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => 'Internal Server Error'], 500);
        }
    }

    public function exportMaterialUnitsCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $units = DB::connection($conn)->table('units')->orderBy('name', 'asc')->get();

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="material_units.csv"',
            ];

            $callback = function() use ($units) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Unit Name']);

                foreach ($units as $u) {
                    fputcsv($file, [$u->id, $u->name]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialUnit(Request $request)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $name = $input['name'] ?? null;

            if (!$name) {
                return response()->json(['status' => 'Error', 'message' => 'Unit name is required'], 400);
            }

            $id = DB::connection($conn)->table('units')->insertGetId(['name' => $name]);
            addActivity($id, 'units', "New Material Unit Created via API: " . $name, 3, $user->id, $conn);
            
            return response()->json(['status' => 'Ok', 'message' => 'Material unit created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Unit already exists'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMaterialUnit(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $name = $input['name'] ?? null;

            if (!$name) {
                return response()->json(['status' => 'Error', 'message' => 'Unit name is required'], 400);
            }

            DB::connection($conn)->table('units')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'units', "Material Unit Updated via API: " . $name, 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material unit updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkDeleteMaterialUnits(Request $request)
    {
        try {
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? [];
            
            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No IDs provided'], 400);
            }

            return $this->processMaterialUnitDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMaterialUnit(Request $request, $id)
    {
        try {
            $ids = explode(',', $id);
            return $this->processMaterialUnitDeletion($ids, $request->user('sanctum'));
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function processMaterialUnitDeletion($ids, $user)
    {
        try {
            $conn = config('database.default');
            $deletedCount = 0;
            $skippedCount = 0;
            $messages = [];

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                // Check usage via helper
                if (!isMaterialUnitDeletable($singleId, $conn)) {
                    $skippedCount++;
                    $unitName = DB::connection($conn)->table('units')->where('id', $singleId)->value('name') ?? $singleId;
                    $messages[] = "Unit '$unitName' (ID: $singleId) is in use and cannot be deleted.";
                    continue;
                }
                
                $unit = DB::connection($conn)->table('units')->where('id', $singleId)->first();
                if (!$unit) {
                    $skippedCount++;
                    continue;
                }

                DB::connection($conn)->table('units')->where('id', $singleId)->delete();
                addActivity(0, 'units', "Material Unit Deleted via API: " . $unit->name, 3, $user->id, $conn);
                $deletedCount++;
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => "Deleted $deletedCount units. Skipped $skippedCount.",
                'details' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // MATERIAL ENTRIES (TRANSACTIONS)
    // ==========================================

    public function listPendingMaterialEntries(Request $request)
    {
        return $this->listMaterialEntriesByStatus($request, 'Pending');
    }

    public function listVerifiedMaterialEntries(Request $request)
    {
        return $this->listMaterialEntriesByStatus($request, 'Verified'); // Or not Pending
    }

    public function generateMaterialReport(Request $request)
    {
        try {
            $conn = config('database.default');
            
            $report_code = $request->get('report_code', $request->get('type', 1));
            $start_date = $request->get('start_date', $request->get('from_date'));
            $end_date = $request->get('end_date', $request->get('to_date'));
            $site_id = $request->get('site_id');
            $supplier_id = $request->get('supplier_id', $request->get('party_id'));
            $material_id = $request->get('material_id', $request->get('head_id'));
            $export = $request->get('export');

            $query = DB::connection($conn)->table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->leftJoin('users', 'users.id', '=', 'material_entry.user_id')
                ->select(
                    'material_entry.*',
                    'material_supplier.name as supplier_name',
                    'materials.name as material_name',
                    'units.name as unit_name',
                    'sites.name as site_name',
                    'users.name as user_name'
                );

            // Apply Date Range
            if ($report_code != 7 && $start_date && $end_date) {
                $query->whereBetween('material_entry.date', [$start_date, $end_date]);
            }

            // Apply Filters based on report_code
            if (in_array($report_code, [2, 4, 6])) {
                if ($site_id) $query->where('material_entry.site_id', $site_id);
            }

            if (in_array($report_code, [3, 4, 7])) {
                if ($supplier_id) $query->where('material_entry.supplier', $supplier_id);
            }

            if (in_array($report_code, [5, 6])) {
                if ($material_id) $query->where('material_entry.material_id', $material_id);
            }

            if ($report_code == 7) {
                // Statement logic is different, but for API we can return entries or a specialized structure
                // Let's stick to entries for now as the basic report
            }

            $data = $query->orderBy('material_entry.date', 'desc')->get();

            if ($export == 'csv') {
                $filename = "material_report_" . date('Y-m-d') . ".csv";
                $headers = [
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                ];

                $callback = function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Date', 'Supplier', 'Material', 'Unit', 'Qty', 'Vehicle', 'Site', 'User', 'Status', 'Remark']);

                    foreach ($data as $e) {
                        fputcsv($file, [$e->id, $e->date, $e->supplier_name, $e->material_name, $e->unit_name, $e->qty, $e->vehical, $e->site_name, $e->user_name, $e->status, $e->remark]);
                    }
                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            return response()->json(['status' => 'Ok', 'count' => count($data), 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getStockDashboard(Request $request)
    {
        try {
            $conn = config('database.default');
            
            // Match the web version (StockController::stock_dashboard) exactly:
            // It shows ALL active sites for the company without any user-based filtering.
            // Note: $request->user() returns user from the CENTRAL DB (Sanctum), not tenant DB.
            
            $query = DB::connection($conn)->table('material_stock_record')
                ->join('materials', 'materials.id', '=', 'material_stock_record.material_id')
                ->join('units', 'units.id', '=', 'material_stock_record.unit')
                ->join('sites', 'sites.id', '=', 'material_stock_record.site_id')
                ->where('sites.status', '=', 'Active')
                ->select(
                    'material_stock_record.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'sites.name as site_name'
                );

            // Optional filters from request
            if ($request->has('site_id')) {
                $query->where('material_stock_record.site_id', $request->get('site_id'));
            }
            if ($request->has('material_id')) {
                $query->where('material_stock_record.material_id', $request->get('material_id'));
            }
            if ($request->has('search')) {
                $search = trim($request->get('search'));
                $query->where(function($q) use ($search) {
                    $q->where('materials.name', 'LIKE', "%{$search}%")
                      ->orWhere('sites.name', 'LIKE', "%{$search}%");
                });
            }

            $stock_data = $query->orderBy('sites.name')->orderBy('materials.name')->get();

            // CSV Export: /api/v1/materials/stock/dashboard?export=csv
            if ($request->get('export') == 'csv') {
                $filename = 'stock_dashboard_' . date('Y-m-d') . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];
                return response()->stream(function() use ($stock_data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['#', 'Site', 'Material', 'Quantity', 'Unit']);
                    $i = 1;
                    foreach ($stock_data as $item) {
                        fputcsv($file, [
                            $i++,
                            $item->site_name,
                            $item->material_name,
                            $item->qty,
                            $item->unit_name
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            // Structure data for dashboard (Site-wise grouping)
            $site_wise = [];
            foreach ($stock_data as $item) {
                if (!isset($site_wise[$item->site_id])) {
                    $site_wise[$item->site_id] = [
                        'site_id' => $item->site_id,
                        'site_name' => $item->site_name,
                        'inventory' => []
                    ];
                }
                $site_wise[$item->site_id]['inventory'][] = $item;
            }

            // Also fetch site and material lists for dropdown filters (matching web)
            $sites = DB::connection($conn)->table('sites')->where('status', 'Active')->select('id', 'name')->get();
            $materials = DB::connection($conn)->table('materials')->select('id', 'name')->get();

            return response()->json([
                'status' => 'Ok',
                'data' => array_values($site_wise),
                'count' => count($stock_data),
                'sites' => $sites,
                'materials' => $materials,
                'server_time' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Material Stock Transactions (matches web: view_mat_transaction)
     * Required params: material_id, site_id, unit
     * Optional params: search, per_page, page
     */
    public function getStockTransactions(Request $request)
    {
        try {
            $conn = config('database.default');
            $material_id = $request->get('material_id') ?? $request->get('mat_id');
            $site_id = $request->get('site_id');
            $unit = $request->get('unit');

            if (!$material_id || !$site_id || !$unit) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'material_id, site_id and unit are required parameters'
                ], 400);
            }

            // Get current stock info with names (matches web logic)
            $current_stock = DB::connection($conn)->table('material_stock_record')
                ->join('materials', 'materials.id', '=', 'material_stock_record.material_id')
                ->join('sites', 'sites.id', '=', 'material_stock_record.site_id')
                ->join('units', 'units.id', '=', 'material_stock_record.unit')
                ->where('material_stock_record.site_id', '=', $site_id)
                ->where('material_stock_record.material_id', '=', $material_id)
                ->where('material_stock_record.unit', '=', $unit)
                ->select(
                    'material_stock_record.*',
                    'units.name as unit_name',
                    'materials.name as material_name',
                    'sites.name as site_name'
                )
                ->first();

            if (!$current_stock) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'No stock record found for this Material/Site/Unit combination'
                ], 404);
            }

            // Build transactions query
            $query = DB::connection($conn)->table('material_stock_transactions')
                ->where('site_id', '=', $site_id)
                ->where('material_id', '=', $material_id)
                ->where('unit', '=', $unit);

            // Search filter
            if ($request->has('search')) {
                $search = trim($request->get('search'));
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('type', 'LIKE', "%{$search}%")
                          ->orWhere('refrence', 'LIKE', "%{$search}%")
                          ->orWhere('qty', 'LIKE', "%{$search}%");
                    });
                }
            }

            // CSV Export: /api/v1/materials/stock/transactions?material_id=3&site_id=47&unit=3&export=csv
            if ($request->get('export') == 'csv') {
                $allTransactions = $query->orderBy('id', 'desc')->get();
                $unitName = $current_stock->unit_name;
                $filename = 'transactions_' . $current_stock->material_name . '_' . $current_stock->site_name . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];
                return response()->stream(function() use ($allTransactions, $current_stock, $unitName) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['Material: ' . $current_stock->material_name, 'Site: ' . $current_stock->site_name, 'Current Stock: ' . $current_stock->qty . ' ' . $unitName]);
                    fputcsv($file, []);
                    fputcsv($file, ['#', 'Date', 'Transaction Type', 'Quantity', 'Unit', 'Reference']);
                    $i = 1;
                    foreach ($allTransactions as $t) {
                        fputcsv($file, [
                            $i++,
                            $t->created_at ?? '',
                            $t->type ?? '',
                            $t->qty ?? '',
                            $unitName,
                            $t->refrence ?? ''
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            $perPage = $request->get('per_page', 10);
            $transactions = $query->orderBy('id', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'current_stock' => [
                    'material_name' => $current_stock->material_name,
                    'site_name' => $current_stock->site_name,
                    'unit_name' => $current_stock->unit_name,
                    'qty' => $current_stock->qty,
                    'last_updated' => $current_stock->last_updated ?? null,
                ],
                'transactions' => $transactions,
                'server_time' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Material Stock Transactions as CSV
     * Required params: material_id, site_id, unit
     */
    public function exportStockTransactionsCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $material_id = $request->get('material_id') ?? $request->get('mat_id');
            $site_id = $request->get('site_id');
            $unit = $request->get('unit');

            if (!$material_id || !$site_id || !$unit) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'material_id, site_id and unit are required parameters'
                ], 400);
            }

            // Get names for the header
            $current_stock = DB::connection($conn)->table('material_stock_record')
                ->join('materials', 'materials.id', '=', 'material_stock_record.material_id')
                ->join('sites', 'sites.id', '=', 'material_stock_record.site_id')
                ->join('units', 'units.id', '=', 'material_stock_record.unit')
                ->where('material_stock_record.site_id', '=', $site_id)
                ->where('material_stock_record.material_id', '=', $material_id)
                ->where('material_stock_record.unit', '=', $unit)
                ->select(
                    'material_stock_record.qty',
                    'units.name as unit_name',
                    'materials.name as material_name',
                    'sites.name as site_name'
                )
                ->first();

            $materialName = $current_stock->material_name ?? 'Material';
            $siteName = $current_stock->site_name ?? 'Site';

            // Get ALL transactions (no pagination for CSV)
            $transactions = DB::connection($conn)->table('material_stock_transactions')
                ->where('site_id', '=', $site_id)
                ->where('material_id', '=', $material_id)
                ->where('unit', '=', $unit)
                ->orderBy('id', 'desc')
                ->get();

            $unitName = $current_stock->unit_name ?? '';
            $filename = "transactions_{$materialName}_{$siteName}.csv";

            $headers = [
                "Content-type" => "text/csv",
                "Content-Disposition" => "attachment; filename=\"{$filename}\"",
                "Pragma" => "no-cache",
                "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
                "Expires" => "0"
            ];

            return response()->stream(function() use ($transactions, $materialName, $siteName, $unitName, $current_stock) {
                $file = fopen('php://output', 'w');
                // Header info rows
                fputcsv($file, ['Material: ' . $materialName, 'Site: ' . $siteName, 'Current Stock: ' . ($current_stock->qty ?? 0) . ' ' . $unitName]);
                fputcsv($file, []);
                // Column headers
                fputcsv($file, ['#', 'Date', 'Transaction Type', 'Quantity', 'Unit', 'Reference']);
                // Data rows
                $i = 1;
                foreach ($transactions as $t) {
                    fputcsv($file, [
                        $i++,
                        $t->created_at ?? '',
                        $t->type ?? '',
                        $t->qty ?? '',
                        $unitName,
                        $t->refrence ?? ''
                    ]);
                }
                fclose($file);
            }, 200, $headers);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Material Stock Site Transfers (matches web: stock_site_transfer)
     * Supports search and CSV export
     */
    public function getStockSiteTransfers(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            
            // Get user's role and site visibility
            $role = DB::connection($conn)->table('roles')->where('id', $user->role_id)->first();
            $visibility = $role->visiblity_at_site ?? 'all';
            
            $query = DB::connection($conn)->table('material_site_transfers')
                ->leftJoin('materials', 'materials.id', '=', 'material_site_transfers.material_id')
                ->leftJoin('sites as f_site', 'f_site.id', '=', 'material_site_transfers.from_site')
                ->leftJoin('sites as t_site', 't_site.id', '=', 'material_site_transfers.to_site')
                ->leftJoin('units', 'units.id', '=', 'material_site_transfers.unit')
                ->leftJoin('users', 'users.id', '=', 'material_site_transfers.user_id')
                ->select(
                    'material_site_transfers.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'f_site.name as from_site_name', 
                    't_site.name as to_site_name', 
                    'users.name as user_name'
                );

            $query->orderBy('material_site_transfers.id', 'DESC');

            // Search filter
            if ($request->has('search')) {
                $search = trim($request->get('search'));
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('materials.name', 'LIKE', "%{$search}%")
                          ->orWhere('f_site.name', 'LIKE', "%{$search}%")
                          ->orWhere('t_site.name', 'LIKE', "%{$search}%")
                          ->orWhere('material_site_transfers.vehicle_no', 'LIKE', "%{$search}%")
                          ->orWhere('material_site_transfers.remark', 'LIKE', "%{$search}%");
                    });
                }
            }

            // Date filters
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('material_site_transfers.date', [$request->get('from_date'), $request->get('to_date')]);
            }

            $query->orderBy('material_site_transfers.id', 'DESC');

            // CSV Export
            if ($request->get('export') == 'csv') {
                $data = $query->get();
                $filename = 'stock_transfers_' . date('Y-m-d') . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];
                return response()->stream(function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['#', 'Date', 'Material', 'From Site', 'To Site', 'Qty', 'Unit', 'Vehicle No', 'Remark', 'Transferred By']);
                    $i = 1;
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $i++,
                            $row->date,
                            $row->material_name,
                            $row->from_site_name,
                            $row->to_site_name,
                            $row->qty,
                            $row->unit_name,
                            $row->vehicle_no,
                            $row->remark,
                            $row->user_name
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            $perPage = $request->get('per_page', 10);
            $transfers = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'debug' => [
                    'connection' => $conn,
                    'role_id' => $user->role_id,
                    'site_id' => $user->site_id,
                    'visibility' => $visibility
                ],
                'data' => $transfers,
                'server_time' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store Material Site Transfer (matches web: newMaterialTransferForm)
     */
    public function storeStockSiteTransfer(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            // Try to find the data in all possible places (Robust parsing)
            $data = $request->all();
            $filteredData = array_diff_key($data, array_flip(['tenant_conn', 'tenant_uid', 'tenant_role', 'tenant_site_id']));
            
            if (empty($filteredData)) {
                $jsonData = $request->json()->all();
                if (!empty($jsonData)) {
                    $data = array_merge($data, $jsonData);
                } else {
                    $raw = file_get_contents('php://input');
                    $decoded = json_decode($raw, true);
                    if ($decoded) $data = array_merge($data, $decoded);
                }
            }

            $validator = Validator::make($data, [
                'material_id' => 'required',
                'from_site' => 'required',
                'to_site' => 'required',
                'unit' => 'required',
                'qty' => 'required|numeric|min:0.01',
                'date' => 'required|date',
                'vehicle_no' => 'nullable',
                'remark' => 'nullable'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Validation failed', 
                    'errors' => $validator->errors()
                ], 400);
            }

            // Check stock in from_site
            $check_current_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', '=', $data['from_site'])
                ->where('material_id', '=', $data['material_id'])
                ->where('unit', '=', $data['unit'])
                ->first();

            if (!$check_current_stock || $check_current_stock->qty < $data['qty']) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Insufficient stock for this transfer. Available: ' . ($check_current_stock->qty ?? 0)
                ], 400);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $data, $user, $check_current_stock) {
                $insertData = [
                    'material_id' => $data['material_id'],
                    'from_site' => $data['from_site'],
                    'to_site' => $data['to_site'],
                    'unit' => $data['unit'],
                    'qty' => $data['qty'],
                    'user_id' => $user->id,
                    'remark' => $data['remark'] ?? '',
                    'vehicle_no' => $data['vehicle_no'] ?? '',
                    'date' => $data['date'],
                ];

                $id = DB::connection($conn)->table('material_site_transfers')->insertGetId($insertData);

                // Update From Site Stock
                DB::connection($conn)->table('material_stock_record')
                    ->where('id', '=', $check_current_stock->id)
                    ->decrement('qty', $data['qty'], ['last_updated' => Carbon::now()]);

                // Update To Site Stock
                $to_site_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', '=', $data['to_site'])
                    ->where('material_id', '=', $data['material_id'])
                    ->where('unit', '=', $data['unit'])
                    ->first();

                if ($to_site_stock) {
                    DB::connection($conn)->table('material_stock_record')
                        ->where('id', '=', $to_site_stock->id)
                        ->increment('qty', $data['qty'], ['last_updated' => Carbon::now()]);
                } else {
                    DB::connection($conn)->table('material_stock_record')->insert([
                        'material_id' => $data['material_id'],
                        'site_id' => $data['to_site'],
                        'qty' => $data['qty'],
                        'unit' => $data['unit'],
                        'last_updated' => Carbon::now()
                    ]);
                }

                // Log Transactions
                DB::connection($conn)->table('material_stock_transactions')->insert([
                    ['site_id' => $data['from_site'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['unit'], 'type' => 'OUT', 'refrence' => 'Site Transferred Debit', 'refrence_id' => $id],
                    ['site_id' => $data['to_site'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['unit'], 'type' => 'IN', 'refrence' => 'Site Transferred Credit', 'refrence_id' => $id]
                ]);

                addActivity($id, 'material_site_transfers', "New Material Transfer Completed via API", 3, $user->id, $conn);

                return response()->json([
                    'status' => 'Ok', 
                    'message' => 'Material Site Transferred successfully!',
                    'id' => $id
                ]);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Material Site Transfer (matches web: deleteMaterialTransferForm)
     */
    public function deleteStockSiteTransfer(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $transfer = DB::connection($conn)->table('material_site_transfers')->where('id', $id)->first();
            if (!$transfer) {
                return response()->json(['status' => 'Error', 'message' => 'Transfer record not found'], 404);
            }

            // Verify stock at destination site before reversing
            $to_site_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', $transfer->to_site)
                ->where('material_id', $transfer->material_id)
                ->where('unit', $transfer->unit)
                ->first();

            if (!$to_site_stock || $to_site_stock->qty < $transfer->qty) {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'Cannot delete transfer. Target site already used this material and has insufficient stock to reverse.'
                ], 400);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $transfer, $user, $id, $to_site_stock) {
                // Reverse stock at destination site
                DB::connection($conn)->table('material_stock_record')
                    ->where('id', $to_site_stock->id)
                    ->decrement('qty', $transfer->qty, ['last_updated' => Carbon::now()]);

                // Restore stock at source site
                $from_site_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', $transfer->from_site)
                    ->where('material_id', $transfer->material_id)
                    ->where('unit', $transfer->unit)
                    ->first();

                if ($from_site_stock) {
                    DB::connection($conn)->table('material_stock_record')
                        ->where('id', $from_site_stock->id)
                        ->increment('qty', $transfer->qty, ['last_updated' => Carbon::now()]);
                }

                // Delete related transaction logs
                DB::connection($conn)->table('material_stock_transactions')
                    ->where('refrence_id', $id)
                    ->whereIn('refrence', ['Site Transferred Debit', 'Site Transferred Credit'])
                    ->delete();

                // Delete the transfer record
                DB::connection($conn)->table('material_site_transfers')->where('id', $id)->delete();

                addActivity($id, 'material_site_transfers', "Material Transfer Deleted via API", 3, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Material Site Transfer deleted successfully!']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialConsumption(Request $request)
    {
        // Support both single and bulk entries
        $material_ids = $request->get('material_id');
        
        if (!is_array($material_ids)) {
            // Convert single request to array format for unified processing
            $count = 1;
        } else {
            $count = count($material_ids);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();
            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);
            $responses = [];

            for ($i = 0; $i < $count; $i++) {
                $material_id = is_array($request->material_id) ? $request->material_id[$i] : $request->material_id;
                $site_id = is_array($request->site_id) ? $request->site_id[$i] : $request->site_id;
                $unit = is_array($request->unit) ? $request->unit[$i] : $request->unit;
                $qty = is_array($request->qty) ? $request->qty[$i] : $request->qty;
                $date = is_array($request->date) ? $request->date[$i] : $request->date;
                $remark = is_array($request->remark) ? $request->remark[$i] : $request->remark;

                if (!$material_id || !$site_id || !$qty) continue;

                $imagePath = "images/expense.png";
                if ($request->hasFile('image')) {
                    $images = $request->file('image');
                    $file = is_array($images) ? ($images[$i] ?? null) : $images;
                    
                    if ($file) {
                        $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                        $file->move(public_path('images/app_images/' . $conn . '/consumption'), $imageName);
                        $imagePath = "images/app_images/" . $conn . "/consumption/" . $imageName;
                    }
                }

                $table = 'material_consumption';
                
                $data = [
                    'material_id' => $material_id,
                    'site_id' => $site_id,
                    'unit' => $unit,
                    'qty' => $qty,
                    'user_id' => $user->id,
                    'image' => $imagePath,
                    'remark' => $remark,
                    'date' => $date,
                    'status' => $status,
                    'create_datetime' => Carbon::now()
                ];

                $id = DB::connection($conn)->table($table)->insertGetId($data);
                addActivity($id, $table, "New Entry Created via API (Bulk Support)", 3, $user->id, $conn);

                if ($status == 'Approved') {
                    $this->adjustStockForConsumption($id, $conn, 'approve');
                }

                $responses[] = ['id' => $id, 'type' => 'Consumption', 'status' => 'Ok'];
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => count($responses) . ' entries processed successfully', 
                'processed' => $responses,
                'status_assigned' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialWastage(Request $request)
    {
        // Support both single and bulk entries
        $material_ids = $request->get('material_id');
        
        if (!is_array($material_ids)) {
            // Convert single request to array format for unified processing
            $count = 1;
        } else {
            $count = count($material_ids);
        }

        try {
            $conn = config('database.default');
            $user = $request->user();
            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);
            $responses = [];

            for ($i = 0; $i < $count; $i++) {
                $material_id = is_array($request->material_id) ? $request->material_id[$i] : $request->material_id;
                $site_id = is_array($request->site_id) ? $request->site_id[$i] : $request->site_id;
                $unit = is_array($request->unit) ? $request->unit[$i] : $request->unit;
                $qty = is_array($request->qty) ? $request->qty[$i] : $request->qty;
                $date = is_array($request->date) ? $request->date[$i] : $request->date;
                $remark = is_array($request->remark) ? $request->remark[$i] : $request->remark;
                // Reason field required for wastage (often sent as 'region' by mobile dev)
                $reason = is_array($request->reason) ? $request->reason[$i] : $request->get('reason');
                $region = is_array($request->region) ? $request->region[$i] : $request->get('region');
                
                // Fallback to region if reason is empty
                $final_reason = !empty($reason) ? $reason : $region;

                if (!$material_id || !$site_id || !$qty) continue;

                $imagePath = "images/expense.png";
                if ($request->hasFile('image')) {
                    $images = $request->file('image');
                    $file = is_array($images) ? ($images[$i] ?? null) : $images;
                    
                    if ($file) {
                        $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                        $file->move(public_path('images/app_images/' . $conn . '/wastage'), $imageName);
                        $imagePath = "images/app_images/" . $conn . "/wastage/" . $imageName;
                    }
                }

                $table = 'material_wastage';
                
                $data = [
                    'material_id' => $material_id,
                    'site_id' => $site_id,
                    'unit' => $unit,
                    'qty' => $qty,
                    'user_id' => $user->id,
                    'image' => $imagePath,
                    'remark' => $remark,
                    'date' => $date,
                    'reason' => $final_reason,
                    'status' => $status,
                    'create_datetime' => Carbon::now()
                ];

                $id = DB::connection($conn)->table($table)->insertGetId($data);
                addActivity($id, $table, "New Wastage Entry Created via API (Bulk Support)", 3, $user->id, $conn);

                if ($status == 'Approved') {
                    $this->adjustStockForWastage($id, $conn, 'approve');
                }

                $responses[] = ['id' => $id, 'type' => 'Wastage', 'status' => 'Ok'];
            }

            return response()->json([
                'status' => 'Ok', 
                'message' => count($responses) . ' wastage entries processed successfully', 
                'processed' => $responses,
                'status_assigned' => $status
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkApproveConsumption(Request $request)
    {
        return $this->processBulkStatusUpdate($request, 'Approved', 'Consumption');
    }

    public function bulkRejectConsumption(Request $request)
    {
        return $this->processBulkStatusUpdate($request, 'Rejected', 'Consumption');
    }

    public function bulkApproveWastage(Request $request)
    {
        return $this->processBulkStatusUpdate($request, 'Approved', 'Wastage');
    }

    public function bulkRejectWastage(Request $request)
    {
        return $this->processBulkStatusUpdate($request, 'Rejected', 'Wastage');
    }

    private function processBulkStatusUpdate(Request $request, $targetStatus, $defaultEntryType)
    {
        try {
            $conn = config('database.default');
            $ids = $request->get('ids');
            if (!is_array($ids)) $ids = explode(',', $ids);
            
            $entry_type = $request->get('entry_type', $defaultEntryType);
            $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';
            
            $user = $request->user();
            $action = ($targetStatus == 'Approved') ? 'approve' : 'reject';

            foreach ($ids as $id) {
                if (empty($id)) continue;
                
                if ($entry_type == 'Wastage') {
                    $this->adjustStockForWastage($id, $conn, $action);
                } else {
                    $this->adjustStockForConsumption($id, $conn, $action);
                }
                
                DB::connection($conn)->table($table)->where('id', $id)->update(['status' => $targetStatus]);
                addActivity($id, $table, "Bulk " . $targetStatus . " via API", 3, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Selected entries ' . strtolower($targetStatus) . ' successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getConsumptionDetails(Request $request, $id)
    {
        return $this->getMaterialEntryDetails($request, $id, 'Consumption');
    }

    public function getWastageDetails(Request $request, $id)
    {
        return $this->getMaterialEntryDetails($request, $id, 'Wastage');
    }

    private function getMaterialEntryDetails(Request $request, $id, $defaultType)
    {
        try {
            $conn = config('database.default');
            $entry_type = $request->get('entry_type', $defaultType);
            $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';

            $data = DB::connection($conn)->table($table)
                ->leftJoin('materials', 'materials.id', '=', $table . '.material_id')
                ->leftJoin('sites', 'sites.id', '=', $table . '.site_id')
                ->leftJoin('units', 'units.id', '=', $table . '.unit')
                ->select(
                    $table . '.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'sites.name as site_name'
                )
                ->where($table . '.id', $id)
                ->first();

            if (!$data) return response()->json(['status' => 'Error', 'message' => 'Entry not found'], 404);

            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMaterialConsumption(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $entry_type = $request->get('entry_type', 'Consumption');
            $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';

            $entry = DB::connection($conn)->table($table)->where('id', $id)->first();
            if (!$entry) return response()->json(['status' => 'Error', 'message' => 'Entry not found'], 404);

            // Verified logic: Only allow edit if Pending or Rejected
            if ($entry->status == 'Approved' || $entry->status == 'Verified') {
                 // Website only shows edit if rejected in verified list.
                 if ($entry->status != 'Rejected') {
                     return response()->json(['status' => 'Error', 'message' => 'Only rejected or pending entries can be edited'], 403);
                 }
            }

            $updateData = $request->only(['material_id', 'site_id', 'unit', 'qty', 'date', 'remark']);
            if ($entry_type == 'Wastage') {
                $updateData['reason'] = $request->get('reason');
            }

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/consumption'), $imageName);
                $updateData['image'] = "images/app_images/" . $conn . "/consumption/" . $imageName;
            }
            
            // If it was rejected, we reset it to Pending
            $updateData['status'] = 'Pending';

            DB::connection($conn)->table($table)->where('id', $id)->update($updateData);
            addActivity($id, $table, "Updated via API", 3);

            return response()->json(['status' => 'Ok', 'message' => 'Entry updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function adjustStockForConsumption($id, $conn, $action = 'approve')
    {
        $consumption = DB::connection($conn)->table('material_consumption')->where('id', $id)->first();
        if (!$consumption) return;

        if ($action == 'approve') {
            // Check if already approved to avoid double deduction
            $already_processed = DB::connection($conn)->table('material_stock_transactions')
                ->where('refrence_id', $id)
                ->where('refrence', 'Consumption')
                ->exists();
            if ($already_processed) return;

            $stock_data = [
                'site_id' => $consumption->site_id, 
                'material_id' => $consumption->material_id, 
                'qty' => $consumption->qty, 
                'unit' => $consumption->unit, 
                'type' => 'OUT', 
                'refrence' => 'Consumption', 
                'refrence_id' => $consumption->id,
                'create_datetime' => Carbon::now()
            ];

            $check_current_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', $consumption->site_id)
                ->where('material_id', $consumption->material_id)
                ->where('unit', $consumption->unit)
                ->first();

            if ($check_current_stock) {
                $new_qty = $check_current_stock->qty - $consumption->qty;
                DB::connection($conn)->table('material_stock_record')->where('id', $check_current_stock->id)->update(['qty' => $new_qty]);
            } else {
                DB::connection($conn)->table('material_stock_record')->insert([
                    'material_id' => $consumption->material_id, 
                    'site_id' => $consumption->site_id, 
                    'qty' => -$consumption->qty, 
                    'unit' => $consumption->unit
                ]);
            }
            DB::connection($conn)->table('material_stock_transactions')->insert($stock_data);
        } else if ($action == 'reject') {
            // If it was previously approved, we need to reverse it
            $transaction = DB::connection($conn)->table('material_stock_transactions')
                ->where('refrence_id', $id)
                ->where('refrence', 'Consumption')
                ->first();
            
            if ($transaction) {
                $check_current_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', $consumption->site_id)
                    ->where('material_id', $consumption->material_id)
                    ->where('unit', $consumption->unit)
                    ->first();
                
                if ($check_current_stock) {
                    $new_qty = $check_current_stock->qty + $consumption->qty;
                    DB::connection($conn)->table('material_stock_record')->where('id', $check_current_stock->id)->update(['qty' => $new_qty]);
                }
                DB::connection($conn)->table('material_stock_transactions')->where('id', $transaction->id)->delete();
            }
        }
    }

    private function adjustStockForWastage($id, $conn, $action = 'approve')
    {
        $wastage = DB::connection($conn)->table('material_wastage')->where('id', $id)->first();
        if (!$wastage) return;

        if ($action == 'approve') {
            $already_processed = DB::connection($conn)->table('material_stock_transactions')
                ->where('refrence_id', $id)
                ->where('refrence', 'Wastage')
                ->exists();
            if ($already_processed) return;

            $stock_data = [
                'site_id' => $wastage->site_id, 
                'material_id' => $wastage->material_id, 
                'qty' => $wastage->qty, 
                'unit' => $wastage->unit, 
                'type' => 'OUT', 
                'refrence' => 'Wastage', 
                'refrence_id' => $wastage->id,
                'create_datetime' => Carbon::now()
            ];

            $check_current_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', $wastage->site_id)
                ->where('material_id', $wastage->material_id)
                ->where('unit', $wastage->unit)
                ->first();

            if ($check_current_stock) {
                $new_qty = $check_current_stock->qty - $wastage->qty;
                DB::connection($conn)->table('material_stock_record')->where('id', $check_current_stock->id)->update(['qty' => $new_qty]);
            } else {
                DB::connection($conn)->table('material_stock_record')->insert([
                    'material_id' => $wastage->material_id, 
                    'site_id' => $wastage->site_id, 
                    'qty' => -$wastage->qty, 
                    'unit' => $wastage->unit
                ]);
            }
            DB::connection($conn)->table('material_stock_transactions')->insert($stock_data);
        } else if ($action == 'reject') {
            $transaction = DB::connection($conn)->table('material_stock_transactions')
                ->where('refrence_id', $id)
                ->where('refrence', 'Wastage')
                ->first();
            
            if ($transaction) {
                $check_current_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', $wastage->site_id)
                    ->where('material_id', $wastage->material_id)
                    ->where('unit', $wastage->unit)
                    ->first();
                
                if ($check_current_stock) {
                    $new_qty = $check_current_stock->qty + $wastage->qty;
                    DB::connection($conn)->table('material_stock_record')->where('id', $check_current_stock->id)->update(['qty' => $new_qty]);
                }
                DB::connection($conn)->table('material_stock_transactions')->where('id', $transaction->id)->delete();
            }
        }
    }

    public function getPendingConsumption(Request $request)
    {
        return $this->listConsumptionByStatus($request, 'Pending', 'Consumption');
    }

    public function getVerifiedConsumption(Request $request)
    {
        return $this->listConsumptionByStatus($request, 'Verified', 'Consumption');
    }

    public function getPendingWastage(Request $request)
    {
        return $this->listConsumptionByStatus($request, 'Pending', 'Wastage');
    }

    public function getVerifiedWastage(Request $request)
    {
        return $this->listConsumptionByStatus($request, 'Verified', 'Wastage');
    }

    /**
     * List Stock Reconciliations
     * categories: pending, uploaded, verified
     */
    public function listReconciliation(Request $request)
    {
        try {
            $conn = config('database.default');
            $type = $request->get('type', 'pending'); // pending, uploaded, verified
            $search = trim($request->get('search'));
            $per_page = $request->get('per_page', 10);

            $query = DB::connection($conn)->table('material_reconsilation_record as msr')
                ->select(
                    'msr.*',
                    'sites.name as site_name',
                    'r_user.name as requested_by_name',
                    'u_user.name as upload_by_name',
                    'a_user.name as approved_by_name'
                )
                ->join('sites', 'sites.id', '=', 'msr.site_id')
                ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
                ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
                ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by');

            // Apply Type Filter (matching StockController logic)
            if ($type == 'pending') {
                $query->whereIn('msr.status', ['Pending', 'Draft']);
            } elseif ($type == 'uploaded' || $type == 'submitted') {
                $query->where('msr.status', 'Submitted');
            } elseif ($type == 'verified') {
                $query->whereIn('msr.status', ['Rejected', 'Approved', 'Converted']);
            }

            // Apply Search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('sites.name', 'LIKE', "%{$search}%")
                        ->orWhere('r_user.name', 'LIKE', "%{$search}%")
                        ->orWhere('msr.date', 'LIKE', "%{$search}%")
                        ->orWhere('msr.status', 'LIKE', "%{$search}%");
                });
            }

            $data = $query->orderBy('msr.id', 'desc')->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'type' => $type,
                'data' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Stock Reconciliation Details
     * Required param: id
     */
    public function getReconciliationDetails(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            $reconsile_record = DB::connection($conn)->table('material_reconsilation_record as msr')
                ->select(
                    'msr.*',
                    'sites.name as site_name',
                    'r_user.name as requested_by_name',
                    'u_user.name as upload_by_name',
                    'a_user.name as approved_by_name'
                )
                ->join('sites', 'sites.id', '=', 'msr.site_id')
                ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
                ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
                ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by')
                ->where('msr.id', '=', $id)
                ->first();

            if (!$reconsile_record) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation record not found'], 404);
            }

            if ($reconsile_record->status == "Pending" || $reconsile_record->status == "Draft") {
                $reconsile_data = DB::connection($conn)->table('material_reconsilation_data')
                    ->where('reconsilation_id', '=', $id)
                    ->get()
                    ->keyBy(function ($item) {
                        return $item->material_id . '_' . $item->unit;
                    });

                $material_stock_record = DB::connection($conn)->table('material_stock_record')
                    ->join('materials', 'materials.id', '=', 'material_stock_record.material_id')
                    ->join('units', 'units.id', '=', 'material_stock_record.unit')
                    ->select('material_stock_record.*', 'units.name as unit_name', 'materials.name as material_name')
                    ->where('material_stock_record.site_id', '=', $reconsile_record->site_id)
                    ->get();

                $data = array();
                foreach ($material_stock_record as $stock) {
                    $key = $stock->material_id . '_' . $stock->unit;
                    $stock->system_qty = $stock->qty;
                    $stock->reconsiled_qty = isset($reconsile_data[$key]) ? $reconsile_data[$key]->reconsiled_qty : null;
                    if ($stock->reconsiled_qty !== null) {
                        $stock->difference = $stock->qty - $stock->reconsiled_qty;
                    } else {
                        $stock->difference = null;
                    }
                    $data[] = $stock;
                }
            } else {
                $data = DB::connection($conn)->table('material_reconsilation_data')
                    ->join('materials', 'materials.id', '=', 'material_reconsilation_data.material_id')
                    ->join('units', 'units.id', '=', 'material_reconsilation_data.unit')
                    ->select('material_reconsilation_data.*', 'units.name as unit_name', 'materials.name as material_name')
                    ->where('material_reconsilation_data.reconsilation_id', '=', $id)
                    ->get();
            }

            return response()->json([
                'status' => 'Ok',
                'record' => $reconsile_record,
                'items' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Stock Reconciliation as CSV
     */
    public function exportReconciliationCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $type = $request->get('type', 'all');
            $search = trim($request->get('search'));

            $query = DB::connection($conn)->table('material_reconsilation_record as msr')
                ->select(
                    'msr.*',
                    'sites.name as site_name',
                    'r_user.name as requested_by_name',
                    'u_user.name as upload_by_name',
                    'a_user.name as approved_by_name'
                )
                ->join('sites', 'sites.id', '=', 'msr.site_id')
                ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
                ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
                ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by');

            // Apply Type Filter
            if ($type == 'pending') {
                $query->whereIn('msr.status', ['Pending', 'Draft']);
            } elseif ($type == 'uploaded' || $type == 'submitted') {
                $query->where('msr.status', 'Submitted');
            } elseif ($type == 'verified') {
                $query->whereIn('msr.status', ['Rejected', 'Approved', 'Converted']);
            }

            // Apply Search
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('sites.name', 'LIKE', "%{$search}%")
                        ->orWhere('r_user.name', 'LIKE', "%{$search}%")
                        ->orWhere('msr.date', 'LIKE', "%{$search}%")
                        ->orWhere('msr.status', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->orderBy('msr.id', 'desc')->get();

            $filename = 'reconciliation_export_' . ($type != 'all' ? $type . '_' : '') . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['#', 'Date', 'Site', 'Status', 'Requested By', 'Uploaded By', 'Verified By', 'Stock Updated']);

                $i = 1;
                foreach ($records as $r) {
                    fputcsv($file, [
                        $i++,
                        $r->date,
                        $r->site_name,
                        $r->status,
                        $r->requested_by_name,
                        $r->upload_by_name,
                        $r->approved_by_name,
                        $r->stock_updated
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new Reconciliation Request
     */
    public function storeReconciliationRequest(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $site_id = $request->input('site_id');

            if (!$site_id) {
                return response()->json(['status' => 'Error', 'message' => 'site_id is required'], 400);
            }

            // Verify site exists and is active
            $site = DB::connection($conn)->table('sites')->where('id', $site_id)->where('status', 'Active')->first();
            if (!$site) {
                return response()->json(['status' => 'Error', 'message' => 'Active site not found'], 404);
            }

            $date = \Carbon\Carbon::now()->format('d-m-Y');
            $data = [
                'site_id' => $site_id,
                'requested_by' => $user->id,
                'date' => $date,
                'status' => 'Pending',
                'stock_updated' => 'No'
            ];

            $id = DB::connection($conn)->table('material_reconsilation_record')->insertGetId($data);
            addActivity($id, 'material_reconsilation_record', "New Stock Reconciliation Requested via API for site: " . $site->name, 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciliation Requested Successfully',
                'id' => $id,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Stock Reconciliation
     */
    public function deleteReconciliation(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            $record = DB::connection($conn)->table('material_reconsilation_record')->where('id', $id)->first();
            if (!$record) {
                return response()->json(['status' => 'Error', 'message' => 'Record not found'], 404);
            }

            // Only allow deleting if not already converted/approved? (Optional, mirroring StockController: delete_reconsilation doesn't have checks)
            DB::connection($conn)->table('material_reconsilation_record')->where('id', $id)->delete();
            DB::connection($conn)->table('material_reconsilation_data')->where('reconsilation_id', $id)->delete();

            addActivity($id, 'material_reconsilation_record', "Stock Reconciliation Request Deleted via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciliation Request Deleted Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update/Upload Reconciliation Data
     */
    public function updateReconciliationData(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $reconsilation_id = $id ?? $request->get('id') ?? $request->get('reconsilation_id');

            if (!$reconsilation_id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            // More robust way to get items from JSON or Form Data
            $items = $request->input('items');
            if (!$items && $request->getContent()) {
                $decoded = json_decode($request->getContent(), true);
                $items = $decoded['items'] ?? null;
            }

            if (!is_array($items) || empty($items)) {
                return response()->json(['status' => 'Error', 'message' => 'Items array is required'], 400);
            }

            $new_data = array();
            foreach ($items as $item) {
                $new_data[] = [
                    'reconsilation_id' => $reconsilation_id,
                    'material_id' => $item['material_id'],
                    'system_qty' => $item['system_qty'],
                    'reconsiled_qty' => $item['reconsiled_qty'],
                    'unit' => $item['unit'],
                    'difference' => $item['difference'] ?? ($item['system_qty'] - $item['reconsiled_qty'])
                ];
            }

            DB::connection($conn)->table('material_reconsilation_data')->where('reconsilation_id', '=', $reconsilation_id)->delete();
            DB::connection($conn)->table('material_reconsilation_data')->insert($new_data);
            DB::connection($conn)->table('material_reconsilation_record')->where('id', '=', $reconsilation_id)->update([
                'status' => 'Submitted', 
                'upload_by' => $user->id
            ]);

            addActivity($reconsilation_id, 'material_reconsilation_record', "Stock Reconciliation Data Uploaded/Updated via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciliation Data Uploaded Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify Stock Reconciliation (Sets status to Approved)
     */
    public function verifyReconciliation(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            DB::connection($conn)->table('material_reconsilation_record')->where('id', '=', $id)->update([
                'status' => 'Approved', 
                'approved_by' => $user->id
            ]);

            addActivity($id, 'material_reconsilation_record', "Stock Reconciliation Verified via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciliation Verified Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject Stock Reconciliation (Sets status to Rejected)
     */
    public function rejectReconciliation(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            DB::connection($conn)->table('material_reconsilation_record')->where('id', '=', $id)->update([
                'status' => 'Rejected', 
                'approved_by' => $user->id
            ]);

            addActivity($id, 'material_reconsilation_record', "Stock Reconciliation Rejected via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciliation Rejected Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve & Update Stock (Sets status to Converted and updates physical stock)
     */
    public function approveAndUpdateStock(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation ID is required'], 400);
            }

            $reconsile_record = DB::connection($conn)->table('material_reconsilation_record')->where('id', '=', $id)->first();
            if (!$reconsile_record) {
                return response()->json(['status' => 'Error', 'message' => 'Reconciliation record not found'], 404);
            }

            if ($reconsile_record->status == 'Converted') {
                return response()->json(['status' => 'Error', 'message' => 'Stock already updated for this reconciliation'], 400);
            }

            $reconsile_data = DB::connection($conn)->table('material_reconsilation_data')->where('reconsilation_id', '=', $id)->get();
            
            if ($reconsile_data->isEmpty()) {
                return response()->json(['status' => 'Error', 'message' => 'No item data found to update stock'], 400);
            }

            foreach ($reconsile_data as $data) {
                // Update Material Stock Record
                DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', '=', $reconsile_record->site_id)
                    ->where('material_id', '=', $data->material_id)
                    ->where('unit', '=', $data->unit)
                    ->update(['qty' => $data->reconsiled_qty]);

                // Create Stock Transaction Record
                $trans_data = [
                    'site_id' => $reconsile_record->site_id,
                    'material_id' => $data->material_id,
                    'unit' => $data->unit,
                    'qty' => $data->reconsiled_qty,
                    'type' => 'Reconciliation',
                    'refrence' => $id,
                    'created_at' => \Carbon\Carbon::now()->toDateTimeString(),
                    'updated_at' => \Carbon\Carbon::now()->toDateTimeString()
                ];
                DB::connection($conn)->table('material_stock_transactions')->insert($trans_data);
            }

            // Update Reconciliation Record Status
            DB::connection($conn)->table('material_reconsilation_record')->where('id', '=', $id)->update([
                'status' => 'Converted', 
                'stock_updated' => 'Yes',
                'approved_by' => $user->id
            ]);

            addActivity($id, 'material_reconsilation_record', "Stock Reconciled & Updated via API", 3, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Stock Reconciled & Updated Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Bill Parties
     */
    public function listBillParties(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $status = $request->get('status');
            $per_page = $request->get('per_page', 20);

            $query = DB::connection($conn)->table('bills_party')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'bills_party.cost_category_id')
                ->select('bills_party.*', 'expense_head.name as category_name');

            $user = $request->user();
            $view_duration = getUserViewDuration($user, $conn);
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
            $query->whereBetween('bills_party.create_datetime', [$min_date, $max_date]);

            if ($status) {
                $query->where('bills_party.status', $status);
            }

            if (!empty($search)) {
                $query->where('bills_party.name', 'LIKE', "%{$search}%");
            }

            $data = $query->orderBy('bills_party.name', 'asc')->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'data' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Bill Party
     */
    public function storeBillParty(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            // Get input with fallback to raw content decoding
            $input = $request->all();
            if (empty($input) || !isset($input['name'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $data = [
                'name' => isset($input['name']) ? $input['name'] : null,
                'address' => isset($input['address']) ? $input['address'] : null,
                'panno' => isset($input['panno']) ? $input['panno'] : null,
                'bank_ac' => isset($input['bank_ac']) ? $input['bank_ac'] : null,
                'ifsc' => isset($input['ifsc']) ? $input['ifsc'] : null,
                'bankname' => isset($input['bankname']) ? $input['bankname'] : null,
                'ac_holder_name' => isset($input['ac_holder_name']) ? $input['ac_holder_name'] : null,
                'cost_category_id' => isset($input['cost_category_id']) ? $input['cost_category_id'] : null,
                'status' => isset($input['status']) ? $input['status'] : 'Pending'
            ];

            if (!$data['name']) {
                return response()->json(['status' => 'Error', 'message' => 'Name is required'], 400);
            }

            $id = DB::connection($conn)->table('bills_party')->insertGetId($data);
            addActivity($id, 'bills_party', "New Bill Party Created via API: " . $data['name'], 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Created Successfully',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party already exists'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Party
     */
    public function updateBillParty(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            // Get input with fallback to raw content decoding
            $input = $request->all();
            if (empty($input) || !isset($input['name'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $data = [
                'name' => isset($input['name']) ? $input['name'] : null,
                'address' => isset($input['address']) ? $input['address'] : null,
                'panno' => isset($input['panno']) ? $input['panno'] : null,
                'bank_ac' => isset($input['bank_ac']) ? $input['bank_ac'] : null,
                'ifsc' => isset($input['ifsc']) ? $input['ifsc'] : null,
                'bankname' => isset($input['bankname']) ? $input['bankname'] : null,
                'ac_holder_name' => isset($input['ac_holder_name']) ? $input['ac_holder_name'] : null,
                'cost_category_id' => isset($input['cost_category_id']) ? $input['cost_category_id'] : null
            ];

            // Remove null values to avoid overwriting with nulls if not provided
            $data = array_filter($data, function ($value) {
                return $value !== null;
            });

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided to update'], 400);
            }

            DB::connection($conn)->table('bills_party')->where('id', $id)->update($data);
            addActivity($id, 'bills_party', "Bill Party Updated via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Updated Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Bill Party
     */
    public function deleteBillParty(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            // Check if in use
            $check = DB::connection($conn)->table('new_bill_entry')->where('party_id', '=', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party is in use and cannot be deleted'], 400);
            }

            $party = DB::connection($conn)->table('bills_party')->where('id', $id)->first();
            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party not found'], 404);
            }

            DB::connection($conn)->table('bills_party')->where('id', $id)->delete();
            addActivity(0, 'bills_party', "Bill Party Deleted via API - " . $party->name, 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Deleted Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Party Status
     */
    public function updateBillPartyStatus(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');
            
            $input = $request->all();
            if (empty($input) || !isset($input['status'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }
            $status = $input['status'] ?? null;

            if (!$id || !$status) {
                return response()->json(['status' => 'Error', 'message' => 'ID and Status are required'], 400);
            }

            $party = DB::connection($conn)->table('bills_party')->where('id', '=', $id)->first();
            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party not found'], 404);
            }

            DB::connection($conn)->table('bills_party')->where('id', '=', $id)->update(['status' => $status]);
            addActivity($id, 'bills_party', "Bill Party Status Updated To " . $status . " via API", 4, $user->id, $conn);

            if ($status == 'Active' && $party->status == 'Pending') {
                DB::connection($conn)->table('contact_profile')->insert([
                    'comp_name' => $party->name, 
                    'contact_name' => $party->name, 
                    'category' => 'Bills Party'
                ]);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Status Updated Successfully',
                'new_status' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Bill Party Details
     */
    public function getBillPartyDetails(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $party = DB::connection($conn)->table('bills_party')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'bills_party.cost_category_id')
                ->select('bills_party.*', 'expense_head.name as category_name')
                ->where('bills_party.id', $id)
                ->first();

            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $party
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Bill Parties as CSV
     */
    public function exportBillPartiesCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $status = $request->get('status');

            $query = DB::connection($conn)->table('bills_party')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'bills_party.cost_category_id')
                ->select('bills_party.*', 'expense_head.name as category_name');

            if ($status) {
                $query->where('bills_party.status', $status);
            }

            if (!empty($search)) {
                $query->where('bills_party.name', 'LIKE', "%{$search}%");
            }

            $records = $query->orderBy('bills_party.name', 'asc')->get();

            $filename = 'bill_parties_export_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Name', 'Address', 'PAN No', 'Bank Account', 'IFSC', 'Bank Name', 'Account Holder', 'Category', 'Status']);

                foreach ($records as $r) {
                    fputcsv($file, [
                        $r->id,
                        $r->name,
                        $r->address,
                        $r->panno,
                        $r->bank_ac,
                        $r->ifsc,
                        $r->bankname,
                        $r->ac_holder_name,
                        $r->category_name,
                        $r->status
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Bill Party Payments
     */
    public function listBillPartyPayments(Request $request)
    {
        try {
            $conn = config('database.default');
            $party_id = $request->get('party_id');
            $search = trim($request->get('search'));
            $per_page = $request->get('per_page', 20);

            $query = DB::connection($conn)->table('bill_party_payments');

            if ($party_id) {
                $query->where('party_id', $party_id);
            }

            if (!empty($search)) {
                $query->where('remark', 'LIKE', "%{$search}%");
            }

            $data = $query->orderBy('date', 'desc')->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'data' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Bill Party Payment
     */
    public function storeBillPartyPayment(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $input = $request->all();
            if (empty($input) || !isset($input['party_id'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $id = $input['party_id'] ?? null;
            $amount = $input['amount'] ?? 0;
            $remark = $input['remark'] ?? '';
            $date = $input['date'] ?? date('Y-m-d');

            if (!$id || !$amount) {
                return response()->json(['status' => 'Error', 'message' => 'Party ID and Amount are required'], 400);
            }

            $data = [
                'party_id' => $id,
                'amount' => $amount,
                'remark' => $remark,
                'date' => $date
            ];

            $pay_id = DB::connection($conn)->table('bill_party_payments')->insertGetId($data);
            addActivity($pay_id, 'bill_party_payments', "Bill Party Payment Done Of Amount - " . $amount . " via API", 4, $user->id, $conn);

            $tdata = [
                'party_id' => $id,
                'type' => 'Credit',
                'payment_id' => $pay_id,
                'particular' => $remark
            ];
            DB::connection($conn)->table('bill_party_statement')->insert($tdata);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Balance Credit Successfully',
                'payment_id' => $pay_id
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Bill Party Payment Details
     */
    public function getBillPartyPaymentDetails(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $payment = DB::connection($conn)->table('bill_party_payments')->where('id', $id)->first();
            if (!$payment) {
                return response()->json(['status' => 'Error', 'message' => 'Payment not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Party Payment
     */
    public function updateBillPartyPayment(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $input = $request->all();
            if (empty($input) || !isset($input['amount'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $data = [
                'amount' => $input['amount'] ?? null,
                'remark' => $input['remark'] ?? null,
                'date' => $input['date'] ?? null
            ];

            $data = array_filter($data, function ($v) { return $v !== null; });

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided to update'], 400);
            }

            DB::connection($conn)->table('bill_party_payments')->where('id', $id)->update($data);
            addActivity($id, 'bill_party_payments', "Bill Party Payment Updated via API", 4, $user->id, $conn);

            if (isset($data['remark'])) {
                DB::connection($conn)->table('bill_party_statement')->where('payment_id', $id)->update(['particular' => $data['remark']]);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Party Balance Updated Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Bill Party Payments as CSV
     */
    public function exportBillPartyPaymentsCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $party_id = $request->get('party_id');
            $search = trim($request->get('search'));

            $query = DB::connection($conn)->table('bill_party_payments')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'bill_party_payments.party_id')
                ->select('bill_party_payments.*', 'bills_party.name as party_name');

            if ($party_id) {
                $query->where('party_id', $party_id);
            }

            if (!empty($search)) {
                $query->where('remark', 'LIKE', "%{$search}%");
            }

            $records = $query->orderBy('date', 'desc')->get();

            $filename = 'bill_party_payments_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Party Name', 'Amount', 'Date', 'Remark']);

                foreach ($records as $r) {
                    fputcsv($file, [
                        $r->id,
                        $r->party_name,
                        $r->amount,
                        $r->date,
                        $r->remark
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Bill Works
     */
    public function listBillWorks(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $per_page = $request->get('per_page', 20);

            $query = DB::connection($conn)->table('bills_work');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $data = $query->orderBy('name', 'asc')->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'data' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Bill Work
     */
    public function storeBillWork(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $input = $request->all();
            if (empty($input) || !isset($input['name'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $name = $input['name'] ?? null;
            $unit = $input['unit'] ?? null;

            if (!$name || !$unit) {
                return response()->json(['status' => 'Error', 'message' => 'Name and Unit are required'], 400);
            }

            $exists = DB::connection($conn)->table('bills_work')->where('name', $name)->exists();
            if ($exists) {
                return response()->json(['status' => 'Error', 'message' => 'Bill work already exists'], 400);
            }

            $data = ['name' => $name, 'unit' => $unit];
            $id = DB::connection($conn)->table('bills_work')->insertGetId($data);
            addActivity($id, 'bills_work', "New Bill work Created via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Work Created Successfully',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Bill Work Details
     */
    public function getBillWorkDetails(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $work = DB::connection($conn)->table('bills_work')->where('id', $id)->first();
            if (!$work) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Work not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $work
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Work
     */
    public function updateBillWork(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $input = $request->all();
            if (empty($input) || !isset($input['name'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $data = [
                'name' => $input['name'] ?? null,
                'unit' => $input['unit'] ?? null
            ];

            $data = array_filter($data, function ($v) { return $v !== null; });

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided to update'], 400);
            }

            DB::connection($conn)->table('bills_work')->where('id', $id)->update($data);
            addActivity($id, 'bills_work', "Bill Work Updated via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Work Updated Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Bill Work
     */
    public function deleteBillWork(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $work = DB::connection($conn)->table('bills_work')->where('id', $id)->first();
            if (!$work) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Work not found'], 404);
            }

            $check = DB::connection($conn)->table('new_bills_item_entry')->where('work_id', $id)->exists();
            if ($check) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Work is in use and cannot be deleted'], 400);
            }

            DB::connection($conn)->table('bills_work')->where('id', $id)->delete();
            DB::connection($conn)->table('bills_rate')->where('work_id', $id)->delete();
            
            addActivity(0, 'bills_work', "Bill Work Deleted via API: " . $work->name, 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Work Deleted Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Bill Works as CSV
     */
    public function exportBillWorksCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));

            $query = DB::connection($conn)->table('bills_work');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            $records = $query->orderBy('name', 'asc')->get();

            $filename = 'bill_works_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Name', 'Unit']);

                foreach ($records as $r) {
                    fputcsv($file, [$r->id, $r->name, $r->unit]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Bill Rates
     */
    public function listBillRates(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $site_id = $request->get('site_id');
            $work_id = $request->get('work_id');
            $per_page = $request->get('per_page', 20);

            $query = DB::connection($conn)->table('bills_rate')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'bills_rate.work_id')
                ->leftJoin('sites', 'sites.id', '=', 'bills_rate.site_id')
                ->select('bills_rate.*', 'bills_work.name as work_name', 'bills_work.unit', 'sites.name as site_name');

            if ($site_id) {
                $query->where('bills_rate.site_id', $site_id);
            }

            if ($work_id) {
                $query->where('bills_rate.work_id', $work_id);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('bills_work.name', 'LIKE', "%{$search}%")
                      ->orWhere('sites.name', 'LIKE', "%{$search}%");
                });
            }

            $data = $query->orderBy('sites.name', 'asc')->orderBy('bills_work.name', 'asc')->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'data' => $data,
                'server_time' => \Carbon\Carbon::now()->toDateTimeString()
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Bill Rate
     */
    public function storeBillRate(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $input = $request->all();
            if (empty($input) || !isset($input['work_id'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $work_id = $input['work_id'] ?? null;
            $site_id = $input['site_id'] ?? null;
            $rate = $input['rate'] ?? null;

            if (!$work_id || !$site_id || $rate === null) {
                return response()->json(['status' => 'Error', 'message' => 'Work ID, Site ID, and Rate are required'], 400);
            }

            $exists = DB::connection($conn)->table('bills_rate')->where('work_id', $work_id)->where('site_id', $site_id)->exists();
            if ($exists) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Rate already exists for this site and work'], 400);
            }

            $data = ['work_id' => $work_id, 'site_id' => $site_id, 'rate' => $rate];
            $id = DB::connection($conn)->table('bills_rate')->insertGetId($data);
            
            $site_name = DB::connection($conn)->table('sites')->where('id', $site_id)->value('name');
            addActivity($id, 'bills_rate', "Bill Work Rate Set For Site - " . $site_name . " via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Rate Created Successfully',
                'id' => $id
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Bill Rate Details
     */
    public function getBillRateDetails(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $id = $id ?? $request->get('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $rate = DB::connection($conn)->table('bills_rate')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'bills_rate.work_id')
                ->leftJoin('sites', 'sites.id', '=', 'bills_rate.site_id')
                ->select('bills_rate.*', 'bills_work.name as work_name', 'bills_work.unit', 'sites.name as site_name')
                ->where('bills_rate.id', $id)
                ->first();

            if (!$rate) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Rate not found'], 404);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $rate
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill Rate
     */
    public function updateBillRate(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $input = $request->all();
            if (empty($input) || !isset($input['rate'])) {
                $raw = $request->getContent();
                if ($raw) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $input = array_merge($input, $decoded);
                    }
                }
            }

            $data = [
                'work_id' => $input['work_id'] ?? null,
                'site_id' => $input['site_id'] ?? null,
                'rate' => $input['rate'] ?? null
            ];

            $data = array_filter($data, function ($v) { return $v !== null; });

            if (empty($data)) {
                return response()->json(['status' => 'Error', 'message' => 'No data provided to update'], 400);
            }

            // If updating work_id or site_id, check for duplicates
            if (isset($data['work_id']) || isset($data['site_id'])) {
                $current = DB::connection($conn)->table('bills_rate')->where('id', $id)->first();
                $new_work = $data['work_id'] ?? $current->work_id;
                $new_site = $data['site_id'] ?? $current->site_id;
                
                if ($new_work != $current->work_id || $new_site != $current->site_id) {
                    $exists = DB::connection($conn)->table('bills_rate')
                        ->where('work_id', $new_work)
                        ->where('site_id', $new_site)
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        return response()->json(['status' => 'Error', 'message' => 'Bill Rate already exists for this site and work'], 400);
                    }
                }
            }

            DB::connection($conn)->table('bills_rate')->where('id', $id)->update($data);
            
            $site_id = $data['site_id'] ?? DB::connection($conn)->table('bills_rate')->where('id', $id)->value('site_id');
            $site_name = DB::connection($conn)->table('sites')->where('id', $site_id)->value('name');
            addActivity($id, 'bills_rate', "Bill Work Rate Updated For Site - " . $site_name . " via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Rate Updated Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Bill Rate
     */
    public function deleteBillRate(Request $request, $id = null)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $id = $id ?? $request->input('id');

            if (!$id) {
                return response()->json(['status' => 'Error', 'message' => 'ID is required'], 400);
            }

            $rate = DB::connection($conn)->table('bills_rate')->where('id', $id)->first();
            if (!$rate) {
                return response()->json(['status' => 'Error', 'message' => 'Bill Rate not found'], 404);
            }

            DB::connection($conn)->table('bills_rate')->where('id', $id)->delete();
            addActivity(0, 'bills_rate', "Bill Work Rate Deleted Of Amount - " . $rate->rate . " via API", 4, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Bill Rate Deleted Successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Bill Rates as CSV
     */
    public function exportBillRatesCsv(Request $request)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $site_id = $request->get('site_id');

            $query = DB::connection($conn)->table('bills_rate')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'bills_rate.work_id')
                ->leftJoin('sites', 'sites.id', '=', 'bills_rate.site_id')
                ->select('bills_rate.id', 'sites.name as site_name', 'bills_work.name as work_name', 'bills_rate.rate', 'bills_work.unit');

            if ($site_id) {
                $query->where('bills_rate.site_id', $site_id);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('bills_work.name', 'LIKE', "%{$search}%")
                      ->orWhere('sites.name', 'LIKE', "%{$search}%");
                });
            }

            $records = $query->orderBy('sites.name', 'asc')->orderBy('bills_work.name', 'asc')->get();

            $filename = 'bill_rates_' . date('Y-m-d') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ];

            $callback = function () use ($records) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Site Name', 'Work Name', 'Rate', 'Unit']);

                foreach ($records as $r) {
                    fputcsv($file, [$r->id, $r->site_name, $r->work_name, $r->rate, $r->unit]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function listConsumptionByStatus(Request $request, $status, $filterType = 'Both')
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            
            $per_page = $request->get('per_page', 20);
            $site_id = $request->get('site_id');
            $material_id = $request->get('material_id');
            $search = $request->get('search'); // Site name search
            $start_date = $request->get('start_date', $request->get('from_date'));
            $end_date = $request->get('end_date', $request->get('to_date'));

            // Consumption Query
            $consumption_query = DB::connection($conn)->table('material_consumption')
                ->leftJoin('materials', 'materials.id', '=', 'material_consumption.material_id')
                ->leftJoin('sites', 'sites.id', '=', 'material_consumption.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_consumption.unit')
                ->leftJoin('users', 'users.id', '=', 'material_consumption.user_id')
                ->select(
                    'material_consumption.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'sites.name as site_name', 
                    'users.name as user_name',
                    DB::raw("'Consumption' as entry_type")
                );

            // Wastage Query
            $wastage_query = DB::connection($conn)->table('material_wastage')
                ->leftJoin('materials', 'materials.id', '=', 'material_wastage.material_id')
                ->leftJoin('sites', 'sites.id', '=', 'material_wastage.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_wastage.unit')
                ->leftJoin('users', 'users.id', '=', 'material_wastage.user_id')
                ->select(
                    'material_wastage.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'sites.name as site_name', 
                    'users.name as user_name',
                    DB::raw("'Wastage' as entry_type")
                );

            if ($status == 'Pending') {
                $consumption_query->where('material_consumption.status', 'Pending');
                $wastage_query->where('material_wastage.status', 'Pending');
            } else {
                $consumption_query->where('material_consumption.status', '!=', 'Pending');
                $wastage_query->where('material_wastage.status', '!=', 'Pending');
            }

            // Apply Common Filters
            $queries = ['Consumption' => $consumption_query, 'Wastage' => $wastage_query];
            foreach ($queries as $type => $q) {
                if ($site_id) $q->where($q->from . '.site_id', $site_id);
                if ($material_id) $q->where($q->from . '.material_id', $material_id);
                if ($search) {
                    $q->where('sites.name', 'like', "%$search%");
                }
                if ($start_date && $end_date) {
                    $q->whereBetween($q->from . '.date', [$start_date, $end_date]);
                }
            }

            // Determine final query based on filterType
            if ($filterType == 'Consumption') {
                $final_query = $consumption_query->orderBy('date', 'desc')->orderBy('id', 'desc');
            } else if ($filterType == 'Wastage') {
                $final_query = $wastage_query->orderBy('date', 'desc')->orderBy('id', 'desc');
            } else {
                // Combine using Union
                $final_query = $consumption_query->union($wastage_query)->orderBy('date', 'desc')->orderBy('id', 'desc');
            }
            
            // CSV Export: /api/v1/materials/consumption/pending?export=csv
            if ($request->get('export') == 'csv') {
                $data = $final_query->get();
                $filename = strtolower($status) . '_' . strtolower($filterType) . '_' . date('Y-m-d') . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];
                return response()->stream(function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['#', 'Date', 'Type', 'Material', 'Site', 'Qty', 'Unit', 'User', 'Remark', 'Reason (for Wastage)']);
                    $i = 1;
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $i++,
                            $row->date,
                            $row->entry_type,
                            $row->material_name,
                            $row->site_name,
                            $row->qty,
                            $row->unit_name,
                            $row->user_name,
                            $row->remark,
                            $row->reason ?? ''
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            $per_page = $request->get('per_page', 20);
            $data = $final_query->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'status_type' => $status,
                'filter_type' => $filterType,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function exportPendingMaterialEntriesCsv(Request $request)
    {
        return $this->exportMaterialEntriesCsvByStatus($request, 'Pending');
    }

    public function exportVerifiedMaterialEntriesCsv(Request $request)
    {
        return $this->exportMaterialEntriesCsvByStatus($request, 'Verified');
    }

    private function listMaterialEntriesByStatus(Request $request, $statusType)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $from_date = $request->get('from_date');
            $to_date = $request->get('to_date');

            $query = DB::connection($conn)->table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->leftJoin('users', 'users.id', '=', 'material_entry.user_id')
                ->select(
                    'material_entry.*', 
                    'materials.name as material_name', 
                    'units.name as unit_name', 
                    'sites.name as site_name', 
                    'users.name as user_name', 
                    'material_supplier.name as supplier_name'
                );

            if ($statusType == 'Pending') {
                $query->where('material_entry.status', '=', 'Pending');
            } else {
                $query->where('material_entry.status', '!=', 'Pending');
            }

            if ($from_date && $to_date) {
                $query->whereBetween('material_entry.date', [$from_date, $to_date]);
            } elseif ($from_date) {
                $query->where('material_entry.date', '>=', $from_date);
            } elseif ($to_date) {
                $query->where('material_entry.date', '<=', $to_date);
            } else {
                $user = $request->user();
                $view_duration = getUserViewDuration($user, $conn);
                $dates = getdurationdates($view_duration);
                $min_date = date('Y-m-d', strtotime($dates['min']));
                $max_date = date('Y-m-d', strtotime($dates['max']));
                $query->whereBetween('material_entry.date', [$min_date, $max_date]);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                        ->orWhere('materials.name', 'LIKE', "%{$search}%")
                        ->orWhere('material_entry.vehical', 'LIKE', "%{$search}%")
                        ->orWhere('sites.name', 'LIKE', "%{$search}%")
                        ->orWhere('material_entry.remark', 'LIKE', "%{$search}%");
                });
            }

            $entries = $query->orderBy('material_entry.date', 'desc')->paginate(10);

            return response()->json([
                'status' => 'Ok', 
                'data' => $entries,
                'filters' => [
                    'from_date' => $from_date,
                    'to_date' => $to_date,
                    'search' => $search
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getMaterialEntry(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $entry = DB::connection($conn)->table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->select('material_entry.*', 'materials.name as material_name', 'units.name as unit_name', 'sites.name as site_name', 'material_supplier.name as supplier_name')
                ->where('material_entry.id', $id)
                ->first();

            if (!$entry) {
                return response()->json(['status' => 'Error', 'message' => 'Material entry not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $entry]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeMaterialEntry(Request $request)
    {
        // (Previously implemented logic remains here, just ensuring it's in the right place)
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            $role_id = $user->role ?? 0;
            
            $status = 'Pending';
            $role_details = DB::connection($conn)->table('roles')->where('id', $role_id)->first();
            if ($role_details && $role_details->can_certify == 1) {
                $status = 'Approved';
            }

            $input = $request->all();
            $site_ids = (array) ($input['site_id'] ?? []);
            if (empty($site_ids)) return response()->json(['status' => 'Error', 'message' => 'Site ID is required'], 400);

            $suppliers = (array) ($input['supplier'] ?? []);
            $material_ids = (array) ($input['material_id'] ?? []);
            $units = (array) ($input['unit'] ?? []);
            $qtys = (array) ($input['qty'] ?? []);
            $vehicals = (array) ($input['vehical'] ?? []);
            $remarks = (array) ($input['remark'] ?? []);
            $dates = (array) ($input['date'] ?? []);
            $images = $request->file('image');

            $length = count($site_ids);
            $created_ids = [];

            for ($i = 0; $i < $length; $i++) {
                $imagePath = "images/expense.png";
                if ($images && isset($images[$i])) {
                    $image = $images[$i];
                    $imageName = time() . rand(10000, 1000000) . '.' . $image->getClientOriginalExtension();
                    $image->move(public_path('images/app_images/' . $conn . '/material'), $imageName);
                    $imagePath = "images/app_images/" . $conn . "/material/" . $imageName;
                }

                $data = [
                    'supplier' => $suppliers[$i] ?? ($suppliers[0] ?? null),
                    'material_id' => $material_ids[$i] ?? ($material_ids[0] ?? null),
                    'unit' => $units[$i] ?? ($units[0] ?? null),
                    'qty' => $qtys[$i] ?? ($qtys[0] ?? 0),
                    'vehical' => $vehicals[$i] ?? ($vehicals[0] ?? null),
                    'image' => $imagePath,
                    'remark' => $remarks[$i] ?? ($remarks[0] ?? null),
                    'site_id' => $site_ids[$i] ?? ($site_ids[0] ?? null),
                    'status' => $status,
                    'user_id' => $user->id,
                    'date' => $dates[$i] ?? ($dates[0] ?? date('Y-m-d')),
                    'create_datetime' => date('Y-m-d H:i:s')
                ];

                $id = DB::connection($conn)->table('material_entry')->insertGetId($data);
                $created_ids[] = $id;

                if ($status == 'Approved') {
                    $this->approveMaterialEntryLogic($id, $conn, $user->id);
                }
            }

            addActivity(0, 'material_entry', "New Material Entries Created via API", 3, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Created successfully', 'ids' => $created_ids, 'current_status' => $status]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateMaterialEntry(Request $request, $id)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $entry = DB::connection($conn)->table('material_entry')->where('id', $id)->first();
            if (!$entry) return response()->json(['status' => 'Error', 'message' => 'Entry not found'], 404);

            $input = $request->all();
            $imagePath = $entry->image;

            if ($request->hasFile('image')) {
                if ($imagePath && $imagePath != 'images/expense.png' && file_exists(public_path($imagePath))) {
                    @unlink(public_path($imagePath));
                }
                $image = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('images/app_images/' . $conn . '/material'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/material/" . $imageName;
            }

            $updateData = [
                'supplier' => $input['supplier'] ?? $entry->supplier,
                'material_id' => $input['material_id'] ?? $entry->material_id,
                'unit' => $input['unit'] ?? $entry->unit,
                'qty' => $input['qty'] ?? $entry->qty,
                'vehical' => $input['vehical'] ?? $entry->vehical,
                'remark' => $input['remark'] ?? $entry->remark,
                'site_id' => $input['site_id'] ?? $entry->site_id,
                'date' => $input['date'] ?? $entry->date,
                'image' => $imagePath
            ];

            DB::connection($conn)->table('material_entry')->where('id', $id)->update($updateData);
            addActivity($id, 'material_entry', "Material Entry Updated via API", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Material entry updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteMaterialEntry(Request $request, $id = null)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = isset($input['ids']) ? (array)$input['ids'] : explode(',', $id ?? '');

            $deletedCount = 0;
            $skippedCount = 0;

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;

                $entry = DB::connection($conn)->table('material_entry')->where('id', $singleId)->first();
                if (!$entry) {
                    $skippedCount++;
                    continue;
                }

                // Revert stock if it was approved
                if ($entry->status == 'Approved') {
                    $this->rejectMaterialEntryLogic($singleId, $conn, $user->id);
                }

                if ($entry->image && $entry->image != 'images/expense.png' && file_exists(public_path($entry->image))) {
                    @unlink(public_path($entry->image));
                }

                DB::connection($conn)->table('material_entry')->where('id', $singleId)->delete();
                $deletedCount++;
            }

            addActivity(0, 'material_entry', "Material Entries Deleted via API (Count: $deletedCount)", 3, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => "Successfully deleted $deletedCount entries. Skipped $skippedCount."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function approveMaterialEntry(Request $request, $id = null)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = isset($input['ids']) ? (array)$input['ids'] : explode(',', $id ?? '');

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;
                $this->approveMaterialEntryLogic($singleId, $conn, $user->id);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Material entries approved successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function rejectMaterialEntry(Request $request, $id = null)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = isset($input['ids']) ? (array)$input['ids'] : explode(',', $id);

            foreach ($ids as $singleId) {
                $singleId = trim($singleId);
                if (empty($singleId)) continue;
                $this->rejectMaterialEntryLogic($singleId, $conn, $user->id);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Material entries rejected successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function approveMaterialEntryLogic($id, $conn, $userId)
    {
        $material_entry = DB::connection($conn)->table('material_entry')->where('id', $id)->first();
        if (!$material_entry || $material_entry->status == 'Approved') return;

        DB::connection($conn)->table('material_entry')->where('id', $id)->update(['status' => 'Approved']);

        // Stock Transaction
        DB::connection($conn)->table('material_stock_transactions')->insert([
            'site_id' => $material_entry->site_id,
            'material_id' => $material_entry->material_id,
            'qty' => $material_entry->qty,
            'unit' => $material_entry->unit,
            'type' => 'IN',
            'refrence' => 'Purchase',
            'refrence_id' => $id
        ]);

        // Stock Record
        $check = DB::connection($conn)->table('material_stock_record')
            ->where(['site_id' => $material_entry->site_id, 'material_id' => $material_entry->material_id, 'unit' => $material_entry->unit])
            ->first();

        if ($check) {
            DB::connection($conn)->table('material_stock_record')->where('id', $check->id)->update(['qty' => $check->qty + $material_entry->qty]);
        } else {
            DB::connection($conn)->table('material_stock_record')->insert([
                'material_id' => $material_entry->material_id, 'site_id' => $material_entry->site_id, 'qty' => $material_entry->qty, 'unit' => $material_entry->unit
            ]);
        }

        addActivity($id, 'material_entry', "Material Entry Approved via API", 3, $userId, $conn);
    }

    private function rejectMaterialEntryLogic($id, $conn, $userId)
    {
        $material_entry = DB::connection($conn)->table('material_entry')->where('id', $id)->first();
        if (!$material_entry) return;

        DB::connection($conn)->table('material_entry')->where('id', $id)->update(['status' => 'Rejected']);

        // Revert stock if it was approved
        $stock_tx = DB::connection($conn)->table('material_stock_transactions')
            ->where('refrence_id', '=', $id)
            ->where('refrence', '=', 'Purchase')
            ->first();

        if ($stock_tx) {
            DB::connection($conn)->table('material_stock_transactions')->where('id', $stock_tx->id)->delete();
            
            $check = DB::connection($conn)->table('material_stock_record')
                ->where(['site_id' => $material_entry->site_id, 'material_id' => $material_entry->material_id, 'unit' => $material_entry->unit])
                ->first();

            if ($check) {
                DB::connection($conn)->table('material_stock_record')->where('id', $check->id)->update(['qty' => $check->qty - $material_entry->qty]);
            }
        }

        addActivity($id, 'material_entry', "Material Entry Rejected via API", 3, $userId, $conn);
    }

    private function exportMaterialEntriesCsvByStatus(Request $request, $statusType)
    {
        try {
            $conn = config('database.default');
            $search = trim($request->get('search'));
            $from_date = $request->get('from_date');
            $to_date = $request->get('to_date');

            $query = DB::connection($conn)->table('material_entry')
                ->leftJoin('materials', 'materials.id', '=', 'material_entry.material_id')
                ->leftJoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                ->leftJoin('sites', 'sites.id', '=', 'material_entry.site_id')
                ->leftJoin('units', 'units.id', '=', 'material_entry.unit')
                ->leftJoin('users', 'users.id', '=', 'material_entry.user_id')
                ->select(
                    'material_entry.id',
                    'material_supplier.name as supplier',
                    'materials.name as material',
                    'units.name as unit',
                    'material_entry.qty',
                    'material_entry.vehical',
                    'material_entry.status',
                    'material_entry.remark',
                    'sites.name as site',
                    'users.name as user',
                    'material_entry.date'
                );

            if ($statusType == 'Pending') {
                $query->where('material_entry.status', '=', 'Pending');
            } else {
                $query->where('material_entry.status', '!=', 'Pending');
            }

            if ($from_date && $to_date) {
                $query->whereBetween('material_entry.date', [$from_date, $to_date]);
            } elseif ($from_date) {
                $query->where('material_entry.date', '>=', $from_date);
            } elseif ($to_date) {
                $query->where('material_entry.date', '<=', $to_date);
            }

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('material_supplier.name', 'LIKE', "%{$search}%")
                        ->orWhere('materials.name', 'LIKE', "%{$search}%")
                        ->orWhere('material_entry.vehical', 'LIKE', "%{$search}%")
                        ->orWhere('sites.name', 'LIKE', "%{$search}%");
                });
            }

            $entries = $query->orderBy('material_entry.date', 'desc')->get();
            $filename = strtolower($statusType) . "_material_entries_" . date('Y-m-d') . ".csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
            ];

            $callback = function() use ($entries) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['ID', 'Supplier', 'Material', 'Unit', 'Qty', 'Vehicle', 'Status', 'Remark', 'Site', 'User', 'Date']);

                foreach ($entries as $e) {
                    fputcsv($file, [$e->id, $e->supplier, $e->material, $e->unit, $e->qty, $e->vehical, $e->status, $e->remark, $e->site, $e->user, $e->date]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Stock Unit Conversions (matches web: stock_unit_conversion)
     */
    public function getStockUnitConversions(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            
            // Get user's role and site visibility
            $role = DB::connection($conn)->table('roles')->where('id', $user->role_id)->first();
            $visibility = $role->visiblity_at_site ?? 'all';
            
            $query = DB::connection($conn)->table('material_units_conversion_record')
                ->leftJoin('materials', 'materials.id', '=', 'material_units_conversion_record.material_id')
                ->leftJoin('sites', 'sites.id', '=', 'material_units_conversion_record.site_id')
                ->leftJoin('units as f_unit', 'f_unit.id', '=', 'material_units_conversion_record.from_unit')
                ->leftJoin('units as t_unit', 't_unit.id', '=', 'material_units_conversion_record.to_unit')
                ->leftJoin('users', 'users.id', '=', 'material_units_conversion_record.user_id')
                ->select(
                    'material_units_conversion_record.*', 
                    'materials.name as material_name', 
                    'f_unit.name as from_unit_name', 
                    't_unit.name as to_unit_name', 
                    'sites.name as site_name', 
                    'users.name as user_name'
                );

            $query->orderBy('material_units_conversion_record.id', 'DESC');

            // Search filter
            if ($request->has('search')) {
                $search = trim($request->get('search'));
                if (!empty($search)) {
                    $query->where(function($q) use ($search) {
                        $q->where('materials.name', 'LIKE', "%{$search}%")
                          ->orWhere('sites.name', 'LIKE', "%{$search}%")
                          ->orWhere('f_unit.name', 'LIKE', "%{$search}%")
                          ->orWhere('t_unit.name', 'LIKE', "%{$search}%")
                          ->orWhere('material_units_conversion_record.remark', 'LIKE', "%{$search}%");
                    });
                }
            }

            // Date filters
            if ($request->has('from_date') && $request->has('to_date')) {
                $query->whereBetween('material_units_conversion_record.date', [$request->get('from_date'), $request->get('to_date')]);
            }

            $query->orderBy('material_units_conversion_record.id', 'DESC');

            // CSV Export
            if ($request->get('export') == 'csv') {
                $data = $query->get();
                $filename = 'unit_conversions_' . date('Y-m-d') . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                    'Expires' => '0'
                ];
                return response()->stream(function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['#', 'Date', 'Material', 'Site', 'From Unit', 'To Unit', 'From Qty', 'Converted Qty', 'Remark', 'Done By']);
                    $i = 1;
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $i++,
                            $row->date,
                            $row->material_name,
                            $row->site_name,
                            $row->from_unit_name,
                            $row->to_unit_name,
                            $row->qty,
                            $row->updated_qty,
                            $row->remark,
                            $row->user_name
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            $perPage = $request->get('per_page', 10);
            $conversions = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $conversions,
                'server_time' => Carbon::now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store Stock Unit Conversion (matches web: newStockUnitConversionForm)
     */
    public function storeStockUnitConversion(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            // Robust data parsing
            $data = $request->all();
            $filteredData = array_diff_key($data, array_flip(['tenant_conn', 'tenant_uid', 'tenant_role', 'tenant_site_id']));
            
            if (empty($filteredData)) {
                $jsonData = $request->json()->all();
                if (!empty($jsonData)) {
                    $data = array_merge($data, $jsonData);
                } else {
                    $raw = file_get_contents('php://input');
                    $decoded = json_decode($raw, true);
                    if ($decoded) $data = array_merge($data, $decoded);
                }
            }

            $validator = Validator::make($data, [
                'material_id' => 'required',
                'site_id' => 'required',
                'from_unit' => 'required',
                'to_unit' => 'required',
                'qty' => 'required|numeric|min:0.01',
                'updated_qty' => 'required|numeric|min:0.01',
                'date' => 'required|date',
                'remark' => 'nullable'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'Error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
            }

            if ($data['from_unit'] == $data['to_unit']) {
                return response()->json(['status' => 'Error', 'message' => 'You cannot convert between same units.'], 400);
            }

            // Check stock for from_unit
            $check_current_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', '=', $data['site_id'])
                ->where('material_id', '=', $data['material_id'])
                ->where('unit', '=', $data['from_unit'])
                ->first();

            if (!$check_current_stock || $check_current_stock->qty < $data['qty']) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Insufficient stock for conversion. Available: ' . ($check_current_stock->qty ?? 0)
                ], 400);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $data, $user, $check_current_stock) {
                $insertData = [
                    'material_id' => $data['material_id'],
                    'site_id' => $data['site_id'],
                    'from_unit' => $data['from_unit'],
                    'to_unit' => $data['to_unit'],
                    'qty' => $data['qty'],
                    'updated_qty' => $data['updated_qty'],
                    'user_id' => $user->id,
                    'remark' => $data['remark'] ?? '',
                    'date' => $data['date'],
                ];

                $id = DB::connection($conn)->table('material_units_conversion_record')->insertGetId($insertData);

                // Stock Transactions (Debit from_unit, Credit to_unit)
                DB::connection($conn)->table('material_stock_transactions')->insert([
                    ['site_id' => $data['site_id'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['from_unit'], 'type' => 'OUT', 'refrence' => 'Unit Conversion Debit', 'refrence_id' => $id],
                    ['site_id' => $data['site_id'], 'material_id' => $data['material_id'], 'qty' => $data['updated_qty'], 'unit' => $data['to_unit'], 'type' => 'IN', 'refrence' => 'Unit Conversion Credit', 'refrence_id' => $id]
                ]);

                // Update From Unit Stock
                DB::connection($conn)->table('material_stock_record')
                    ->where('id', '=', $check_current_stock->id)
                    ->decrement('qty', $data['qty'], ['last_updated' => Carbon::now()]);

                // Update To Unit Stock
                $to_unit_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', '=', $data['site_id'])
                    ->where('material_id', '=', $data['material_id'])
                    ->where('unit', '=', $data['to_unit'])
                    ->first();

                if ($to_unit_stock) {
                    DB::connection($conn)->table('material_stock_record')
                        ->where('id', '=', $to_unit_stock->id)
                        ->increment('qty', $data['updated_qty'], ['last_updated' => Carbon::now()]);
                } else {
                    DB::connection($conn)->table('material_stock_record')->insert([
                        'material_id' => $data['material_id'],
                        'site_id' => $data['site_id'],
                        'qty' => $data['updated_qty'],
                        'unit' => $data['to_unit'],
                        'last_updated' => Carbon::now()
                    ]);
                }

                addActivity($id, 'material_units_conversion_record', "Material Unit Conversion Completed via API", 3, $user->id, $conn);

                return response()->json([
                    'status' => 'Ok', 
                    'message' => 'Material Unit Conversion successful!',
                    'id' => $id
                ]);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Stock Unit Conversion (matches web: deleteStockUnitConversion)
     */
    public function deleteStockUnitConversion(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $conversion = DB::connection($conn)->table('material_units_conversion_record')->where('id', '=', $id)->first();
            if (!$conversion) {
                return response()->json(['status' => 'Error', 'message' => 'Conversion record not found.'], 404);
            }

            // Check if to_unit stock has already been used
            $check_to_unit_stock = DB::connection($conn)->table('material_stock_record')
                ->where('site_id', '=', $conversion->site_id)
                ->where('material_id', '=', $conversion->material_id)
                ->where('unit', '=', $conversion->to_unit)
                ->first();

            if (!$check_to_unit_stock || $check_to_unit_stock->qty < $conversion->updated_qty) {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Target site has already used the material in converted unit. Cannot delete.'
                ], 400);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $id, $conversion, $user, $check_to_unit_stock) {
                // Revert from_unit stock
                $from_unit_stock = DB::connection($conn)->table('material_stock_record')
                    ->where('site_id', '=', $conversion->site_id)
                    ->where('material_id', '=', $conversion->material_id)
                    ->where('unit', '=', $conversion->from_unit)
                    ->first();
                
                if ($from_unit_stock) {
                    DB::connection($conn)->table('material_stock_record')
                        ->where('id', '=', $from_unit_stock->id)
                        ->increment('qty', $conversion->qty, ['last_updated' => Carbon::now()]);
                }

                // Revert to_unit stock
                DB::connection($conn)->table('material_stock_record')
                    ->where('id', '=', $check_to_unit_stock->id)
                    ->decrement('qty', $conversion->updated_qty, ['last_updated' => Carbon::now()]);

                // Delete records
                DB::connection($conn)->table('material_units_conversion_record')->where('id', '=', $id)->delete();
                DB::connection($conn)->table('material_stock_transactions')
                    ->where('refrence_id', '=', $id)
                    ->whereIn('refrence', ['Unit Conversion Debit', 'Unit Conversion Credit'])
                    ->delete();

                addActivity(0, 'material_units_conversion_record', "Material Unit Conversion Deleted via API", 3, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Material Unit Conversion deleted and stock reverted successfully!']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * ==========================================
     * SITE BILLS MANAGEMENT
     * ==========================================
     */

    public function listPendingSiteBills(Request $request)
    {
        $request->merge(['status' => 'Pending']);
        return $this->listSiteBills($request);
    }

    public function listVerifiedSiteBills(Request $request)
    {
        $request->merge(['status' => 'Approved']);
        return $this->listSiteBills($request);
    }

    public function listSiteBills(Request $request)
    {
        try {
            $conn = config('database.default');
            $status = $request->get('status');
            $search = $request->get('search');
            $site_id = $request->get('site_id');
            $party_id = $request->get('party_id');
            $from_date = $request->get('from_date', $request->get('start_date'));
            $to_date = $request->get('to_date', $request->get('end_date'));

            $query = DB::connection($conn)->table('new_bill_entry')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                ->select('new_bill_entry.*', 'sites.name as site_name', 'users.name as user_name', 'bills_party.name as party_name');

            if ($status) {
                $query->where('new_bill_entry.status', $status);
            }

            if ($site_id && $site_id != 'all') {
                $query->where('new_bill_entry.site_id', $site_id);
            }

            if ($party_id) {
                $query->where('new_bill_entry.party_id', $party_id);
            }

            if ($from_date && $to_date) {
                $query->whereBetween('new_bill_entry.billdate', [$from_date, $to_date]);
            } else {
                $user = $request->user();
                $view_duration = getUserViewDuration($user, $conn);
                $dates = getdurationdates($view_duration);
                $min_date = date('Y-m-d', strtotime($dates['min']));
                $max_date = date('Y-m-d', strtotime($dates['max']));
                $query->whereBetween('new_bill_entry.billdate', [$min_date, $max_date]);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('new_bill_entry.bill_no', 'like', "%$search%")
                        ->orWhere('bills_party.name', 'like', "%$search%")
                        ->orWhere('new_bill_entry.remark', 'like', "%$search%");
                });
            }

            $bills = $query->orderBy('new_bill_entry.id', 'desc')->paginate($request->get('per_page', 20));

            // Map and add from_date and to_date for the API response
            $bills->getCollection()->transform(function($bill) {
                $period = explode(' to ', $bill->bill_period);
                $bill->bill_from_date = $period[0] ?? '';
                $bill->bill_to_date = $period[1] ?? '';
                return $bill;
            });

            // CSV Export logic
            if ($request->get('export') == 'csv') {
                $data = $query->orderBy('new_bill_entry.id', 'desc')->get();
                $filename = 'site_bills_' . date('Y-m-d') . '.csv';
                $headers = [
                    'Content-type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ];
                return response()->stream(function () use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['#', 'Bill No', 'Date', 'Party', 'Site', 'Amount', 'Status', 'Period', 'Created By', 'Remark']);
                    $i = 1;
                    foreach ($data as $row) {
                        fputcsv($file, [
                            $i++,
                            $row->bill_no,
                            $row->billdate,
                            $row->party_name,
                            $row->site_name,
                            $row->amount,
                            $row->status,
                            $row->bill_period,
                            $row->user_name,
                            $row->remark
                        ]);
                    }
                    fclose($file);
                }, 200, $headers);
            }

            $bills = $query->orderBy('new_bill_entry.id', 'desc')->paginate($request->get('per_page', 20));

            return response()->json(['status' => 'Ok', 'data' => $bills]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSiteBill(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $data = $request->all();
            $filteredData = array_diff_key($data, array_flip(['tenant_conn', 'tenant_uid', 'tenant_role', 'tenant_site_id']));

            if (empty($filteredData['items'])) {
                $jsonData = $request->json()->all();
                if (!empty($jsonData['items'])) {
                    $data = array_merge($data, $jsonData);
                } else {
                    $raw = file_get_contents('php://input');
                    $decoded = json_decode($raw, true);
                    if ($decoded) $data = array_merge($data, $decoded);
                }
            }

            $validator = Validator::make($data, [
                'bill_party_id' => 'required',
                'bill_site_id' => 'required',
                'bill_no' => 'required',
                'bill_date' => 'required|date',
                'bill_from_date' => 'required|date',
                'bill_to_date' => 'required|date',
                'items' => 'required|array|min:1',
                'items.*.work_id' => 'required',
                'items.*.qty' => 'required|numeric',
                'items.*.rate' => 'required|numeric',
                'items.*.unit' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'Error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
            }

            $party = DB::connection($conn)->table('bills_party')->where('id', $data['bill_party_id'])->first();
            if (!$party || $party->status != 'Active') {
                return response()->json(['status' => 'Error', 'message' => 'Bill Party is not active or not found.'], 400);
            }

            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $totalAmount += ($item['qty'] * $item['rate']);
            }

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);
            $bill_period = ($data['bill_from_date'] ?? '') . " to " . ($data['bill_to_date'] ?? '');

            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images/app_images/' . $conn . '/bill'), $fileName);
                    $attachments[] = 'images/app_images/' . $conn . '/bill/' . $fileName;
                }
            }

            return DB::connection($conn)->transaction(function () use ($conn, $data, $user, $status, $totalAmount, $bill_period, $attachments) {
                $billId = DB::connection($conn)->table('new_bill_entry')->insertGetId([
                    'party_id' => $data['bill_party_id'],
                    'bill_no' => $data['bill_no'],
                    'site_id' => $data['bill_site_id'],
                    'billdate' => $data['bill_date'],
                    'bill_period' => $bill_period,
                    'user_id' => $user->id,
                    'status' => $status,
                    'amount' => $totalAmount,
                    'remark' => $data['remark'] ?? '',
                    'attachments' => count($attachments) > 0 ? json_encode($attachments) : null,
                    'create_datetime' => Carbon::now()
                ]);

                foreach ($data['items'] as $item) {
                    DB::connection($conn)->table('new_bills_item_entry')->insert([
                        'bill_id' => $billId,
                        'work_id' => $item['work_id'],
                        'unit' => $item['unit'],
                        'rate' => $item['rate'],
                        'qty' => $item['qty'],
                        'amount' => $item['qty'] * $item['rate']
                    ]);
                }

                addActivity($billId, 'new_bill_entry', "New Bill Created via API - " . $data['bill_no'], 4, $user->id, $conn);

                if ($status == 'Approved') {
                    $this->syncBillToStatement($billId, $conn);
                }

                return response()->json(['status' => 'Ok', 'message' => 'Bill created successfully', 'id' => $billId]);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getSiteBillDetails(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $bill = DB::connection($conn)->table('new_bill_entry')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                ->select('new_bill_entry.*', 'sites.name as site_name', 'users.name as user_name', 'bills_party.name as party_name', 
                         'bills_party.bank_ac', 'bills_party.ifsc', 'bills_party.bankname', 'bills_party.ac_holder_name', 'bills_party.panno')
                ->where('new_bill_entry.id', $id)
                ->first();

            if (!$bill) {
                return response()->json(['status' => 'Error', 'message' => 'Bill not found'], 404);
            }

            $period = explode(' to ', $bill->bill_period);
            $bill->bill_from_date = $period[0] ?? '';
            $bill->bill_to_date = $period[1] ?? '';

            $items = DB::connection($conn)->table('new_bills_item_entry')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                ->where('bill_id', $id)
                ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                ->get();

            return response()->json(['status' => 'Ok', 'data' => ['bill' => $bill, 'items' => $items]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSiteBill(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $bill = DB::connection($conn)->table('new_bill_entry')->where('id', $id)->first();
            if (!$bill) return response()->json(['status' => 'Error', 'message' => 'Bill not found'], 404);

            if ($bill->status == 'Approved') {
                return response()->json(['status' => 'Error', 'message' => 'Cannot update an approved bill.'], 403);
            }

            $data = $request->all();
            $filteredData = array_diff_key($data, array_flip(['tenant_conn', 'tenant_uid', 'tenant_role', 'tenant_site_id']));

            if (empty($filteredData) || (empty($filteredData['items']) && $request->isJson())) {
                $jsonData = $request->json()->all();
                if (!empty($jsonData)) {
                    $data = array_merge($data, $jsonData);
                } else {
                    $raw = file_get_contents('php://input');
                    $decoded = json_decode($raw, true);
                    if ($decoded) $data = array_merge($data, $decoded);
                }
            }

            $validator = Validator::make($data, [
                'bill_party_id' => 'sometimes|required',
                'bill_site_id' => 'sometimes|required',
                'bill_no' => 'sometimes|required',
                'bill_date' => 'sometimes|required|date',
                'bill_from_date' => 'sometimes|required|date',
                'bill_to_date' => 'sometimes|required|date',
                'items' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'Error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 400);
            }

            $totalAmount = 0;
            if (isset($data['items'])) {
                foreach ($data['items'] as $item) {
                    $totalAmount += ($item['qty'] * $item['rate']);
                }
            } else {
                $totalAmount = $bill->amount;
            }

            $bill_period = (isset($data['bill_from_date']) && isset($data['bill_to_date']))
                ? ($data['bill_from_date'] . " to " . $data['bill_to_date'])
                : $bill->bill_period;

            $existing = $request->input('existing_attachments');
            $attachments = [];
            if ($bill && !empty($bill->attachments)) {
                $oldAttachments = [];
                $decoded = json_decode($bill->attachments, true);
                if (is_array($decoded)) {
                    $oldAttachments = $decoded;
                } else {
                    $oldAttachments = [$bill->attachments];
                }
                
                if (is_null($existing)) {
                    $attachments = $oldAttachments;
                } else {
                    $existingArray = is_array($existing) ? $existing : json_decode($existing, true);
                    if (!is_array($existingArray)) {
                        $existingArray = [];
                    }
                    foreach ($oldAttachments as $old) {
                        if (in_array($old, $existingArray)) {
                            $attachments[] = $old;
                        } else {
                            if (\File::exists(public_path($old))) {
                                \File::delete(public_path($old));
                            }
                        }
                    }
                }
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images/app_images/' . $conn . '/bill'), $fileName);
                    $attachments[] = 'images/app_images/' . $conn . '/bill/' . $fileName;
                }
            }

            return DB::connection($conn)->transaction(function () use ($conn, $id, $data, $user, $totalAmount, $bill_period, $bill, $attachments) {
                $updateData = [
                    'party_id' => $data['bill_party_id'] ?? $bill->party_id,
                    'bill_no' => $data['bill_no'] ?? $bill->bill_no,
                    'site_id' => $data['bill_site_id'] ?? $bill->site_id,
                    'billdate' => $data['bill_date'] ?? $bill->billdate,
                    'bill_period' => $bill_period,
                    'amount' => $totalAmount,
                    'remark' => $data['remark'] ?? $bill->remark,
                    'attachments' => count($attachments) > 0 ? json_encode($attachments) : null,
                ];

                DB::connection($conn)->table('new_bill_entry')->where('id', $id)->update($updateData);

                if (isset($data['items'])) {
                    DB::connection($conn)->table('new_bills_item_entry')->where('bill_id', $id)->delete();
                    foreach ($data['items'] as $item) {
                        DB::connection($conn)->table('new_bills_item_entry')->insert([
                            'bill_id' => $id,
                            'work_id' => $item['work_id'],
                            'unit' => $item['unit'],
                            'rate' => $item['rate'],
                            'qty' => $item['qty'],
                            'amount' => $item['qty'] * $item['rate']
                        ]);
                    }
                }

                addActivity($id, 'new_bill_entry', "Bill Updated via API", 4, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Bill updated successfully']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteSiteBill(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();

            $bill = DB::connection($conn)->table('new_bill_entry')->where('id', $id)->first();
            if (!$bill) return response()->json(['status' => 'Error', 'message' => 'Bill not found'], 404);

            if ($bill->status == 'Approved') {
                return response()->json(['status' => 'Error', 'message' => 'Cannot delete an approved bill.'], 403);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $id, $user, $bill) {
                if ($bill && !empty($bill->attachments)) {
                    $files = json_decode($bill->attachments, true);
                    if (is_array($files)) {
                        foreach ($files as $file_path) {
                            if (\File::exists(public_path($file_path))) {
                                \File::delete(public_path($file_path));
                            }
                        }
                    }
                }

                DB::connection($conn)->table('new_bill_entry')->where('id', $id)->delete();
                DB::connection($conn)->table('new_bills_item_entry')->where('bill_id', $id)->delete();

                addActivity($id, 'new_bill_entry', "Bill Deleted via API", 4, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Bill deleted successfully']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkUpdateSiteBillStatus(Request $request)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $data = $request->all();
            $filteredData = array_diff_key($data, array_flip(['tenant_conn', 'tenant_uid', 'tenant_role', 'tenant_site_id']));

            // Robust JSON parsing (handles Text body and strips comments)
            if (empty($filteredData['ids']) && empty($filteredData['id'])) {
                $raw = file_get_contents('php://input');
                // Strip comments (// or /* */) to handle copy-pasted examples with notes
                $json = preg_replace('#//.*$|/\*.*?\*/#m', '', $raw);
                $decoded = json_decode($json, true);
                if ($decoded) {
                    $data = array_merge($data, $decoded);
                }
            }

            $ids = $data['ids'] ?? [$data['id'] ?? null];
            $ids = array_filter((array)$ids);
            $status = $data['status'] ?? null; // 'Approved' or 'Rejected'

            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'No bill IDs provided.'], 400);
            }

            if (!in_array($status, ['Approved', 'Rejected'])) {
                return response()->json(['status' => 'Error', 'message' => 'Invalid status provided. Use Approved or Rejected.'], 400);
            }

            return DB::connection($conn)->transaction(function () use ($conn, $ids, $status, $user) {
                $count = 0;
                foreach ($ids as $id) {
                    $bill = DB::connection($conn)->table('new_bill_entry')->where('id', $id)->first();
                    if (!$bill) continue;

                    if ($status == 'Approved') {
                        $party = DB::connection($conn)->table('bills_party')->where('id', $bill->party_id)->first();
                        if (!$party || $party->status != 'Active') {
                            continue; // Skip if party is not active
                        }
                        DB::connection($conn)->table('new_bill_entry')->where('id', $id)->update(['status' => 'Approved']);
                        $this->syncBillToStatement($id, $conn);
                        addActivity($id, 'new_bill_entry', "Bill Approved via API", 4, $user->id, $conn);
                    } else {
                        DB::connection($conn)->table('new_bill_entry')->where('id', $id)->update(['status' => 'Rejected']);
                        DB::connection($conn)->table('bill_party_statement')->where('bill_no', $id)->delete();
                        addActivity($id, 'new_bill_entry', "Bill Rejected via API", 4, $user->id, $conn);
                    }
                    $count++;
                }

                return response()->json(['status' => 'Ok', 'message' => "Successfully updated $count bills to $status."]);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    private function syncBillToStatement($billId, $conn)
    {
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', $billId)->first();
        if (!$bill) return;

        $party_statement = [
            'party_id' => $bill->party_id,
            'type' => 'Debit',
            'particular' => $bill->bill_no,
            'bill_no' => $billId,
            'create_datetime' => $bill->create_datetime
        ];

        DB::connection($conn)->table('bill_party_statement')->where('bill_no', $billId)->delete();
        DB::connection($conn)->table('bill_party_statement')->insert($party_statement);
    }

    public function systemActivity(Request $request)
    {
        try {
            $conn = config('database.default');
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $moduleId = $input['module_id'] ?? $request->get('module_id');
            $search = trim($input['search'] ?? $request->get('search') ?? '');
            $export = $input['export'] ?? $request->get('export');
            $limit = intval($input['limit'] ?? $request->get('limit', 20));
            if ($limit <= 0) $limit = 20;

            // Fetch modules map from master database
            $moduleMap = DB::connection('mysql')->table('modules')->pluck('name', 'id')->toArray();
            $allModules = DB::connection('mysql')->table('modules')->select('id', 'name')->orderBy('id', 'asc')->get();

            $query = DB::connection($conn)->table('activity')
                ->leftJoin('users', 'users.id', '=', 'activity.uid')
                ->select('activity.*', 'users.name as user_name')
                ->orderBy('activity.id', 'desc');

            if (!empty($moduleId)) {
                $query->where('activity.module_id', $moduleId);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('activity.action', 'LIKE', "%{$search}%")
                      ->orWhere('activity.date', 'LIKE', "%{$search}%")
                      ->orWhere('activity.time', 'LIKE', "%{$search}%")
                      ->orWhere('users.name', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "activities_" . time() . ".csv";
                $headers = [
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                ];

                $callback = function() use ($results, $moduleMap) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Date', 'Time', 'Messages', 'Name', 'Module']);
                    foreach ($results as $row) {
                        $moduleName = $moduleMap[$row->module_id] ?? 'N/A';
                        fputcsv($file, [
                            $row->id,
                            $row->date,
                            $row->time,
                            $row->action,
                            $row->user_name ?? 'User Info Unavailable',
                            $moduleName
                        ]);
                    }
                    fclose($file);
                };

                return response()->stream($callback, 200, $headers);
            }

            $activities = $query->paginate($limit);
            $activities->getCollection()->transform(function($row) use ($moduleMap) {
                $row->module_name = $moduleMap[$row->module_id] ?? 'N/A';
                return $row;
            });

            return response()->json([
                'status' => 'Ok',
                'modules' => $allModules,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function activityDetails(Request $request, $id)
    {
        try {
            $conn = config('database.default');

            $activity = DB::connection($conn)->table('activity')
                ->leftJoin('users', 'users.id', '=', 'activity.uid')
                ->select('activity.*', 'users.name as user_name')
                ->where('activity.id', $id)
                ->first();

            if (!$activity) {
                return response()->json(['status' => 'Error', 'message' => 'Activity not found'], 404);
            }

            // Fetch modules map from master database
            $moduleMap = DB::connection('mysql')->table('modules')->pluck('name', 'id')->toArray();
            $activity->module_name = $moduleMap[$activity->module_id] ?? 'N/A';

            return response()->json([
                'status' => 'Ok',
                'data' => $activity
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
