<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiExpenseController extends Controller
{
    /**
     * Get Expense Summary (Heads, Parties, Sites) for Dropdowns
     */
    public function summary(Request $request)
    {
        try {
            $conn = config('database.default');
            $data = [
                'expense_heads' => DB::table('expense_head')->get(),
                'expense_parties' => DB::table('expense_party')->where('status', 'Active')->get(),
                'bill_parties' => DB::table('bills_party')->where('status', 'Active')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
            ];

            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Expenses
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $site_id = $request->get('site_id', $user->site_id);
            $search = $request->get('search');
            $status = $request->get('status'); // New: filter by Pending, Verified, Returned, Approved
            $per_page = $request->get('per_page', 20); // New: dynamic pagination
            
            // Standard filters based on project role logic
            $role_id = $user->role_id;
            $role_details = DB::table('roles')->where('id', $role_id)->first();
            $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';
            
            $view_duration = getUserViewDuration($user);
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
            
            $query = DB::table('expenses')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expense_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'expense');
                })
                ->leftJoin('bills_party', function ($join) {
                    $join->on('bills_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'bill');
                })
                ->select(
                    'expenses.*', 
                    'sites.name as site_name', 
                    'users.name as user_name', 
                    'expense_head.name as head_name',
                    DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END AS party_name')
                );

            if (empty($search)) {
                $query->whereBetween('expenses.create_datetime', [$min_date, $max_date]);
            }

            $query->orderBy('expenses.date', 'desc')
                ->orderBy('expenses.id', 'desc');

            if ($visiblity_at_site == 'current' && $site_id && $site_id != 'all') {
                if (is_array($site_id)) {
                    $query->whereIn('expenses.site_id', $site_id);
                } else if (strpos((string)$site_id, ',') !== false) {
                    $query->whereIn('expenses.site_id', explode(',', (string)$site_id));
                } else {
                    $query->where('expenses.site_id', $site_id);
                }
            }

            if ($status) {
                if (strpos($status, ',') !== false) {
                    $query->whereIn('expenses.status', explode(',', $status));
                } else {
                    $query->where('expenses.status', $status);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('expenses.particular', 'like', "%$search%")
                      ->orWhere('expenses.amount', 'like', "%$search%")
                      ->orWhere('expenses.date', 'like', "%$search%")
                      ->orWhere('expenses.create_datetime', 'like', "%$search%")
                      ->orWhere('expense_head.name', 'like', "%$search%")
                      ->orWhere('expense_party.name', 'like', "%$search%")
                      ->orWhere('bills_party.name', 'like', "%$search%");

                    try {
                        if (strtotime($search) !== false) {
                            $parsedDate = date('Y-m-d', strtotime($search));
                            if ($parsedDate && $parsedDate !== '1970-01-01') {
                                $q->orWhere('expenses.date', 'like', "%$parsedDate%");
                            }
                        }
                    } catch (\Exception $e) {}
                });
            }

            $expenses = $query->paginate($per_page);

            return response()->json(['status' => 'Ok', 'data' => $expenses]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Export Expenses to CSV
     */
    public function export(Request $request)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $site_id = $request->get('site_id', $user->site_id);
            $search = $request->get('search');
            $status = $request->get('status'); 
            
            // Standard filters based on project role logic
            $role_id = $user->role_id;
            $role_details = DB::table('roles')->where('id', $role_id)->first();
            $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';
            
            $query = DB::table('expenses')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expense_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'expense');
                })
                ->leftJoin('bills_party', function ($join) {
                    $join->on('bills_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'bill');
                })
                ->select(
                    'expenses.date', 
                    'expenses.particular', 
                    'expense_head.name as head_name',
                    DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END AS party_name'),
                    'expenses.amount', 
                    'expenses.remark', 
                    'expenses.status',
                    'sites.name as site_name', 
                    'users.name as user_name'
                )
                ->orderBy('expenses.date', 'desc')
                ->orderBy('expenses.id', 'desc');

            if ($visiblity_at_site == 'current' && $site_id && $site_id != 'all') {
                if (is_array($site_id)) {
                    $query->whereIn('expenses.site_id', $site_id);
                } else if (strpos((string)$site_id, ',') !== false) {
                    $query->whereIn('expenses.site_id', explode(',', (string)$site_id));
                } else {
                    $query->where('expenses.site_id', $site_id);
                }
            }

            if ($status) {
                if (strpos($status, ',') !== false) {
                    $query->whereIn('expenses.status', explode(',', $status));
                } else {
                    $query->where('expenses.status', $status);
                }
            } else {
                // Default to Pending, Returned, Verified as requested
                $query->whereIn('expenses.status', ['Pending', 'Returned', 'Verified']);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('expenses.particular', 'like', "%$search%")
                      ->orWhere('expenses.amount', 'like', "%$search%")
                      ->orWhere('expenses.date', 'like', "%$search%")
                      ->orWhere('expenses.create_datetime', 'like', "%$search%")
                      ->orWhere('expense_head.name', 'like', "%$search%")
                      ->orWhere('expense_party.name', 'like', "%$search%")
                      ->orWhere('bills_party.name', 'like', "%$search%");

                    try {
                        if (strtotime($search) !== false) {
                            $parsedDate = date('Y-m-d', strtotime($search));
                            if ($parsedDate && $parsedDate !== '1970-01-01') {
                                $q->orWhere('expenses.date', 'like', "%$parsedDate%");
                            }
                        }
                    } catch (\Exception $e) {}
                });
            }

            $expenses = $query->get();

            $filename = "expenses_export_" . date('Y-m-d_H-i-s') . ".csv";
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];

            $callback = function() use($expenses) {
                $file = fopen('php://output', 'w');
                fputcsv($file, ['Date', 'Particular', 'Head', 'Party', 'Amount', 'Remark', 'Status', 'Site', 'User']);

                foreach ($expenses as $expense) {
                    fputcsv($file, [
                        $expense->date,
                        $expense->particular,
                        $expense->head_name,
                        $expense->party_name,
                        $expense->amount,
                        $expense->remark,
                        $expense->status,
                        $expense->site_name,
                        $expense->user_name,
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
     * Expense Report API
     */
    public function report(Request $request)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $report_code = $request->get('report_code', $request->get('type', 1));
            $start_date = $request->get('start_date', $request->get('from_date'));
            $end_date = $request->get('end_date', $request->get('to_date'));
            $site_id = $request->get('site_id');
            $party_id_raw = $request->get('party_id');
            $head_id = $request->get('head_id');
            $export = $request->get('export');

            $query = DB::table('expenses')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expense_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'expense');
                })
                ->leftJoin('bills_party', function ($join) {
                    $join->on('bills_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'bill');
                })
                ->select(
                    'expenses.*', 
                    'sites.name as site_name', 
                    'users.name as user_name', 
                    'expense_head.name as head_name',
                    DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END AS party_name')
                );

            // Apply Date Range
            if ($start_date && $end_date) {
                $query->whereBetween('expenses.date', [$start_date, $end_date]);
            }

            // Apply Filters based on report_code
            if (in_array($report_code, [2, 4, 6])) {
                if ($site_id) $query->where('expenses.site_id', $site_id);
            }

            if (in_array($report_code, [3, 4])) {
                if ($party_id_raw) {
                    $party = explode("||", $party_id_raw);
                    $query->where('expenses.party_id', $party[0])
                          ->where('expenses.party_type', $party[1] ?? 'expense');
                }
            }

            if (in_array($report_code, [5, 6])) {
                if ($head_id) $query->where('expenses.head_id', $head_id);
            }

            $data = $query->orderBy('expenses.date', 'desc')->get();

            if ($export == 'csv') {
                $filename = "expense_report_" . date('Y-m-d') . ".csv";
                $headers = [
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                ];

                $callback = function() use ($data) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Date', 'Party', 'Head', 'Particular', 'Amount', 'Site', 'User', 'Status', 'Remark']);

                    foreach ($data as $e) {
                        fputcsv($file, [$e->id, $e->date, $e->party_name, $e->head_name, $e->particular, $e->amount, $e->site_name, $e->user_name, $e->status, $e->remark]);
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

    /**
     * Get single Expense Details
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $expense = DB::table('expenses')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expense_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'expense');
                })
                ->leftJoin('bills_party', function ($join) {
                    $join->on('bills_party.id', '=', 'expenses.party_id')
                         ->where('expenses.party_type', '=', 'bill');
                })
                ->select(
                    'expenses.*', 
                    'sites.name as site_name', 
                    'users.name as user_name', 
                    'expense_head.name as head_name',
                    DB::raw('CASE WHEN expenses.party_type = "bill" THEN bills_party.name ELSE expense_party.name END AS party_name'),
                    DB::raw('CONCAT(expenses.party_id, "||", expenses.party_type) as party_id_with_type')
                )
                ->where('expenses.id', $id)
                ->first();

            if (!$expense) {
                return response()->json(['status' => 'Failed', 'message' => 'Expense not found'], 404);
            }

            return response()->json(['status' => 'Ok', 'data' => $expense]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store Expense
     */
    public function store(Request $request)
    {
        $request->validate([
            'site_id' => 'required',
            'amount' => 'required|numeric',
            'head_id' => 'required',
            'date' => 'required|date',
            'party_id' => 'required',
            'party_type' => 'required|in:bill,expense'
        ]);

        try {
            $conn = config('database.default');
            $user = $request->user();

            $add_duration = getUserAddDuration($user);
            $duration = getdurationdates($add_duration);
            $min_date = $duration['min'];
            $max_date = substr($duration['today'], 0, 10);
            if (strtotime($request->date) < strtotime($min_date) || strtotime($request->date) > strtotime($max_date)) {
                return response()->json(['status' => 'Failed', 'message' => "You don't have permission to add entry for date: " . $request->date], 403);
            }
            
            $imagePath = "images/expense.png";
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/expense'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/expense/" . $imageName;
            }

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);
            if (is_machinery_head($request->head_id, $conn) || is_asset_head($request->head_id, $conn)) {
                $status = 'Pending';
            }

            $data = [
                'site_id' => $request->site_id,
                'user_id' => $user->id,
                'party_id' => $request->party_id,
                'party_type' => $request->party_type,
                'head_id' => $request->head_id,
                'particular' => $request->particular,
                'amount' => $request->amount,
                'remark' => $request->remark,
                'image' => $imagePath,
                'location' => $request->location,
                'status' => $status,
                'date' => $request->date,
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('expenses')->insertGetId($data);
            addActivity($id, 'expenses', "New Expense Created via API Of Amount - " . $request->amount, 2, $user->id, $conn);

            return response()->json([
                'status' => 'Ok', 
                'message' => 'Expense created successfully', 
                'id' => $id, 
                'image' => $imagePath
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Expense
     */
    public function update(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $expense = DB::table('expenses')->where('id', $id)->first();

            if (!$expense) {
                return response()->json(['status' => 'Failed', 'message' => 'Expense not found'], 404);
            }

            if ($request->has('date')) {
                $add_duration = getUserAddDuration($user);
                $duration = getdurationdates($add_duration);
                $min_date = $duration['min'];
                $max_date = substr($duration['today'], 0, 10);
                if (strtotime($request->date) < strtotime($min_date) || strtotime($request->date) > strtotime($max_date)) {
                    return response()->json(['status' => 'Failed', 'message' => "You don't have permission to edit entry for date: " . $request->date], 403);
                }
            }

            $updateData = $request->only(['site_id', 'party_id', 'party_type', 'head_id', 'particular', 'amount', 'remark', 'location', 'date']);
            
            if ($request->hasFile('image')) {
                // Delete old image
                if (File::exists(public_path($expense->image)) && $expense->image != 'images/expense.png') {
                    File::delete(public_path($expense->image));
                }
                $file = $request->file('image');
                $imageName = time() . rand(10000, 1000000) . '.' . $file->extension();
                $file->move(public_path('images/app_images/' . $conn . '/expense'), $imageName);
                $updateData['image'] = "images/app_images/" . $conn . "/expense/" . $imageName;
            }

            DB::table('expenses')->where('id', $id)->update($updateData);
            addActivity($id, 'expenses', "Expense Updated via API", 2, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense updated successfully']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Expense
     */
    public function destroy(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $user = $request->user();
            $expense = DB::table('expenses')->where('id', $id)->first();

            if (!$expense) {
                return response()->json(['status' => 'Failed', 'message' => 'Expense not found'], 404);
            }

            // Optional: check permissions
            if ($expense->status == 'Approved') {
                return response()->json(['status' => 'Failed', 'message' => 'Cannot delete an approved expense.'], 403);
            }

            // Delete image
            if (File::exists(public_path($expense->image)) && $expense->image != 'images/expense.png') {
                File::delete(public_path($expense->image));
            }

            DB::table('expenses')->where('id', $id)->delete();
            addActivity($id, 'expenses', "Expense Deleted via API", 2, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Expense deleted successfully']);

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
