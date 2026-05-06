<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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

                // Process Duration Ranges
                if ($request->has('view_from') || $request->has('view_to')) {
                    $data['view_duration'] = ($request->view_from ?? '') . ',' . ($request->view_to ?? '');
                }
                if ($request->has('add_from') || $request->has('add_to')) {
                    $data['add_duration'] = ($request->add_from ?? '') . ',' . ($request->add_to ?? '');
                }

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

            // Process Duration Ranges
            if ($request->has('view_from') || $request->has('view_to')) {
                $updateData['view_duration'] = ($request->view_from ?? '') . ',' . ($request->view_to ?? '');
            }
            if ($request->has('add_from') || $request->has('add_to')) {
                $updateData['add_duration'] = ($request->add_from ?? '') . ',' . ($request->add_to ?? '');
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
        // ... (existing method code)
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
                $entry_type = is_array($request->entry_type) ? $request->entry_type[$i] : $request->get('entry_type', 'Consumption');
                $reason = is_array($request->reason) ? $request->reason[$i] : $request->reason;

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

                $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';
                
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

                if ($entry_type == 'Wastage') {
                    $data['reason'] = $reason;
                }

                $id = DB::connection($conn)->table($table)->insertGetId($data);
                addActivity($id, $table, "New Entry Created via API (Bulk Support)", 3, $user->id, $conn);

                if ($status == 'Approved') {
                    if ($entry_type == 'Wastage') {
                        $this->adjustStockForWastage($id, $conn, 'approve');
                    } else {
                        $this->adjustStockForConsumption($id, $conn, 'approve');
                    }
                }

                $responses[] = ['id' => $id, 'type' => $entry_type, 'status' => 'Ok'];
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

    public function bulkApproveConsumption(Request $request)
    {
        try {
            $conn = config('database.default');
            $ids = $request->get('ids');
            if (!is_array($ids)) $ids = explode(',', $ids);
            $entry_type = $request->get('entry_type', 'Consumption'); // Default to Consumption

            $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';

            $user = $request->user();

            foreach ($ids as $id) {
                if (empty($id)) continue;
                if ($entry_type == 'Wastage') {
                    $this->adjustStockForWastage($id, $conn, 'approve');
                } else {
                    $this->adjustStockForConsumption($id, $conn, 'approve');
                }
                DB::connection($conn)->table($table)->where('id', $id)->update(['status' => 'Approved']);
                addActivity($id, $table, "Bulk Approved via API", 3, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Selected entries approved successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function bulkRejectConsumption(Request $request)
    {
        try {
            $conn = config('database.default');
            $ids = $request->get('ids');
            if (!is_array($ids)) $ids = explode(',', $ids);
            $entry_type = $request->get('entry_type', 'Consumption');

            $table = ($entry_type == 'Wastage') ? 'material_wastage' : 'material_consumption';

            $user = $request->user();

            foreach ($ids as $id) {
                if (empty($id)) continue;
                if ($entry_type == 'Wastage') {
                    $this->adjustStockForWastage($id, $conn, 'reject');
                } else {
                    $this->adjustStockForConsumption($id, $conn, 'reject');
                }
                DB::connection($conn)->table($table)->where('id', $id)->update(['status' => 'Rejected']);
                addActivity($id, $table, "Bulk Rejected via API", 3, $user->id, $conn);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Selected entries rejected successfully']);
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
        return $this->listConsumptionByStatus($request, 'Pending');
    }

    public function getVerifiedConsumption(Request $request)
    {
        return $this->listConsumptionByStatus($request, 'Verified');
    }

    private function listConsumptionByStatus(Request $request, $status)
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
            $queries = [$consumption_query, $wastage_query];
            foreach ($queries as $q) {
                if ($site_id) $q->where($q->from . '.site_id', $site_id);
                if ($material_id) $q->where($q->from . '.material_id', $material_id);
                if ($search) {
                    $q->where('sites.name', 'like', "%$search%");
                }
                if ($start_date && $end_date) {
                    $q->whereBetween($q->from . '.date', [$start_date, $end_date]);
                }
            }

            // Combine using Union
            $final_query = $consumption_query->union($wastage_query)->orderBy('date', 'desc')->orderBy('id', 'desc');
            
            $data = $final_query->paginate($per_page);

            return response()->json([
                'status' => 'Ok',
                'status_type' => $status,
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
            $from_date = $request->get('from_date', date('Y-m-01'));
            $to_date = $request->get('to_date', date('Y-m-t'));

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

            $query->whereBetween('material_entry.date', [$from_date, $to_date]);

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
            $from_date = $request->get('from_date', date('Y-m-01'));
            $to_date = $request->get('to_date', date('Y-m-t'));

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

            $query->whereBetween('material_entry.date', [$from_date, $to_date]);

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
}
