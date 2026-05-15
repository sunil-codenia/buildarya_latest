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

    public function listInvoices(Request $request, $id = null)
    {
        try {
            $project_id = $id ?? $request->get('project_id');
            $search = trim($request->get('search'));
            $export = $request->get('export');

            $query = DB::table('sales_invoice')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')
                ->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')
                ->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')
                ->select('sales_invoice.*', 'sales_company.name as company_name', 'sales_party.name as party_name', 'sales_project.name as project_name')
                ->orderBy('sales_invoice.id', 'desc');

            if (!empty($project_id)) {
                $query->where('sales_invoice.project_id', $project_id);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('sales_invoice.invoice_no', 'LIKE', "%{$search}%")
                      ->orWhere('sales_party.name', 'LIKE', "%{$search}%")
                      ->orWhere('sales_project.name', 'LIKE', "%{$search}%")
                      ->orWhere('sales_company.name', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "sales_receipts_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Date', 'Invoice No', 'Project', 'Party', 'Company', 'Amount', 'Status']);
                    foreach ($results as $row) {
                        fputcsv($file, [
                            $row->id, 
                            $row->date, 
                            $row->invoice_no, 
                            $row->project_name, 
                            $row->party_name, 
                            $row->company_name, 
                            $row->amount, 
                            $row->status
                        ]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $invoices = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $invoices]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeInvoice(Request $request, $id = null)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        // Use ID from URL if present
        if ($id) {
            $request->request->add(['project_id' => $id]);
        }

        $request->validate([
            'project_id' => 'required',
            'company_id' => 'required',
            'party_id' => 'required',
            'invoice_no' => 'required',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');

            // Check for duplicate invoice_no manually to avoid unique validator issues with soft deletes/tenants if any
            $exists = DB::table('sales_invoice')->where('invoice_no', $request->invoice_no)->exists();
            if ($exists) {
                return response()->json(['status' => 'Error', 'message' => 'Invoice No already exists!'], 400);
            }
            
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
                'gst_rate' => $request->gst_rate ?? 0,
                'taxable_value' => $request->taxable_value ?? $request->amount,
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

    public function updateInvoice(Request $request, $id, $invoice_id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            'company_id' => 'required',
            'party_id' => 'required',
            'invoice_no' => 'required',
            'amount' => 'required',
            'date' => 'required|date'
        ]);

        try {
            $user = $request->user();
            $conn = config('database.default');
            $invoice = DB::table('sales_invoice')->where('id', $invoice_id)->first();

            if (!$invoice) return response()->json(['status' => 'Failed', 'message' => 'Invoice not found'], 404);

            // Manual check for duplicate invoice_no if changed
            if ($request->invoice_no != $invoice->invoice_no) {
                $exists = DB::table('sales_invoice')->where('invoice_no', $request->invoice_no)->exists();
                if ($exists) {
                    return response()->json(['status' => 'Error', 'message' => 'Invoice No already exists!'], 400);
                }
            }

            $pdfPath = $invoice->pdf;
            if ($request->hasFile('pdf')) {
                if (!empty($invoice->pdf) && File::exists(public_path($invoice->pdf))) {
                    File::delete(public_path($invoice->pdf));
                }
                $pdfName = time() . rand(10000, 1000000) . '.' . $request->file('pdf')->extension();
                $request->file('pdf')->move(public_path('images/app_images/' . $conn . '/invoices'), $pdfName);
                $pdfPath = "images/app_images/" . $conn . "/invoices/" . $pdfName;
            }

            $imagePath = $invoice->image;
            if ($request->hasFile('image')) {
                if (!empty($invoice->image) && File::exists(public_path($invoice->image))) {
                    File::delete(public_path($invoice->image));
                }
                $imageName = time() . rand(10000, 1000000) . '.' . $request->file('image')->extension();
                $request->file('image')->move(public_path('images/app_images/' . $conn . '/invoices'), $imageName);
                $imagePath = "images/app_images/" . $conn . "/invoices/" . $imageName;
            }

            $data = [
                'company_id' => $request->company_id,
                'party_id' => $request->party_id,
                'financial_year' => $request->financial_year ?? $invoice->financial_year,
                'invoice_no' => $request->invoice_no,
                'gst_rate' => $request->gst_rate ?? $invoice->gst_rate,
                'taxable_value' => $request->taxable_value ?? $request->amount,
                'amount' => $request->amount,
                'pdf' => $pdfPath,
                'image' => $imagePath,
                'date' => $request->date
            ];

            DB::table('sales_invoice')->where('id', $invoice_id)->update($data);
            addActivity($invoice_id, 'sales_invoice', "Sale Invoice Updated via API", 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Invoice updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function deleteInvoice(Request $request, $id, $invoice_id)
    {
        try {
            $user = $request->user();
            $conn = config('database.default');

            // Check if used in adjustments
            $check = DB::table('sales_manage_invoice')->where('invoice_id', $invoice_id)->count();
            if ($check > 0) {
                return response()->json(['status' => 'Error', 'message' => 'This Invoice Cannot Be Deleted. It has associated adjustments!'], 400);
            }

            $invoice = DB::table('sales_invoice')->where('id', $invoice_id)->first();
            if (!$invoice) return response()->json(['status' => 'Failed', 'message' => 'Invoice not found'], 404);

            if (!empty($invoice->pdf) && File::exists(public_path($invoice->pdf))) {
                File::delete(public_path($invoice->pdf));
            }
            if (!empty($invoice->image) && File::exists(public_path($invoice->image))) {
                File::delete(public_path($invoice->image));
            }

            DB::table('sales_invoice')->where('id', $invoice_id)->delete();
            addActivity(0, 'sales_invoice', "Sale Invoice Deleted via API: " . $invoice->invoice_no, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Invoice deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateInvoiceStatus(Request $request, $id, $invoice_id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate(['status' => 'required|in:Active,Deactive']);

        try {
            $user = $request->user();
            $conn = config('database.default');

            DB::table('sales_invoice')->where('id', $invoice_id)->update(['status' => $request->status]);
            addActivity($invoice_id, 'sales_invoice', "Sale Invoice Status Updated via API: " . $request->status, 7, $user->id, $conn);

            return response()->json(['status' => 'Ok', 'message' => 'Invoice status updated successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function invoiceDetails(Request $request, $id, $invoice_id = null)
    {
        try {
            $target_id = $invoice_id ?? $id;
            $invoice = DB::table('sales_invoice')
                ->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')
                ->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')
                ->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')
                ->where('sales_invoice.id', $target_id)
                ->select('sales_invoice.*', 'sales_project.name as project_name', 'sales_company.name as company_name', 'sales_party.name as party_name')
                ->first();

            if (!$invoice) return response()->json(['status' => 'Failed', 'message' => 'Invoice not found'], 404);

            $adjustments = DB::table("sales_manage_invoice")
                ->leftJoin("sales_dedadd", "sales_dedadd.id", "=", "sales_manage_invoice.type_id")
                ->where("sales_manage_invoice.invoice_id", $target_id)
                ->select("sales_manage_invoice.*", "sales_dedadd.name as type_name", "sales_dedadd.type as type")
                ->get();

            $debits = [];
            $credits = [];
            $total_debit = 0;
            $total_credit = 0;

            foreach ($adjustments as $adj) {
                if ($adj->type == "add") {
                    $debits[] = $adj;
                    $total_debit += $adj->amount;
                } else {
                    $credits[] = $adj;
                    $total_credit += $adj->amount;
                }
            }

            $gross_value = floatval($invoice->amount);
            $balance = $gross_value + $total_debit - $total_credit;

            return response()->json([
                "status" => "Ok",
                "data" => [
                    "invoice" => $invoice,
                    "summary" => [
                        "invoice_no" => $invoice->invoice_no,
                        "project" => $invoice->project_name,
                        "company" => $invoice->company_name,
                        "taxable_value" => $invoice->taxable_value,
                        "gst_rate" => $invoice->gst_rate,
                        "gross_value" => $invoice->amount,
                        "date" => $invoice->date,
                        "financial_year" => $invoice->financial_year,
                        "party" => $invoice->party_name,
                        "status" => $invoice->status
                    ],
                    "debits" => $debits,
                    "credits" => $credits,
                    "totals" => [
                        "total_debit" => $total_debit,
                        "total_credit" => $total_credit,
                        "balance" => $balance
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function salesReport(Request $request)
    {
        $type = $request->get("type");
        $party_id = $request->get("party_id");
        $project_id = $request->get("project_id");
        $company_id = $request->get("company_id");
        $head_id = $request->get("head_id");
        $financial_year = $request->get("financial_year");

        try {
            $query = DB::table("sales_invoice as si")
                ->leftJoin("sales_company as sc", "sc.id", "=", "si.company_id")
                ->leftJoin("sales_project as sp", "sp.id", "=", "si.project_id")
                ->leftJoin("sales_party as sparty", "sparty.id", "=", "si.party_id")
                ->select("si.*", "sc.name as company_name", "sp.name as project_name", "sparty.name as party_name");

            if ($type == 1) {
                $query->where("si.party_id", $party_id);
            } else if ($type == 2) {
                $query->where("si.project_id", $project_id);
            } else if ($type == 3) {
                $query->where("si.financial_year", $financial_year);
            } else if ($type == 4) {
                $query->where("si.financial_year", $financial_year)->where("si.company_id", $company_id);
            } else if ($type == 5) {
                $query->where("si.financial_year", $financial_year)
                    ->whereIn("si.id", function ($q) use ($head_id) {
                        $q->select("invoice_id")->from("sales_manage_invoice")->where("type_id", $head_id);
                    });
            }

            $invoices = $query->get();
            if ($invoices->isEmpty()) {
                return response()->json(["status" => "Failed", "message" => "No data found for the selected criteria"], 404);
            }

            $filename = "sales_report_" . time() . ".csv";
            $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
            $callback = function() use ($invoices) {
                $file = fopen("php://output", "w");
                fputcsv($file, ["ID", "Invoice No", "Date", "Project", "Party", "Company", "FY", "Status", "Taxable Value", "Gross Amount"]);
                foreach ($invoices as $row) {
                    fputcsv($file, [
                        $row->id,
                        $row->invoice_no,
                        $row->date,
                        $row->project_name,
                        $row->party_name,
                        $row->company_name,
                        $row->financial_year,
                        $row->status,
                        $row->taxable_value,
                        $row->amount
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            return response()->json(["status" => "Error", "message" => $e->getMessage()], 500);
        }
    }
    public function listAdjustments(Request $request)
    {
        try {
            $project_id = $request->get('project_id');
            $invoice_id = $request->get('invoice_id');
            $search = trim($request->get('search'));
            $export = $request->get('export');

            $query = DB::table('sales_manage_invoice')
                ->leftJoin('sales_invoice', 'sales_invoice.id', '=', 'sales_manage_invoice.invoice_id')
                ->leftJoin('sales_dedadd', 'sales_dedadd.id', '=', 'sales_manage_invoice.type_id')
                ->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')
                ->select("sales_manage_invoice.*", "sales_dedadd.name as type_name", "sales_dedadd.type as adjustment_type", "sales_invoice.invoice_no", "sales_project.name as project_name")
                ->orderBy('sales_manage_invoice.id', 'desc');

            if (!empty($project_id)) {
                $query->where('sales_invoice.project_id', $project_id);
            }
            if (!empty($invoice_id)) {
                $query->where('sales_manage_invoice.invoice_id', $invoice_id);
            }

            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('sales_invoice.invoice_no', 'LIKE', "%{$search}%")
                      ->orWhere('sales_dedadd.name', 'LIKE', "%{$search}%")
                      ->orWhere('sales_project.name', 'LIKE', "%{$search}%");
                });
            }

            if ($export == 'csv') {
                $results = $query->get();
                $filename = "sales_payments_" . time() . ".csv";
                $headers = ["Content-type" => "text/csv", "Content-Disposition" => "attachment; filename=$filename"];
                $callback = function() use ($results) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, ['ID', 'Date', 'Invoice No', 'Project', 'Type', 'Adjustment Type', 'Amount']);
                    foreach ($results as $row) {
                        fputcsv($file, [
                            $row->id, 
                            $row->date, 
                            $row->invoice_no, 
                            $row->project_name, 
                            $row->type_name, 
                            $row->adjustment_type, 
                            $row->amount
                        ]);
                    }
                    fclose($file);
                };
                return response()->stream($callback, 200, $headers);
            }

            $adjustments = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $adjustments]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeAdjustment(Request $request, $id = null, $invoice_id = null)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $inv_id = $invoice_id ?? $request->invoice_id;
        $request->merge(["invoice_id" => $inv_id]);

        $request->validate([
            "invoice_id" => "required",
            "type_id" => "required",
            "amount" => "required",
            "date" => "required|date"
        ]);

        try {
            $user = $request->user();
            $conn = config("database.default");

            $pdfPath = "";
            if ($request->hasFile("pdf")) {
                $pdfName = time() . rand(10000, 1000000) . "." . $request->file("pdf")->extension();
                $request->file("pdf")->move(public_path("images/app_images/" . $conn . "/invoices"), $pdfName);
                $pdfPath = "images/app_images/" . $conn . "/invoices/" . $pdfName;
            }

            $imagePath = "";
            if ($request->hasFile("image")) {
                $imageName = time() . rand(10000, 1000000) . "." . $request->file("image")->extension();
                $request->file("image")->move(public_path("images/app_images/" . $conn . "/invoices"), $imageName);
                $imagePath = "images/app_images/" . $conn . "/invoices/" . $imageName;
            }

            $data = [
                "invoice_id" => $request->invoice_id,
                "type_id" => $request->type_id,
                "amount" => $request->amount,
                "date" => $request->date,
                "image" => $imagePath,
                "pdf" => $pdfPath,
                "create_datetime" => Carbon::now()
            ];

            $id = DB::table("sales_manage_invoice")->insertGetId($data);
            addActivity($id, "sales_manage_invoice", "Sales Invoice Adjustment Added via API", 7, $user->id, $conn);

            return response()->json(["status" => "Ok", "message" => "Adjustment added successfully", "id" => $id]);
        } catch (\Exception $e) {
            return response()->json(["status" => "Error", "message" => $e->getMessage()], 500);
        }
    }

    public function updateAdjustment(Request $request, $id, $invoice_id, $adjustment_id)
    {
        if (!empty($request->getContent())) {
            $json = json_decode($request->getContent(), true);
            if ($json) $request->request->add($json);
        }

        $request->validate([
            "type_id" => "required",
            "amount" => "required",
            "date" => "required|date"
        ]);

        try {
            $user = $request->user();
            $conn = config("database.default");

            $adj = DB::table("sales_manage_invoice")->where("id", $adjustment_id)->first();
            if (!$adj) return response()->json(["status" => "Failed", "message" => "Adjustment not found"], 404);

            $pdfPath = $adj->pdf;
            if ($request->hasFile("pdf")) {
                if (!empty($adj->pdf) && File::exists(public_path($adj->pdf))) {
                    File::delete(public_path($adj->pdf));
                }
                $pdfName = time() . rand(10000, 1000000) . "." . $request->file("pdf")->extension();
                $request->file("pdf")->move(public_path("images/app_images/" . $conn . "/invoices"), $pdfName);
                $pdfPath = "images/app_images/" . $conn . "/invoices/" . $pdfName;
            }

            $imagePath = $adj->image;
            if ($request->hasFile("image")) {
                if (!empty($adj->image) && File::exists(public_path($adj->image))) {
                    File::delete(public_path($adj->image));
                }
                $imageName = time() . rand(10000, 1000000) . "." . $request->file("image")->extension();
                $request->file("image")->move(public_path("images/app_images/" . $conn . "/invoices"), $imageName);
                $imagePath = "images/app_images/" . $conn . "/invoices/" . $imageName;
            }

            $data = [
                "type_id" => $request->type_id,
                "amount" => $request->amount,
                "date" => $request->date,
                "image" => $imagePath,
                "pdf" => $pdfPath
            ];

            DB::table("sales_manage_invoice")->where("id", $adjustment_id)->update($data);
            addActivity($adjustment_id, "sales_manage_invoice", "Sales Invoice Adjustment Updated via API", 7, $user->id, $conn);

            return response()->json(["status" => "Ok", "message" => "Adjustment updated successfully"]);
        } catch (\Exception $e) {
            return response()->json(["status" => "Error", "message" => $e->getMessage()], 500);
        }
    }

    public function deleteAdjustment(Request $request, $id, $invoice_id, $adjustment_id)
    {
        try {
            $user = $request->user();
            $conn = config("database.default");

            $adj = DB::table("sales_manage_invoice")->where("id", $adjustment_id)->first();
            if (!$adj) return response()->json(["status" => "Failed", "message" => "Adjustment not found"], 404);

            if (!empty($adj->pdf) && File::exists(public_path($adj->pdf))) {
                File::delete(public_path($adj->pdf));
            }
            if (!empty($adj->image) && File::exists(public_path($adj->image))) {
                File::delete(public_path($adj->image));
            }

            DB::table("sales_manage_invoice")->where("id", $adjustment_id)->delete();
            addActivity(0, "sales_manage_invoice", "Sales Invoice Adjustment Deleted via API: Amount " . $adj->amount, 7, $user->id, $conn);

            return response()->json(["status" => "Ok", "message" => "Adjustment deleted successfully"]);
        } catch (\Exception $e) {
            return response()->json(["status" => "Error", "message" => $e->getMessage()], 500);
        }
    }

    public function adjustmentDetails(Request $request, $id, $invoice_id, $adjustment_id)
    {
        try {
            $adj = DB::table("sales_manage_invoice")
                ->leftJoin("sales_dedadd", "sales_dedadd.id", "=", "sales_manage_invoice.type_id")
                ->where("sales_manage_invoice.id", $adjustment_id)
                ->select("sales_manage_invoice.*", "sales_dedadd.name as type_name", "sales_dedadd.type as type")
                ->first();

            if (!$adj) return response()->json(["status" => "Failed", "message" => "Adjustment not found"], 404);

            return response()->json(["status" => "Ok", "data" => $adj]);
        } catch (\Exception $e) {
            return response()->json(["status" => "Error", "message" => $e->getMessage()], 500);
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
