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
            $query = DB::table('sales_project')->orderBy('id', 'desc');
            $projects = $query->paginate(20);
            return response()->json(['status' => 'Ok', 'data' => $projects]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'Error', 'message' => $e->getMessage()], 500);
        }
    }

    public function storeProject(Request $request)
    {
        $request->validate(['name' => 'required|unique:sales_project,name']);
        try {
            $user = $request->user();
            $conn = config('database.default');
            $id = DB::table('sales_project')->insertGetId([
                'name' => $request->name,
                'details' => $request->details,
                'status' => 'Active',
                'create_datetime' => Carbon::now()
            ]);
            addActivity($id, 'sales_project', "New Sales Project Created via API: " . $request->name, 7, $user->id, $conn);
            return response()->json(['status' => 'Ok', 'message' => 'Project created successfully', 'id' => $id]);
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
}
