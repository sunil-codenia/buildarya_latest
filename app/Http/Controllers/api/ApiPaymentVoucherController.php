<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiPaymentVoucherController extends Controller
{
    /**
     * List Payment Vouchers with filters
     */
    public function index(Request $request)
    {
        try {
            $status = $request->get('status'); // Pending, Approved, Paid, Rejected
            $site_id = $request->get('site_id');

            $query = DB::table('payment_vouchers')
                ->leftJoin('sites', 'sites.id', '=', 'payment_vouchers.site_id')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'payment_vouchers.company_id')
                ->select('payment_vouchers.*', 'sites.name as site_name', 'sales_company.name as company_name')
                ->orderBy('payment_vouchers.id', 'desc');

            if ($status) $query->where('payment_vouchers.status', $status);
            if ($site_id) $query->where('payment_vouchers.site_id', $site_id);

            $vouchers = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $vouchers]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Store New Payment Voucher
     */
    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required',
            'site_id' => 'required',
            'party_type' => 'required', // bill, material, other, site
            'party_id' => 'required',
            'voucher_no' => 'required|unique:payment_vouchers,voucher_no',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $imagePath = "images/expense.png";
            if ($request->hasFile('image')) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('image')->extension();
                $request->file('image')->move(public_path('images/app_images/' . $conn . '/paymentvoucher'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/paymentvoucher/" . $imageName;
            }

            $status = getAppInitialEntryStatusByRole($user->role_id, $conn);

            $data = [
                'company_id' => $request->company_id,
                'site_id' => $request->site_id,
                'party_type' => $request->party_type,
                'party_id' => $request->party_id,
                'voucher_no' => $request->voucher_no,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_details' => $request->payment_details,
                'remark' => $request->remark,
                'created_by' => $user->id,
                'image' => $imagePath,
                'status' => $status,
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('payment_vouchers')->insertGetId($data);
            addActivity($id, 'payment_vouchers', "New Payment Voucher Created via API", 8, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Voucher created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Voucher Status (Approve/Reject)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            $status = $request->input('status'); // Approved, Rejected

            if (!in_array($status, ['Approved', 'Rejected'])) {
                return response()->json(['status' => 'Failed', 'message' => 'Invalid status'], 400);
            }

            DB::table('payment_vouchers')->where('id', $id)->update([
                'status' => $status,
                'approved_by' => $user->id
            ]);

            addActivity($id, 'payment_vouchers', "Payment Voucher updated to $status via API", 8, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => "Voucher $status successfully"]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add Side Balance Credit (Manual)
     */
    public function creditSiteBalance(Request $request)
    {
        $request->validate([
            'site_id' => 'required',
            'amount' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            return DB::transaction(function() use ($request, $user, $conn) {
                $pay_id = DB::table('site_payments')->insertGetId([
                    'site_id' => $request->site_id,
                    'amount' => $request->amount,
                    'remark' => $request->remark,
                    'create_datetime' => Carbon::now()
                ]);

                DB::table('sites_transaction')->insert([
                    'site_id' => $request->site_id,
                    'type' => 'Credit',
                    'payment_id' => $pay_id,
                    'create_datetime' => Carbon::now()
                ]);

                addActivity($pay_id, 'site_payments', "Site Balance Credit of " . $request->amount . " via API", 1, $user->id, $conn);
                return response()->json(['status' => 'Ok', 'message' => 'Site balance credited successfully']);
            });
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
