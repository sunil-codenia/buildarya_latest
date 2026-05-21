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
     * Helper to build the base query for payment vouchers with Joins to resolve related names.
     */
    private function getVouchersQuery($conn)
    {
        return DB::connection($conn)->table('payment_vouchers as pv')
            ->leftJoin('sales_company as sc', 'sc.id', '=', 'pv.company_id')
            ->leftJoin('sites as vs', 'vs.id', '=', 'pv.site_id')
            ->leftJoin('users as cu', 'cu.id', '=', 'pv.created_by')
            ->leftJoin('users as au', 'au.id', '=', 'pv.approved_by')
            ->leftJoin('users as pu', 'pu.id', '=', 'pv.paid_by')
            ->leftJoin('bills_party', function ($join) {
                $join->on('pv.party_id', '=', 'bills_party.id')
                    ->where('pv.party_type', '=', 'bill');
            })
            ->leftJoin('material_supplier', function ($join) {
                $join->on('pv.party_id', '=', 'material_supplier.id')
                    ->where('pv.party_type', '=', 'material');
            })
            ->leftJoin('sites as ps', function ($join) {
                $join->on('pv.party_id', '=', 'ps.id')
                    ->where('pv.party_type', '=', 'site');
            })
            ->leftJoin('other_parties', function ($join) {
                $join->on('pv.party_id', '=', 'other_parties.id')
                    ->where('pv.party_type', '=', 'other');
            })
            ->selectRaw('pv.*, sc.name as company_name, vs.name as site_name, cu.name as created_user, au.name as approved_user, pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name');
    }

    /**
     * Helper to stream payment voucher list as CSV.
     */
    private function exportCsv($vouchers, $filenamePrefix)
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$filenamePrefix}_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'ID', 'Voucher No', 'Site Name', 'Company Name', 'Party Type', 'Party Name', 
            'Amount', 'Date', 'Status', 'Payment Details', 'Payment Date', 'Remark', 
            'Created By User', 'Approved By User', 'Paid By User', 'Created At'
        ];

        $callback = function() use($vouchers, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($vouchers as $v) {
                fputcsv($file, [
                    $v->id,
                    $v->voucher_no,
                    $v->site_name,
                    $v->company_name,
                    $v->party_type,
                    $v->party_name,
                    $v->amount,
                    $v->date,
                    $v->status,
                    $v->payment_details,
                    $v->payment_date,
                    $v->remark,
                    $v->created_user,
                    $v->approved_user,
                    $v->paid_user,
                    $v->create_datetime
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * List Pending Payment Vouchers
     */
    public function listPending(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = $this->getVouchersQuery($conn)->where('pv.status', 'Pending');

            // Apply Search
            $search = trim($request->get('search'));
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('pv.voucher_no', 'LIKE', "%{$search}%")
                      ->orWhere('pv.payment_details', 'LIKE', "%{$search}%")
                      ->orWhere('pv.remark', 'LIKE', "%{$search}%")
                      ->orWhere('pv.amount', 'LIKE', "%{$search}%")
                      ->orWhere('sc.name', 'LIKE', "%{$search}%")
                      ->orWhere('vs.name', 'LIKE', "%{$search}%")
                      ->orWhere('bills_party.name', 'LIKE', "%{$search}%")
                      ->orWhere('material_supplier.name', 'LIKE', "%{$search}%")
                      ->orWhere('ps.name', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.name', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('pv.create_datetime', 'desc');

            // Check if CSV format is requested
            if ($request->get('format') === 'csv' || $request->get('export') === 'csv') {
                return $this->exportCsv($query->get(), 'pending_vouchers');
            }

            // Paginated Response
            $perPage = intval($request->get('per_page', 20));
            $vouchers = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $vouchers,
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Verified Payment Vouchers (Approved or Rejected)
     */
    public function listVerified(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = $this->getVouchersQuery($conn)->whereIn('pv.status', ['Approved', 'Rejected']);

            // Apply Search
            $search = trim($request->get('search'));
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('pv.voucher_no', 'LIKE', "%{$search}%")
                      ->orWhere('pv.payment_details', 'LIKE', "%{$search}%")
                      ->orWhere('pv.remark', 'LIKE', "%{$search}%")
                      ->orWhere('pv.amount', 'LIKE', "%{$search}%")
                      ->orWhere('sc.name', 'LIKE', "%{$search}%")
                      ->orWhere('vs.name', 'LIKE', "%{$search}%")
                      ->orWhere('bills_party.name', 'LIKE', "%{$search}%")
                      ->orWhere('material_supplier.name', 'LIKE', "%{$search}%")
                      ->orWhere('ps.name', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.name', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('pv.create_datetime', 'desc');

            // Check if CSV format is requested
            if ($request->get('format') === 'csv' || $request->get('export') === 'csv') {
                return $this->exportCsv($query->get(), 'verified_vouchers');
            }

            // Paginated Response
            $perPage = intval($request->get('per_page', 20));
            $vouchers = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $vouchers,
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * List Paid Payment Vouchers
     */
    public function listPaid(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = $this->getVouchersQuery($conn)->where('pv.status', 'Paid');

            // Apply Search
            $search = trim($request->get('search'));
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('pv.voucher_no', 'LIKE', "%{$search}%")
                      ->orWhere('pv.payment_details', 'LIKE', "%{$search}%")
                      ->orWhere('pv.remark', 'LIKE', "%{$search}%")
                      ->orWhere('pv.amount', 'LIKE', "%{$search}%")
                      ->orWhere('sc.name', 'LIKE', "%{$search}%")
                      ->orWhere('vs.name', 'LIKE', "%{$search}%")
                      ->orWhere('bills_party.name', 'LIKE', "%{$search}%")
                      ->orWhere('material_supplier.name', 'LIKE', "%{$search}%")
                      ->orWhere('ps.name', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.name', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('pv.create_datetime', 'desc');

            // Check if CSV format is requested
            if ($request->get('format') === 'csv' || $request->get('export') === 'csv') {
                return $this->exportCsv($query->get(), 'paid_vouchers');
            }

            // Paginated Response
            $perPage = intval($request->get('per_page', 20));
            $vouchers = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $vouchers,
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk Approve Pending Payment Vouchers
     */
    public function bulkApprove(Request $request, $id = null)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;
            
            if (empty($ids) && !empty($id)) {
                $ids = [$id];
            }
            
            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher IDs are required'], 400);
            }

            if (!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            $ids = array_map('trim', $ids);
            
            $approvedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                try {
                    $voucher = DB::connection($conn)->table('payment_vouchers')->where('id', $id)->first();
                    if (!$voucher) {
                        $errors[] = "Voucher ID {$id}: Not found";
                        continue;
                    }

                    if ($voucher->status !== 'Pending') {
                        $errors[] = "Voucher ID {$id} ({$voucher->voucher_no}): Status is not Pending (current: {$voucher->status})";
                        continue;
                    }

                    // Check if Party is Active
                    $party_status = null;
                    if ($voucher->party_type == 'bill') {
                        $party_status = DB::connection($conn)->table('bills_party')->where('id', $voucher->party_id)->first();
                    } else if ($voucher->party_type == 'material') {
                        $party_status = DB::connection($conn)->table('material_supplier')->where('id', $voucher->party_id)->first();
                    } else if ($voucher->party_type == 'other') {
                        $party_status = DB::connection($conn)->table('other_parties')->where('id', $voucher->party_id)->first();
                    } else if ($voucher->party_type == 'site') {
                        $party_status = DB::connection($conn)->table('sites')->where('id', $voucher->party_id)->first();
                    }

                    if ($party_status && $party_status->status !== 'Active') {
                        $errors[] = "Voucher ID {$id} ({$voucher->voucher_no}): Party '{$party_status->name}' is not Active";
                        continue;
                    }

                    // Approve the voucher
                    DB::connection($conn)->table('payment_vouchers')
                        ->where('id', $id)
                        ->update(['status' => 'Approved', 'approved_by' => $user->id]);

                    addActivity($id, 'payment_vouchers', "Payment Voucher Approved via Bulk API", 8, $user->id, $conn);
                    $approvedCount++;
                } catch (\Exception $ex) {
                    $errors[] = "Voucher ID {$id}: " . $ex->getMessage();
                }
            }

            return response()->json([
                'status' => count($errors) > 0 ? 'Partial' : 'Ok',
                'message' => "{$approvedCount} payment vouchers approved successfully.",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk Reject Pending Payment Vouchers
     */
    public function bulkReject(Request $request, $id = null)
    {
        try {
            $user = $request->user('sanctum');
            $conn = config('database.default');
            
            $input = json_decode($request->getContent(), true) ?? $request->all();
            $ids = $input['ids'] ?? null;
            
            if (empty($ids) && !empty($id)) {
                $ids = [$id];
            }
            
            if (empty($ids)) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher IDs are required'], 400);
            }

            if (!is_array($ids)) {
                $ids = explode(',', $ids);
            }
            $ids = array_map('trim', $ids);
            
            $rejectedCount = 0;
            $errors = [];

            foreach ($ids as $id) {
                try {
                    $voucher = DB::connection($conn)->table('payment_vouchers')->where('id', $id)->first();
                    if (!$voucher) {
                        $errors[] = "Voucher ID {$id}: Not found";
                        continue;
                    }

                    if ($voucher->status !== 'Pending') {
                        $errors[] = "Voucher ID {$id} ({$voucher->voucher_no}): Status is not Pending (current: {$voucher->status})";
                        continue;
                    }

                    // Reject the voucher
                    DB::connection($conn)->table('payment_vouchers')
                        ->where('id', $id)
                        ->update(['status' => 'Rejected', 'approved_by' => $user->id]);

                    addActivity($id, 'payment_vouchers', "Payment Voucher Rejected via Bulk API", 8, $user->id, $conn);
                    $rejectedCount++;
                } catch (\Exception $ex) {
                    $errors[] = "Voucher ID {$id}: " . $ex->getMessage();
                }
            }

            return response()->json([
                'status' => count($errors) > 0 ? 'Partial' : 'Ok',
                'message' => "{$rejectedCount} payment vouchers rejected successfully.",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle Store or Bulk Store dynamically
     */
    public function storeOrBulkStore(Request $request)
    {
        if ($request->has('site_id') && is_array($request->input('site_id'))) {
            return $this->bulkStore($request);
        }
        return $this->store($request);
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
            'voucher_no' => 'required',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            // Extract party_id and party_type
            $rawPartyId = $request->party_id;
            $partyId = $rawPartyId;
            $partyType = $request->party_type;

            if (is_string($rawPartyId) && str_contains($rawPartyId, '||')) {
                $parts = explode('||', $rawPartyId);
                $partyId = $parts[0];
                $partyType = $parts[1];
            }

            if (empty($partyType)) {
                $partyType = 'material'; // default fallback
            }

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
                'party_type' => $partyType,
                'party_id' => $partyId,
                'voucher_no' => $request->voucher_no,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_details' => $request->payment_details ?? "",
                'remark' => $request->remark ?? $request->remarks ?? "",
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

                    // Extract party_id and party_type
                    $rawPartyId = $request->party_id[$i] ?? null;
                    $partyId = $rawPartyId;
                    $partyType = $request->party_type[$i] ?? null;

                    if (is_string($rawPartyId) && str_contains($rawPartyId, '||')) {
                        $parts = explode('||', $rawPartyId);
                        $partyId = $parts[0];
                        $partyType = $parts[1];
                    }

                    if (empty($partyType)) {
                        $partyType = 'material'; // default fallback
                    }

                    $data = [
                        'company_id' => $request->company_id[$i] ?? null,
                        'site_id' => $request->site_id[$i] ?? null,
                        'party_type' => $partyType,
                        'party_id' => $partyId,
                        'voucher_no' => $request->voucher_no[$i] ?? null,
                        'amount' => $request->amount[$i] ?? null,
                        'date' => $request->date[$i] ?? null,
                        'payment_details' => $request->payment_details[$i] ?? "",
                        'remark' => $request->remark[$i] ?? $request->remarks[$i] ?? "",
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

    /**
     * Generate Voucher PDF
     */
    public function generateVoucherPdf(Request $request, $id = null)
    {
        try {
            $target_id = $id ?? $request->get('id');
            if (empty($target_id)) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher ID is required'], 400);
            }

            $conn = config('database.default');
            
            // Set session variables for helpers and blade compatibility in stateless API environment
            if (!session()->has('comp_db_conn_name')) {
                session()->put('comp_db_conn_name', $conn);
            }
            if (!session()->has('primary_color')) {
                session()->put('primary_color', ['#8c52ff']);
            }
            if (!session()->has('secondry_color')) {
                session()->put('secondry_color', ['#3f51b5']);
            }

            // Fetch payment voucher details
            $payment_vouchers = DB::select("SELECT `payment_vouchers`.*, `sales_company`.`name` as `company_name`, `sites`.`name` as `site_name` FROM `payment_vouchers` LEFT JOIN `sites` ON `payment_vouchers`.`site_id` = `sites`.`id` LEFT JOIN `sales_company` ON `payment_vouchers`.`company_id` = `sales_company`.`id` WHERE `payment_vouchers`.`id` = ?", [$target_id]);

            if (empty($payment_vouchers)) {
                return response()->json(['status' => 'Error', 'message' => 'Payment Voucher not found'], 404);
            }

            $payment_voucher = $payment_vouchers[0];
            $company = DB::table('sales_company')->where('id', $payment_voucher->company_id)->first();

            if (!$company) {
                $company = DB::table('sales_company')->first();
            }

            $file_name = $payment_voucher->voucher_no . ".pdf";
            
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('layouts.paymentvoucher.pdfs.pv_pdf', compact(['payment_voucher', 'company']));
            return $pdf->download($file_name);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Payment Voucher by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $voucher = $this->getVouchersQuery($conn)->where('pv.id', $id)->first();
            
            if (!$voucher) {
                return response()->json(['status' => 'Error', 'message' => 'Payment Voucher not found'], 404);
            }

            if ($request->get('format') === 'csv' || $request->get('export') === 'csv' || $request->get('exprot') === 'csv') {
                return $this->exportCsv([$voucher], 'voucher_' . $id);
            }
            
            return response()->json([
                'status' => 'Ok',
                'data' => $voucher
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update Payment Voucher by ID
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'site_id' => 'required',
            'company_id' => 'required',
            'party_id' => 'required',
            'voucher_no' => 'required',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $voucher = DB::table('payment_vouchers')->where('id', $id)->first();
            if (!$voucher) {
                return response()->json(['status' => 'Error', 'message' => 'Payment Voucher not found'], 404);
            }

            // Extract party_id and party_type
            $rawPartyId = $request->party_id;
            $partyId = $rawPartyId;
            $partyType = $request->party_type;

            if (is_string($rawPartyId) && str_contains($rawPartyId, '||')) {
                $parts = explode('||', $rawPartyId);
                $partyId = $parts[0];
                $partyType = $parts[1];
            }

            if (empty($partyType)) {
                $partyType = $voucher->party_type; // Fallback to current
            }

            $imagePath = $voucher->image;
            if ($request->hasFile('image')) {
                // Delete old image if it's not default
                if ($voucher->image && $voucher->image !== 'images/expense.png') {
                    $oldPath = public_path($voucher->image);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('image')->extension();
                $request->file('image')->move(public_path('images/app_images/' . $conn . '/paymentvoucher'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/paymentvoucher/" . $imageName;
            }

            $status = getInitialEntryStatusByRole($user->role_id);

            $data = [
                'company_id' => $request->company_id,
                'site_id' => $request->site_id,
                'party_type' => $partyType,
                'party_id' => $partyId,
                'voucher_no' => $request->voucher_no,
                'amount' => $request->amount,
                'date' => $request->date,
                'payment_details' => $request->payment_details ?? $voucher->payment_details ?? "",
                'remark' => $request->remark ?? $request->remarks ?? $voucher->remark ?? "",
                'image' => $imagePath,
                'status' => $status
            ];

            DB::table('payment_vouchers')->where('id', $id)->update($data);
            addActivity($id, 'payment_vouchers', "Payment Voucher Updated via API", 8, $user->id, $conn);

            // Auto-approval logic if status is Approved
            if ($status == 'Approved') {
                $this->handleAutoApproval($id, $conn, $user->id);
            }

            return response()->json(['status' => 'Ok', 'message' => 'Payment Voucher updated successfully']);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher No Already Exists!'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Pay/Complete Voucher Payment (Wallet API)
     */
    public function payVoucher(Request $request, $id)
    {
        $request->validate([
            'payment_details' => 'required|string',
            'payment_date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $paymentvoucher = DB::connection($conn)->table('payment_vouchers')->where('id', $id)->first();
            if (!$paymentvoucher) {
                return response()->json(['status' => 'Error', 'message' => 'Payment Voucher not found'], 404);
            }

            if ($paymentvoucher->status !== 'Approved') {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Only verified (Approved) payment vouchers can be paid. Current status is: ' . $paymentvoucher->status
                ], 400);
            }

            $party_type = $paymentvoucher->party_type;

            if ($request->hasFile('image')) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('image')->extension();
                $request->file('image')->move(public_path('images/app_images/' . $conn . '/paymentvoucher'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/paymentvoucher/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }

            // Update payment voucher status to Paid
            DB::connection($conn)->table('payment_vouchers')->where('id', '=', $id)->update([
                'status' => 'Paid', 
                'paid_by' => $user->id, 
                'payment_details' => $request->payment_details, 
                'payment_date' => $request->payment_date, 
                'payment_image' => $imagePath
            ]);

            addActivity($id, 'payment_vouchers', "Payment Vouchers Paid via API", 8, $user->id, $conn);

            // Statement Ledger Updates
            if ($party_type == 'site') {
                $tdata = [
                    'site_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($conn)->table('sites_transaction')->where('payment_voucher_id', $id)->delete();
                DB::connection($conn)->table('sites_transaction')->insert($tdata);
            } else if ($party_type == 'bill') {
                $tdata = [
                    'party_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'particular' => $request->payment_details,
                    'payment_voucher_id' => $id
                ];
                DB::connection($conn)->table('bill_party_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($conn)->table('bill_party_statement')->insert($tdata);
            } else if ($party_type == 'material') {
                $tdata = [
                    'supplier_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($conn)->table('material_supplier_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($conn)->table('material_supplier_statement')->insert($tdata);
            } else if ($party_type == 'other') {
                $tdata = [
                    'party_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($conn)->table('other_party_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($conn)->table('other_party_statement')->insert($tdata);
            }

            return response()->json([
                'status' => 'Ok',
                'message' => 'Payment Voucher Paid Successfully!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Error While Paying Payment Voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject an already Paid Payment Voucher (Reverse Payment)
     */
    public function rejectPaid(Request $request, $id = null)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            // Allow ID either via route parameter or via request body/query
            $targetId = $id ?? $request->get('id');
            
            if (!$targetId) {
                return response()->json(['status' => 'Error', 'message' => 'Voucher ID is required'], 400);
            }

            $paymentvoucher = DB::connection($conn)->table('payment_vouchers')->where('id', $targetId)->first();
            if (!$paymentvoucher) {
                return response()->json(['status' => 'Error', 'message' => 'Payment Voucher not found'], 404);
            }

            if ($paymentvoucher->status !== 'Paid') {
                return response()->json([
                    'status' => 'Error', 
                    'message' => 'Only Paid vouchers can be rejected/reversed using this endpoint. Current status is: ' . $paymentvoucher->status
                ], 400);
            }

            $party_type = $paymentvoucher->party_type;

            // Delete payment receipt image if exists and not default
            $image_path = $paymentvoucher->payment_image;
            if ($image_path && $image_path !== 'images/expense.png') {
                $absolutePath = public_path($image_path);
                if (File::exists($absolutePath)) {
                    File::delete($absolutePath);
                }
            }

            // Remove transaction statement entries
            if ($party_type == 'site') {
                DB::connection($conn)->table('sites_transaction')->where('payment_voucher_id', $targetId)->delete();
            } else if ($party_type == 'bill') {
                DB::connection($conn)->table('bill_party_statement')->where('payment_voucher_id', $targetId)->delete();
            } else if ($party_type == 'material') {
                DB::connection($conn)->table('material_supplier_statement')->where('payment_voucher_id', $targetId)->delete();
            } else if ($party_type == 'other') {
                DB::connection($conn)->table('other_party_statement')->where('payment_voucher_id', $targetId)->delete();
            }

            // Update status to Rejected
            DB::connection($conn)->table('payment_vouchers')->where('id', '=', $targetId)->update([
                'status' => 'Rejected', 
                'approved_by' => $user->id,
                'paid_by' => null,
                'payment_details' => null,
                'payment_date' => null,
                'payment_image' => null
            ]);

            addActivity($targetId, 'payment_vouchers', "Already Paid Payment Vouchers Rejected via API", 8, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Paid Payment Voucher successfully rejected and payment reversed!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Error while rejecting paid payment voucher: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Payment Voucher Report (All Scenarios in CSV / JSON)
     */
    public function generateReport(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date'
        ]);

        try {
            $conn = config('database.default');
            $query = $this->getVouchersQuery($conn);

            $start_date = $request->start_date;
            $end_date = $request->end_date;

            // Apply Date Range
            $query->whereBetween('pv.date', [$start_date, $end_date]);

            // Scenario Detection based on inputs:
            // 1 = Date Only, 2 = Party Only, 3 = Site Only, 4 = Both
            $report_type = 1;
            
            $party_id = $request->get('party_id');
            $site_id = $request->get('site_id');

            $partySelected = !empty($party_id);
            $siteSelected = !empty($site_id);

            if ($partySelected && $siteSelected) {
                $report_type = 4;
            } else if ($partySelected) {
                $report_type = 2;
            } else if ($siteSelected) {
                $report_type = 3;
            }

            // Apply filters based on detected scenario
            if ($report_type == 2 || $report_type == 4) {
                $partyname = $party_id;
                $partytype = "";
                
                // Handle optional id||type formatting
                if (strpos($party_id, '||') !== false) {
                    $parts = explode('||', $party_id);
                    $partyname = $parts[0];
                    $partytype = $parts[1];
                } else {
                    $partytype = $request->get('party_type');
                }

                if (!empty($partyname)) {
                    $query->where('pv.party_id', $partyname);
                }
                if (!empty($partytype)) {
                    $query->where('pv.party_type', $partytype);
                }
            }

            if ($report_type == 3 || $report_type == 4) {
                $query->where('pv.site_id', $site_id);
            }

            $query->orderBy('pv.date', 'desc');

            $vouchers = $query->get();

            // Always stream/download as CSV when format=csv or export=csv is specified
            if ($request->get('format') === 'csv' || $request->get('export') === 'csv' || $request->get('exprot') === 'csv') {
                $prefix = 'payment_report_type_' . $report_type;
                return $this->exportCsv($vouchers, $prefix);
            }

            return response()->json([
                'status' => 'Ok',
                'detected_scenario' => $report_type,
                'data' => $vouchers
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
