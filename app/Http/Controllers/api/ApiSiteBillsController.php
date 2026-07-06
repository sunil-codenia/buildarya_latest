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
            
            $view_duration = getUserViewDuration($user);
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];

            $query = DB::table('new_bill_entry')
                ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                ->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                ->select('new_bill_entry.*', 'sites.name as site_name', 'users.name as user_name', 'bills_party.name as party_name')
                ->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])
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

            $add_duration = getUserAddDuration($user);
            $duration = getdurationdates($add_duration);
            $min_date = $duration['min'];
            $max_date = substr($duration['today'], 0, 10);
            if (strtotime($request->bill_date) < strtotime($min_date) || strtotime($request->bill_date) > strtotime($max_date)) {
                return response()->json(['status' => 'Failed', 'message' => "You don't have permission to add entry for date: " . $request->bill_date], 403);
            }
            
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

            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images/app_images/' . $conn . '/bill'), $fileName);
                    $attachments[] = 'images/app_images/' . $conn . '/bill/' . $fileName;
                }
            }

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
                'attachments' => count($attachments) > 0 ? json_encode($attachments) : null,
                'create_datetime' => Carbon::now()
            ];

            return DB::transaction(function () use ($billData, $request, $user, $conn, $status) {
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

            $bill_date = $request->bill_date ?? $request->billdate;
            if ($bill_date) {
                $add_duration = getUserAddDuration($user);
                $duration = getdurationdates($add_duration);
                $min_date = $duration['min'];
                $max_date = substr($duration['today'], 0, 10);
                if (strtotime($bill_date) < strtotime($min_date) || strtotime($bill_date) > strtotime($max_date)) {
                    return response()->json(['status' => 'Failed', 'message' => "You don't have permission to edit entry for date: " . $bill_date], 403);
                }
            }

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $totalAmount += ($item['rate'] * $item['qty']);
            }

            $existing = $request->input('existing_attachments');
            $attachments = [];
            if ($bill && !empty($bill->attachments)) {
                $oldAttachments = json_decode($bill->attachments, true);
                if (is_array($oldAttachments)) {
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
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                    $file->move(public_path('images/app_images/' . $conn . '/bill'), $fileName);
                    $attachments[] = 'images/app_images/' . $conn . '/bill/' . $fileName;
                }
            }

            $updateData = $request->only(['party_id', 'bill_no', 'site_id', 'billdate', 'remark']);
            $updateData['amount'] = $totalAmount;
            $updateData['attachments'] = count($attachments) > 0 ? json_encode($attachments) : null;
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

            return DB::transaction(function () use ($id, $user, $conn, $bill) {
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
     * Get Works/Items for a specific Site
     */
    public function getSiteWorks(Request $request)
    {
        try {
            $site_id = $request->get('site_id');
            if (!$site_id) {
                return response()->json(['status' => 'Failed', 'message' => 'site_id is required'], 400);
            }

            $works = DB::table('bills_rate')
                ->leftJoin('bills_work', 'bills_work.id', '=', 'bills_rate.work_id')
                ->where('bills_rate.site_id', $site_id)
                ->select('bills_work.id', 'bills_work.name', 'bills_work.unit', 'bills_rate.rate')
                ->get();

            return response()->json(['status' => 'Ok', 'data' => $works]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Generate Site Bill Report (JSON Response)
     */
    public function report(Request $request)
    {
        try {
            $report_code = $request->get('type');
            $start_date = $request->get('start_date');
            $end_date = $request->get('end_date');
            $site_id = $request->get('site_id');
            $party_id = $request->get('party_id');
            $work_id = $request->get('work_id');
            $conn = config('database.default');

            if ($report_code == 11) {
                // Party Statement Logic
                if (!$party_id) {
                    return response()->json(['status' => 'Failed', 'message' => 'party_id is required for statement'], 400);
                }

                $statement = DB::table('bill_party_statement')
                    ->where('party_id', $party_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $data = [];
                foreach ($statement as $statem) {
                    if ($statem->type == 'Credit') {
                        if (!is_null($statem->expense_id)) {
                            $expense = DB::table('expenses')->where('id', $statem->expense_id)->first();
                            if ($expense) {
                                $data[] = [
                                    'date' => $expense->date,
                                    'ref' => 'Expense',
                                    'ref_no' => '',
                                    'site_name' => getSiteDetailsById($expense->site_id)->name ?? '',
                                    'credit' => $expense->amount,
                                    'debit' => 0,
                                    'particular' => $statem->particular,
                                    'image' => $expense->image
                                ];
                            }
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::table('bill_party_payments')->where('id', $statem->payment_id)->first();
                            if ($payment) {
                                $data[] = [
                                    'date' => $payment->date,
                                    'ref' => 'Payment',
                                    'ref_no' => '',
                                    'site_name' => '',
                                    'credit' => $payment->amount,
                                    'debit' => 0,
                                    'particular' => $statem->particular,
                                    'image' => ''
                                ];
                            }
                        } else if (!is_null($statem->payment_voucher_id)) {
                            $pv = DB::table('payment_vouchers')->where('id', $statem->payment_voucher_id)->first();
                            if ($pv) {
                                $data[] = [
                                    'date' => $pv->date,
                                    'ref' => 'Payment Vouchers',
                                    'ref_no' => $pv->voucher_no,
                                    'site_name' => getSiteDetailsById($pv->site_id)->name ?? '',
                                    'credit' => $pv->amount,
                                    'debit' => 0,
                                    'particular' => $statem->particular,
                                    'image' => $pv->image
                                ];
                            }
                        }
                    } else {
                        if (!is_null($statem->bill_no)) {
                            $bill = DB::table('new_bill_entry')->where('id', $statem->bill_no)->first();
                            if ($bill) {
                                $data[] = [
                                    'date' => $bill->billdate,
                                    'ref' => 'Site Bill',
                                    'ref_no' => $bill->bill_no,
                                    'site_name' => getSiteDetailsById($bill->site_id)->name ?? '',
                                    'credit' => 0,
                                    'debit' => $bill->amount,
                                    'particular' => $statem->particular,
                                    'image' => ''
                                ];
                            }
                        }
                    }
                }

                return response()->json(['status' => 'Ok', 'data' => $data]);
            } else {
                // General Filtering for all other report types
                $query = DB::table('new_bill_entry')
                    ->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name');

                if ($start_date && $end_date) {
                    $query->whereBetween('new_bill_entry.billdate', [$start_date, $end_date]);
                }
                
                if ($site_id) {
                    $query->where('new_bill_entry.site_id', $site_id);
                }
                
                if ($party_id) {
                    $query->where('new_bill_entry.party_id', $party_id);
                }
                
                if ($work_id) {
                    $query->join('new_bills_item_entry', 'new_bill_entry.id', '=', 'new_bills_item_entry.bill_id');
                    $query->where('new_bills_item_entry.work_id', $work_id);
                    $query->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name');
                    $query->distinct();
                }

                $bills = $query->orderBy('new_bill_entry.billdate', 'desc')->get();

                // If detailed report requested, fetch items for each bill
                if (in_array($report_code, [2, 6, 8, 10, 12])) {
                    foreach ($bills as $bill) {
                        $bill->items = DB::table('new_bills_item_entry')
                            ->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                            ->where('bill_id', $bill->id)
                            ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                            ->get();
                    }
                }

                return response()->json(['status' => 'Ok', 'data' => $bills]);
            }
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
