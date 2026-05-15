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
     * List Pending Payment Vouchers
     */
    public function listPending(Request $request)
    {
        try {
            $user = $request->user();
            $query = DB::table('payment_vouchers')
                ->leftJoin('sites', 'sites.id', '=', 'payment_vouchers.site_id')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'payment_vouchers.company_id')
                ->select('payment_vouchers.*', 'sites.name as site_name', 'sales_company.name as company_name')
                ->where('payment_vouchers.status', 'Pending');

            $vouchers = $query->orderBy('payment_vouchers.create_datetime', 'desc')->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $vouchers]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add New Payment Voucher
     */
    public function store(Request $request)
    {
        $request->validate([
            'site_id' => 'required',
            'company_id' => 'required',
            'party_id' => 'required',
            'party_type' => 'required|in:bill,material,other,site',
            'voucher_no' => 'required',
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

            // Status logic from website
            $status = getInitialEntryStatusByRole($user->role_id);

            $data = [
                'company_id' => $request->company_id,
                'site_id' => $request->site_id,
                'party_type' => $request->party_type,
                'party_id' => $request->party_id,
                'voucher_no' => $request->voucher_no,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_details' => $request->payment_details ?? "",
                'remark' => $request->remark ?? "",
                'created_by' => $user->id,
                'image' => $imagePath,
                'status' => $status,
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('payment_vouchers')->insertGetId($data);
            addActivity($id, 'payment_vouchers', "New Payment Voucher Created via API", 8, $user->id, $conn);

            // Auto-approval logic if status is Approved
            if ($status == 'Approved') {
                $this->handleAutoApproval($id, $conn, $user->id);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Payment Voucher created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher No Already Exists!'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Add Multiple Payment Vouchers
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'site_id' => 'required|array',
            'company_id' => 'required|array',
            'party_id' => 'required|array',
            'party_type' => 'required|array',
            'voucher_no' => 'required|array',
            'amount' => 'required|array',
            'date' => 'required|array'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $status = getInitialEntryStatusByRole($user->role_id);
            $ids = [];
            $errors = [];

            $length = count($request->site_id);
            for ($i = 0; $i < $length; $i++) {
                try {
                    $imagePath = "images/expense.png";
                    if ($request->hasFile("image.$i")) {
                        $imageName = time() . rand(10000, 1000000) . '.' . $request->file("image.$i")->extension();
                        $request->file("image.$i")->move(public_path('images/app_images/' . $conn . '/paymentvoucher'), $imageName);
                        $imagePath = "images/app_images/" . $conn . "/paymentvoucher/" . $imageName;
                    }

                    $data = [
                        'company_id' => $request->company_id[$i] ?? null,
                        'site_id' => $request->site_id[$i] ?? null,
                        'party_type' => $request->party_type[$i] ?? null,
                        'party_id' => $request->party_id[$i] ?? null,
                        'voucher_no' => $request->voucher_no[$i] ?? null,
                        'amount' => $request->amount[$i] ?? null,
                        'date' => $request->date[$i] ?? null,
                        'payment_details' => $request->payment_details[$i] ?? "",
                        'remark' => $request->remark[$i] ?? "",
                        'created_by' => $user->id,
                        'image' => $imagePath,
                        'status' => $status,
                        'create_datetime' => Carbon::now()
                    ];

                    $id = DB::table('payment_vouchers')->insertGetId($data);
                    $ids[] = $id;
                    addActivity($id, 'payment_vouchers', "New Payment Voucher Created via Bulk API", 8, $user->id, $conn);

                    if ($status == 'Approved') {
                        $this->handleAutoApproval($id, $conn, $user->id);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row $i: " . ($e->getCode() == 23000 ? "Duplicate Voucher No" : $e->getMessage());
                }
            }

            return response()->json([
                'status' => count($errors) > 0 ? 'Partial' : 'Ok',
                'message' => count($ids) . ' vouchers created successfully',
                'ids' => $ids,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle auto-approval logic similar to website
     */
    private function handleAutoApproval($id, $conn, $userId)
    {
        $voucher = DB::table('payment_vouchers')->where('id', $id)->first();
        $party_status = null;

        if ($voucher->party_type == 'bill') {
            $party_status = DB::table('bills_party')->where('id', $voucher->party_id)->first();
        } else if ($voucher->party_type == 'material') {
            $party_status = DB::table('material_supplier')->where('id', $voucher->party_id)->first();
        } else if ($voucher->party_type == 'other') {
            $party_status = DB::table('other_parties')->where('id', $voucher->party_id)->first();
        } else if ($voucher->party_type == 'site') {
            $party_status = DB::table('sites')->where('id', $voucher->party_id)->first();
        }

        if ($party_status && $party_status->status == 'Active') {
            DB::table('payment_vouchers')->where('id', $id)->update(['status' => 'Approved', 'approved_by' => $userId]);
            addActivity($id, 'payment_vouchers', "Payment Voucher Approved Automatically via API", 8, $userId, $conn);
        }
    }
}
