<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InvoiceController extends Controller
{
    //
    function sales_invoice(Request $request)
    {
        $project_id = $request->get('project_id');

        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['companies'] = DB::connection($user_db_conn_name)->table('sales_company')->where('status', '=', 'Active')->get();
        $data['project'] = DB::connection($user_db_conn_name)->table('sales_project')->where('id', '=', $project_id)->get()[0];
        $data['parties'] = DB::connection($user_db_conn_name)->table('sales_party')->where('status', '=', 'Active')->get();
        $data['invoices'] = DB::connection($user_db_conn_name)->table('sales_invoice')->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')->where('sales_invoice.project_id', '=', $project_id)->select('sales_invoice.*', 'sales_company.name as company', 'sales_party.name as party')->get();
        return  view('layouts.sales.invoice')->with('data', json_encode($data));
    }
    public function all_sales_invoice(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;
        $site_id = $request->session()->get('site_id');

        $query = DB::connection($user_db_conn_name)->table('sales_invoice')->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')->select('sales_invoice.*', 'sales_company.name as company', 'sales_party.name as party', 'sales_project.name as project');

        if ($visiblity_at_site == 'current') {
            if ($site_id == 'all') {
                $assigned_site_ids = $request->session()->get('assigned_site_ids', []);
                $project_ids = DB::connection($user_db_conn_name)->table('sites')->whereIn('id', $assigned_site_ids)->where('project_id', '!=', 0)->pluck('project_id')->toArray();
                $query->whereIn('sales_invoice.project_id', $project_ids);
            } else {
                $project_id = DB::connection($user_db_conn_name)->table('sites')->where('id', $site_id)->value('project_id');
                $query->where('sales_invoice.project_id', $project_id);
            }
        }

        $invoices = $query->get();
        return  view('layouts.sales.allinvoices')->with('data', json_encode($invoices));
    }
    function delete_sales_invoice(Request $request)
    {
        $project_id = $request->get('project_id');
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('invoice_id', '=', $id)->get();
        if (Count($check) > 0) {
            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('error', 'This invoice Cannot Be Deleted. Invoice Has Data!');
        } else {

            if (File::exists($check[0]->pdf)) {
                File::delete($check[0]->pdf);
            }
            if (File::exists($check[0]->image)) {
                File::delete($check[0]->image);
            }
            DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', '=', $id)->delete();
            addActivity(0,'sales_invoice',"Invoice Deleted - ".$check[0]->invoice_no, 7);
            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('success', 'Invoice Deleted Successfully!');
        }
    }
    function updatesalesinvoice(Request $request)
    {
        $id = $request->input('id');
        $company_id = $request->input('company_id');
        $project_id = $request->input('project_id');
        $party_id = $request->input('party_id');
        $financial_year = $request->input('financial_year');
        $invoice_no = $request->input('invoice_no');
        $amount = $request->input('amount');
        $gst_rate = $request->input('gst_rate');
        $taxable_value = $request->input('taxable_value');
        $date = $request->input('date');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $invoice = DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', $id)->get()[0];

        if (isset($request->pdf)) {
            if (File::exists($invoice->pdf)) {
                File::delete($invoice->pdf);
            }
            $pdfName = time() . rand(10000, 1000000) . '.' . $request->pdf->extension();
            $request->pdf->move(public_path('images/app_images/' . $user_db_conn_name . '/invoices'), $pdfName);
            $pdfPath = "images/app_images/" . $user_db_conn_name . "/invoices/" . $pdfName;
        } else {
            $pdfPath = $invoice->pdf;
        }
        if (isset($request->image)) {
            if (File::exists($invoice->image)) {
                File::delete($invoice->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/invoices'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/invoices/" . $imageName;
        } else {
            $imagePath = $invoice->image;
        }
        try {
            DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', $id)->update(['company_id' => $company_id, 'project_id' => $project_id, 'party_id' => $party_id, 'financial_year' => $financial_year, 'invoice_no' => $invoice_no, 'gst_rate' => $gst_rate, 'taxable_value' => $taxable_value, 'amount' => $amount, 'pdf' => $pdfPath, 'image' => $imagePath, 'date' => $date]);
            addActivity($id,'sales_invoice',"Sale Invoice Updated",7);

            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('success', 'Invoice Updated successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_invoice/?project_id=' . $project_id)
                    ->with('error', 'Invoice Already Exists With Same No!');
            } else {
                return redirect('/sales_invoice/?project_id=' . $project_id)
                    ->with('error', 'Error While Updating Invoice!');
            }
        }
    }
    function edit_sales_invoice(Request $request)
    {
        $id = $request->get('id');
        $project_id = $request->get('project_id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['companies'] = DB::connection($user_db_conn_name)->table('sales_company')->where('status', '=', 'Active')->get();
        $data['project'] = DB::connection($user_db_conn_name)->table('sales_project')->where('id', '=', $project_id)->get()[0];
        $data['parties'] = DB::connection($user_db_conn_name)->table('sales_party')->where('status', '=', 'Active')->get();
        $data['invoices'] = DB::connection($user_db_conn_name)->table('sales_invoice')->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')->where('sales_invoice.project_id', '=', $project_id)->select('sales_invoice.*', 'sales_company.name as company', 'sales_party.name as party')->get();

        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', '=', $id)->get();
        return  view('layouts.sales.invoice')->with('data', json_encode($data));
    }
    function update_sales_invoice_status(Request $request)
    {
        $id = $request->get('id');
        $project_id = $request->get('project_id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id,'sales_invoice',"Sale Invoice Status Update - ".$status,7);

        if ($status == 'Active') {
            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('success', 'Invoice Activated!');
        } else {
            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('success', 'Invoice Deactivated!');
        }
    }


    public function sales_pdf(Request $request)
    {
        $id = $request->get('id');
        echo "sales pdf";
    }



    function addsalesinvoice(Request $request)
    {
        $project_id = $request->get('project_id');
        $company_id = $request->input('company_id');
        $project_id = $request->input('project_id');
        $party_id = $request->input('party_id');
        $financial_year = $request->input('financial_year');
        $invoice_no = $request->input('invoice_no');
        $amount = $request->input('amount');
        $gst_rate = $request->input('gst_rate');
        $taxable_value = $request->input('taxable_value');
        $date = $request->input('date');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        if (isset($request->pdf)) {
            $pdfName = time() . rand(10000, 1000000) . '.' . $request->pdf->extension();
            $request->pdf->move(public_path('images/app_images/' . $user_db_conn_name . '/invoices'), $pdfName);
            $pdfPath = "images/app_images/" . $user_db_conn_name . "/invoices/" . $pdfName;
        } else {
            $pdfPath = "";
        }
        if (isset($request->image)) {
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/invoices'), $imageName);
            $imagePath = "images/app_images/'.$user_db_conn_name.'/invoices/" . $imageName;
        } else {
            $imagePath = "";
        }

        $data = ['company_id' => $company_id, 'project_id' => $project_id, 'party_id' => $party_id, 'financial_year' => $financial_year, 'invoice_no' => $invoice_no, 'gst_rate' => $gst_rate, 'taxable_value' => $taxable_value, 'amount' => $amount, 'pdf' => $pdfPath, 'image' => $imagePath, 'date' => $date];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
          $sale_invoice = DB::connection($user_db_conn_name)->table('sales_invoice')->insertGetId($data);
            addActivity($sale_invoice,'sales_invoice',"New Invoice Created",7);
            return redirect('/sales_invoice/?project_id=' . $project_id)
                ->with('success', 'Invoice Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_invoice/?project_id=' . $project_id)
                    ->with('error', 'Invoice With Same No Already Exists!');
            } else {
                return redirect('/sales_invoice/?project_id=' . $project_id)
                    ->with('error', 'Error While Creating Invoice!');
            }
        }
    }
    public function sales_report(Request $request)
    {
        return view('layouts.sales.sales_report');
    }
}
