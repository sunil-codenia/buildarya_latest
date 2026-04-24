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
            
            // Standard filters based on project role logic
            $role_id = $user->role_id;
            $role_details = DB::table('roles')->where('id', $role_id)->first();
            $visiblity_at_site = $role_details->visiblity_at_site;
            
            $query = DB::table('expenses')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftJoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftJoin('users', 'users.id', '=', 'expenses.user_id')
                ->select('expenses.*', 'sites.name as site_name', 'users.name as user_name', 'expense_head.name as head_name')
                ->orderBy('expenses.create_datetime', 'desc');

            if ($visiblity_at_site == 'current' && $site_id && $site_id != 'all') {
                $query->where('expenses.site_id', $site_id);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('expenses.particular', 'like', "%$search%")
                      ->orWhere('expenses.amount', 'like', "%$search%")
                      ->orWhere('expense_head.name', 'like', "%$search%");
                });
            }

            $expenses = $query->paginate(20);

            return response()->json(['status' => 'Ok', 'data' => $expenses]);
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
