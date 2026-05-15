<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class ApiSalesController extends Controller
{
    // ==========================================
    // SALES PROJECTS
    // ==========================================

    public function listProjects(Request $request)
    {
        try {
            $search = trim($request->get('search'));
            $export = $request->get('export');
            $query = DB::table('sales_project')->orderBy('id', 'desc');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "sales_projects_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Details', 'Status', 'Created At']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->details, $row->status, $row->create_datetime]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $projects = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $projects]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeProject(Request $request)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate(['name' => 'required']);
        
        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $imagePath = "";
            if ($request->hasFile('attachment')) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('attachment')->extension();
                $request->file('attachment')->move(public_path('images/app_images/'.$conn.'/projects'), $imageName);
                $imagePath = "images/app_images/".$conn."/projects/" . $imageName;
            }

            $id = DB::table('sales_project')->insertGetId([
                'name' => $request->name,
                'details' => $request->details,
                'attachment' => $imagePath,
                'status' => 'Active',
                'create_datetime' => Carbon::now()
            ]);

            addActivity($id, 'sales_project', "New Sales Project Created via API: " . $request->name, 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Project created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function projectDetails(Request $request, $id)
    {
        try {
            $project = DB::table('sales_project')->where('id', $id)->first();
            if (!$project) return response()->json(['status' => 'Failed', 'message' => 'Project not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $project]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateProject(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate(['name' => 'required']);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $project = DB::table('sales_project')->where('id', $id)->first();
            
            if (!$project) return response()->json(['status' => 'Failed', 'message' => 'Project not found'], 404);

            $imagePath = $project->attachment;
            if ($request->hasFile('attachment')) {
                if (!empty($project->attachment) && File::exists(public_path($project->attachment))) {
                    File::delete(public_path($project->attachment));
                }
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('attachment')->extension();
                $request->file('attachment')->move(public_path('images/app_images/'.$conn.'/projects'), $imageName);
                $imagePath = "images/app_images/".$conn."/projects/" . $imageName;
            }

            DB::table('sales_project')->where('id', $id)->update([
                'name' => $request->name,
                'details' => $request->details,
                'attachment' => $imagePath
            ]);

            addActivity($id, 'sales_project', "Sales Project Updated via API", 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Project updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteProject(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            $check = DB::table('sales_invoice')->where('project_id', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'This Project Cannot Be Deleted. Project Has Invoices In Its Name!'], 400);
            }

            $project = DB::table('sales_project')->where('id', $id)->first();
            if (!$project) return response()->json(['status' => 'Failed', 'message' => 'Project not found'], 404);

            if (!empty($project->attachment) && File::exists(public_path($project->attachment))) {
                File::delete(public_path($project->attachment));
            }

            DB::table('sales_project')->where('id', $id)->delete();
            addActivity(0, 'sales_project', "Sales Project Deleted via API: " . $project->name, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Project deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateProjectStatus(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate(['status' => 'required|in:Active,Deactive']);

        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('sales_project')->where('id', $id)->update(['status' => $request->status]);
            addActivity($id, 'sales_project', "Sales Project Status Updated via API: " . $request->status, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Project status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SALES INVOICES
    // ==========================================

    public function listInvoices(Request $request)
    {
        try {
            $project_id = $request->get('project_id');
            $query = DB::table('sales_invoice')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')
                ->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')
                ->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')
                ->select('sales_invoice.*', 'sales_company.name as company_name', 'sales_party.name as party_name', 'sales_project.name as project_name')
                ->orderBy('sales_invoice.id', 'desc');

            if ($project_id) {
                $query->where('sales_invoice.project_id', $project_id);
            }

            $invoices = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $invoices]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeInvoice(Request $request)
    {
        $request->validate([
            'project_id' => 'required',
            'company_id' => 'required',
            'party_id' => 'required',
            'invoice_no' => 'required|unique:sales_invoice,invoice_no',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            
            $pdfPath = "";
            if ($request->hasFile('pdf')) {
                $pdfName = time() . rand(10000, 1000000) . '.' . $request->file('pdf')->extension();
                $request->file('pdf')->move(public_path('images/app_images/' . $conn . '/invoices'), $pdfName);
                $pdfPath = "images/app_images/" . $conn . "/invoices/" . $pdfName;
            }

            $imagePath = "";
            if ($request->hasFile('image')) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('image')->extension();
                $request->file('image')->move(public_path('images/app_images/' . $conn . '/invoices'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/invoices/" . $imageName;
            }

            $data = [
                'company_id' => $request->company_id,
                'project_id' => $request->project_id,
                'party_id' => $request->party_id,
                'financial_year' => $request->financial_year ?? getCurrentFinancialYear(),
                'invoice_no' => $request->invoice_no,
                'gst_rate' => $request->gst_rate,
                'taxable_value' => $request->taxable_value,
                'amount' => $request->amount,
                'pdf' => $pdfPath,
                'image' => $imagePath,
                'date' => $request->date,
                'status' => 'Active',
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('sales_invoice')->insertGetId($data);
            addActivity($id, 'sales_invoice', "New Sale Invoice Created via API", 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Invoice created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function invoiceDetails(Request $request, $id)
    {
        try {
            $invoice = DB::table('sales_invoice')
                ->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')
                ->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')
                ->where('sales_invoice.id', $id)
                ->select('sales_invoice.*', 'sales_project.name as project_name', 'sales_company.name as company_name', 'sales_party.name as party_name')
                ->first();

            if (!$invoice) return response()->json(['status' => 'Failed', 'message' => 'Invoice not found'], 404);

            $adjustments = DB::table('sales_manage_invoice')
                ->leftJoin('sales_dedadd', 'sales_dedadd.id', '=', 'sales_manage_invoice.type_id')
                ->where('sales_manage_invoice.invoice_id', $id)
                ->select('sales_manage_invoice.*', 'sales_dedadd.name as type_name', 'sales_dedadd.type as adjustment_type')
                ->get();

            return response()->json(['status' => 'Ok', 'data' => ['invoice' => $invoice, 'adjustments' => $adjustments]]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required',
            'type_id' => 'required',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $data = [
                'invoice_id' => $request->invoice_id,
                'type_id' => $request->type_id,
                'amount' => $request->amount,
                'date' => $request->date,
                'image' => "", // Mobile uploads can be added here if needed
                'pdf' => "",
                'create_datetime' => Carbon::now()
            ];

            $id = DB::table('sales_manage_invoice')->insertGetId($data);
            addActivity($id, 'sales_manage_invoice', "Sales Invoice Adjustment Added via API", 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Adjustment added successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SALES INVOICE HEADS
    // ==========================================

    public function listInvoiceHeads(Request $request)
    {
        try {
            $search = trim($request->get('search'));
            $export = $request->get('export');
            $query = DB::table('sales_dedadd')->orderBy('id', 'desc');

            if (!empty($search)) {
                $query->where('name', 'LIKE', "%{$search}%");
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "sales_invoice_heads_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Type']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->type]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $heads = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $heads]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeInvoiceHead(Request $request)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required',
            'type' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $id = DB::table('sales_dedadd')->insertGetId([
                'name' => $request->name,
                'type' => $request->type
            ]);

            addActivity($id, 'sales_dedadd', "New Sales Invoice Head Created via API: " . $request->name, 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Invoice Head created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function invoiceHeadDetails(Request $request, $id)
    {
        try {
            $head = DB::table('sales_dedadd')->where('id', $id)->first();
            if (!$head) return response()->json(['status' => 'Failed', 'message' => 'Invoice Head not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $head]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateInvoiceHead(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required',
            'type' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('sales_dedadd')->where('id', $id)->update([
                'name' => $request->name,
                'type' => $request->type
            ]);

            addActivity($id, 'sales_dedadd', "Sales Invoice Head Updated via API", 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Invoice Head updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteInvoiceHead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            $check = DB::table('sales_manage_invoice')->where('type_id', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'This Head Cannot Be Deleted. Head Is Used In Invoices!'], 400);
            }

            $head = DB::table('sales_dedadd')->where('id', $id)->first();
            if (!$head) return response()->json(['status' => 'Failed', 'message' => 'Invoice Head not found'], 404);

            DB::table('sales_dedadd')->where('id', $id)->delete();
            addActivity(0, 'sales_dedadd', "Sales Invoice Head Deleted via API: " . $head->name, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Invoice Head deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SALES PARTIES
    // ==========================================

    public function listParties(Request $request)
    {
        try {
            $search = trim($request->get('search'));
            $export = $request->get('export');
            $query = DB::table('sales_party')->orderBy('id', 'desc');

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('gst', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "sales_parties_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Name', 'Phone', 'GST', 'State', 'Status']);
                    foreach ($results as $row) {
                        fputcsv($file, [$row->id, $row->name, $row->phone, $row->gst, $row->state, $row->status]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $parties = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $parties]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeParty(Request $request)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'gst' => 'required',
            'phone' => 'required',
            'state' => 'required',
            'state_code' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $data = [
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'gst' => $request->gst,
                'state' => $request->state,
                'state_code' => $request->state_code,
                'status' => 'Active'
            ];

            $id = DB::table('sales_party')->insertGetId($data);
            addActivity($id, 'sales_party', "New Sales Party Created via API: " . $request->name, 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Party created successfully', 'id' => $id]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function partyDetails(Request $request, $id)
    {
        try {
            $party = DB::table('sales_party')->where('id', $id)->first();
            if (!$party) return response()->json(['status' => 'Failed', 'message' => 'Party not found'], 404);
            return response()->json(['status' => 'Ok', 'data' => $party]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateParty(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'name' => 'required',
            'address' => 'required',
            'gst' => 'required',
            'phone' => 'required',
            'state' => 'required',
            'state_code' => 'required'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            $data = [
                'name' => $request->name,
                'address' => $request->address,
                'phone' => $request->phone,
                'gst' => $request->gst,
                'state' => $request->state,
                'state_code' => $request->state_code
            ];

            DB::table('sales_party')->where('id', $id)->update($data);
            addActivity($id, 'sales_party', "Sales Party Updated via API", 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Party updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteParty(Request $request, $id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            $check = DB::table('sales_invoice')->where('party_id', $id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'This Party Cannot Be Deleted. Party Has Invoices In Its Name!'], 400);
            }

            $party = DB::table('sales_party')->where('id', $id)->first();
            if (!$party) return response()->json(['status' => 'Failed', 'message' => 'Party not found'], 404);

            DB::table('sales_party')->where('id', $id)->delete();
            addActivity(0, 'sales_party', "Sales Party Deleted via API: " . $party->name, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Party deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updatePartyStatus(Request $request, $id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'status' => 'required|in:Active,Deactive'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('sales_party')->where('id', $id)->update(['status' => $request->status]);
            addActivity($id, 'sales_party', "Sales Party Status Updated via API: " . $request->status, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Party status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }
}
