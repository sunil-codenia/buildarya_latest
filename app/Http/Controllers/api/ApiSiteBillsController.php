<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiSiteBillsController extends Controller
{
    /**
     * Get Site Bills Summary (Parties, Work Categories, Sites)
     */
    public function summary(Request $request)
    {
        try {
            $conn = config('database.default');
            $data = [
                'bill_parties' => DB::table('bills_party')->where('status', 'Active')->get(),
                'work_categories' => DB::table('bills_work')->get(),
                'sites' => DB::table('sites')->where('status', 'Active')->get(),
                'units' => DB::table('units')->get(),
            ];

            return response()->json(['status' => 'Ok', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Site Bills
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $site_id = $request->get('site_id', $user->site_id);
            $status = $request->get('status'); // Optional: Approved, Pending, Rejected
            
            $query = DB::table('new_bill_entry')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                ->select('new_bill_entry.*', 'sites.name as site_name', 'users.name as user_name', 'bills_party.name as party_name')
                ->orderBy('new_bill_entry.create_datetime', 'desc');

            if ($site_id && $site_id != 'all') {
                $query->where('new_bill_entry.site_id', $site_id);
            }

            if ($status) {
                $query->where('new_bill_entry.status', $status);
            }

            $bills = $query->paginate(20);

            return response()->json(['status' => 'Ok', 'data' => $bills]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Specific Bill Details (with items)
     */
    public function show($id)
    {
        try {
            $bill = DB::table('new_bill_entry')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                ->where('new_bill_entry.id', $id)
                ->select('new_bill_entry.*', 'sites.name as site_name', 'bills_party.name as party_name')
                ->first();

            if (!$bill) {
                return response()->json(['status' => 'Failed', 'message' => 'Bill not found'], 404);
            }

            $items = DB::table('new_bills_item_entry')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                ->where('bill_id', $id)
                ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                ->get();

            return response()->json(['status' => 'Ok', 'data' => ['bill' => $bill, 'items' => $items]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Bill
     */
    public function store(Request $request)
    {
        $request->validate([
            'party_id' => 'required',
            'site_id' => 'required',
            'bill_no' => 'required',
            'bill_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.work_id' => 'required',
            'items.*.rate' => 'required|numeric',
            'items.*.qty' => 'required|numeric',
            'items.*.unit' => 'required',
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $party = DB::table('bills_party')->where('id', $request->party_id)->first();
            if (!$party || $party->status != 'Active') {
                return response()->json(['status' => 'Failed', 'message' => 'Bill party is not active or not found.'], 403);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['rate'] * $item['qty']);
            }

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);
            $bill_period = $request->get('bill_from_date', '') . " to " . $request->get('bill_to_date', '');

            $billData = [
                'party_id' => $request->party_id,
                'bill_no' => $request->bill_no,
                'site_id' => $request->site_id,
                'billdate' => $request->bill_date,
                'bill_period' => $bill_period,
                'user_id' => $user->id,
                'status' => $status,
                'amount' => $totalAmount,
                'remark' => $request->remark,
                'create_datetime' => Carbon::now()
            ];

            return DB::transaction(function () use ($billData, $request, $id, $user, $conn, $status) {
                $billId = DB::table('new_bill_entry')->insertGetId($billData);
                addActivity($billId, 'new_bill_entry', "New Bill Created via API - " . $request->bill_no, 4, $user->id, $conn);

                $billItems = [];
                foreach ($request->items as $item) {
                    $billItems[] = [
                        'bill_id' => $billId,
                        'work_id' => $item['work_id'],
                        'unit' => $item['unit'],
                        'rate' => $item['rate'],
                        'qty' => $item['qty'],
                        'amount' => $item['rate'] * $item['qty']
                    ];
                }
                DB::table('new_bills_item_entry')->insert($billItems);

                if ($status == 'Approved') {
                    $this->approve_bill($billId, $conn);
                }

                return response()->json(['status' => 'Ok', 'message' => 'Bill created successfully', 'id' => $billId]);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Bill
     */
    public function update(Request $request, $id)
    {
        // Similar to store but uses Update logic
        $request->validate([
            'items' => 'required|array|min:1',
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $bill = DB::table('new_bill_entry')->where('id', $id)->first();

            if (!$bill) {
                return response()->json(['status' => 'Failed', 'message' => 'Bill not found'], 404);
            }

            if ($bill->status == 'Approved') {
                return response()->json(['status' => 'Failed', 'message' => 'Cannot update an approved bill.'], 403);
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['rate'] * $item['qty']);
            }

            $updateData = $request->only(['party_id', 'bill_no', 'site_id', 'billdate', 'remark']);
            $updateData['amount'] = $totalAmount;
            if ($request->has('bill_from_date') && $request->has('bill_to_date')) {
                $updateData['bill_period'] = $request->bill_from_date . " to " . $request->bill_to_date;
            }

            return DB::transaction(function () use ($id, $updateData, $request, $user, $conn) {
                DB::table('new_bill_entry')->where('id', $id)->update($updateData);
                addActivity($id, 'new_bill_entry', "Bill Updated via API", 4, $user->id, $conn);

                // Re-insert items
                DB::table('new_bills_item_entry')->where('bill_id', $id)->delete();
                $billItems = [];
                foreach ($request->items as $item) {
                    $billItems[] = [
                        'bill_id' => $id,
                        'work_id' => $item['work_id'],
                        'unit' => $item['unit'],
                        'rate' => $item['rate'],
                        'qty' => $item['qty'],
                        'amount' => $item['rate'] * $item['qty']
                    ];
                }
                DB::table('new_bills_item_entry')->insert($billItems);

                return response()->json(['status' => 'Ok', 'message' => 'Bill updated successfully']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete Bill
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $bill = DB::table('new_bill_entry')->where('id', $id)->first();

            if (!$bill) {
                return response()->json(['status' => 'Failed', 'message' => 'Bill not found'], 404);
            }

            if ($bill->status == 'Approved') {
                return response()->json(['status' => 'Failed', 'message' => 'Cannot delete an approved bill.'], 403);
            }

            return DB::transaction(function () use ($id, $user, $conn) {
                DB::table('new_bill_entry')->where('id', $id)->delete();
                DB::table('new_bills_item_entry')->where('bill_id', $id)->delete();
                addActivity($id, 'new_bill_entry', "Bill Deleted via API", 4, $user->id, $conn);

                return response()->json(['status' => 'Ok', 'message' => 'Bill deleted successfully']);
            });

        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve Bill logic (helper)
     */
    private function approve_bill($id, $conn)
    {
        $bill = DB::table('new_bill_entry')->where('id', $id)->first();
        DB::table('new_bill_entry')->where('id', $id)->update(['status' => 'Approved']);
        
        $party_statement = [
            'party_id' => $bill->party_id,
            'type' => 'Debit',
            'particular' => $bill->bill_no,
            'bill_no' => $id,
            'create_datetime' => $bill->create_datetime
        ];
        DB::table('bill_party_statement')->where('bill_no', $id)->delete();
        DB::table('bill_party_statement')->insert($party_statement);
    }
}
