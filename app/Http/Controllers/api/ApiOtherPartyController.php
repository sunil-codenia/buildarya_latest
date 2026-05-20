<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiOtherPartyController extends Controller
{
    /**
     * Helper base query for other parties
     */
    private function getOtherPartiesQuery($conn)
    {
        return DB::connection($conn)->table('other_parties')
            ->leftJoin('expense_head', 'expense_head.id', '=', 'other_parties.cost_category_id')
            ->select('other_parties.*', 'expense_head.name as category_name');
    }

    /**
     * List other parties with search & optional CSV export
     */
    public function index(Request $request)
    {
        try {
            $conn = config('database.default');
            $query = $this->getOtherPartiesQuery($conn);

            $search = trim($request->get('search'));
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('other_parties.name', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.panno', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.address', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.bank_name', 'LIKE', "%{$search}%")
                      ->orWhere('other_parties.bank_ac', 'LIKE', "%{$search}%")
                      ->orWhere('expense_head.name', 'LIKE', "%{$search}%");
                });
            }

            $query->orderBy('other_parties.id', 'desc');

            // Handle CSV export
            if ($request->get('format') === 'csv' || $request->get('export') === 'csv' || $request->get('exprot') === 'csv') {
                return $this->exportCsv($query->get());
            }

            $perPage = intval($request->get('per_page', 20));
            $parties = $query->paginate($perPage);

            return response()->json([
                'status' => 'Ok',
                'data' => $parties,
                'applied_search' => $search
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get a single other party by ID
     */
    public function show(Request $request, $id)
    {
        try {
            $conn = config('database.default');
            $party = $this->getOtherPartiesQuery($conn)->where('other_parties.id', $id)->first();

            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Other Party not found'], 404);
            }

            if ($request->get('format') === 'csv' || $request->get('export') === 'csv' || $request->get('exprot') === 'csv') {
                return $this->exportCsv([$party], 'other_party_' . $id);
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
     * Create a new other party
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'cost_category_id' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $data = [
                'name' => $request->name,
                'panno' => $request->panno ?? $request->pan_no ?? "",
                'address' => $request->address ?? "",
                'bank_ac' => $request->bank_ac ?? "",
                'bank_ifsc' => $request->bank_ifsc ?? "",
                'bank_name' => $request->bank_name ?? "",
                'bank_ac_holder' => $request->bank_ac_holder ?? "",
                'cost_category_id' => $request->cost_category_id,
                'status' => $request->status ?? 'Active'
            ];

            $id = DB::connection($conn)->table('other_parties')->insertGetId($data);
            
            // Sync with contact_profile as web app does
            DB::connection($conn)->table('contact_profile')->insert([
                'comp_name' => $request->name, 
                'contact_name' => $request->name, 
                'category' => 'Other Party'
            ]);

            addActivity($id, 'other_parties', "New Other Party Created via API", 8, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Other Party created successfully',
                'id' => $id
            ], 201);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Party Already Exists!'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an other party by ID
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'cost_category_id' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $party = DB::connection($conn)->table('other_parties')->where('id', $id)->first();
            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Other Party not found'], 404);
            }

            $data = [
                'name' => $request->name,
                'panno' => $request->panno ?? $request->pan_no ?? $party->panno,
                'address' => $request->address ?? $party->address,
                'bank_ac' => $request->bank_ac ?? $party->bank_ac,
                'bank_ifsc' => $request->bank_ifsc ?? $party->bank_ifsc,
                'bank_name' => $request->bank_name ?? $party->bank_name,
                'bank_ac_holder' => $request->bank_ac_holder ?? $party->bank_ac_holder,
                'cost_category_id' => $request->cost_category_id,
                'status' => $request->status ?? $party->status
            ];

            DB::connection($conn)->table('other_parties')->where('id', $id)->update($data);
            addActivity($id, 'other_parties', "Other Party Data Updated via API", 8, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Other Party updated successfully'
            ]);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return response()->json(['status' => 'Error', 'message' => 'Party Already Exists!'], 400);
            }
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete other party by ID
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            $party = DB::connection($conn)->table('other_parties')->where('id', $id)->first();
            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Other Party not found'], 404);
            }

            // Check if in use
            $check = DB::connection($conn)->table('payment_vouchers')
                ->where('party_id', $id)
                ->where('party_type', 'other')
                ->first();

            if ($check) {
                return response()->json(['status' => 'Error', 'message' => 'Party Is In Use!'], 400);
            }

            DB::connection($conn)->table('other_parties')->where('id', $id)->delete();
            addActivity(0, 'other_parties', "Other Party Deleted via API - " . $party->name, 8, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => 'Other Party deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Activate/Deactivate Other Party status
     */
    public function toggleStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:Active,Inactive'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $party = DB::connection($conn)->table('other_parties')->where('id', $id)->first();
            if (!$party) {
                return response()->json(['status' => 'Error', 'message' => 'Other Party not found'], 404);
            }

            $status = $request->status;
            DB::connection($conn)->table('other_parties')->where('id', $id)->update(['status' => $status]);
            addActivity(0, 'other_parties', "Other Party Status Updated To - " . $status . " via API", 8, $user->id, $conn);

            return response()->json([
                'status' => 'Ok',
                'message' => $status === 'Active' ? 'Party Activated!' : 'Party Deactivated!'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * CSV Export Helper for Other Parties
     */
    private function exportCsv($parties, $filenamePrefix = 'other_parties')
    {
        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename={$filenamePrefix}_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'ID', 'Name', 'Pan No', 'Address', 'Cost Category', 
            'Bank Name', 'Bank A/C', 'Bank IFSC', 'A/C Holder Name', 'Status'
        ];

        $callback = function() use($parties, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($parties as $p) {
                fputcsv($file, [
                    $p->id,
                    $p->name,
                    $p->panno,
                    $p->address,
                    $p->category_name,
                    $p->bank_name,
                    $p->bank_ac,
                    $p->bank_ifsc,
                    $p->bank_ac_holder,
                    $p->status
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
