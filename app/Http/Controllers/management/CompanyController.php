<?php

namespace App\Http\Controllers\management;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

use App\Exports\SalesExport;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CompanyController extends Controller
{
    //
    function sales_companies(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = DB::connection($user_db_conn_name)->table('sales_company')->get();

        return  view('layouts.management.companies')->with('data', json_encode($data));
    }
    function delete_sales_company(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('sales_invoice')->where('company_id', '=', $id)->get();
        $compname = DB::connection($user_db_conn_name)->table('sales_company')->where('id', '=', $id)->get()[0]->name;
        if (Count($check) > 0) {
            return redirect('/sales_companies')
                ->with('error', 'This Company Cannot Be Deleted. Company Has Invoices In Its Name!');
        } else {
            DB::connection($user_db_conn_name)->table('sales_company')->where('id', '=', $id)->delete();
            addActivity(0, 'sales_company', "Sales Company Deleted - " . $compname, 9);
            return redirect('/sales_companies')
                ->with('success', 'Company Deleted Successfully!');
        }
    }
    function updatesalescompany(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $address = $request->input('address');
        $phone = $request->input('phone');
        $gst = $request->input('gst');
        $state = $request->input('state');
        $state_code = $request->input('state_code');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        try {
            DB::connection($user_db_conn_name)->table('sales_company')->where('id', $id)->update(['name' => $name, 'address' => $address, 'phone' => $phone, 'gst' => $gst, 'state' => $state, 'state_code' => $state_code]);
            addActivity($id, 'sales_company', "Sales Company Data Updated", 9);
            return redirect('/sales_companies')
                ->with('success', 'Company Updated successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_companies')
                    ->with('error', 'Company Already Exists With Same Credentials!');
            } else {
                return redirect('/sales_companies')
                    ->with('error', 'Error While Updating Company!');
            }
        }
    }
    function edit_sales_company(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('sales_company')->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sales_company')->where('id', '=', $id)->get();
        return  view('layouts.management.companies')->with('data', json_encode($data));
    }
    function update_sales_company_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('sales_company')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id, 'sales_company', "Sales Company Status Updated - " . $status, 9);
        if ($status == 'Active') {
            return redirect('/sales_companies')
                ->with('success', 'Company Activated!');
        } else {
            return redirect('/sales_companies')
                ->with('success', 'Company Deactivated!');
        }
    }



    function addsalescompany(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $phone = $request->input('phone');
        $gst = $request->input('gst');
        $state = $request->input('state');
        $state_code = $request->input('state_code');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = [
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'gst' => $gst,
            'state' => $state,
            'state_code' => $state_code
        ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $addsalescompany = DB::connection($user_db_conn_name)->table('sales_company')->insertGetId($data);
            addActivity($addsalescompany, 'sales_company', "New Sales Company Created", 9);
            return redirect('/sales_companies')
                ->with('success', 'Company Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_companies')
                    ->with('error', 'Company Already Exists!');
            } else {
                return redirect('/sales_companies')
                    ->with('error', 'Error While Creating Company!');
            }
        }
    }
    public function management_report(Request $request) {}
    public function activity(Request $request)
    {
        if (!isSuperAdmin()) {
            return redirect('/dashboard')->with('error', 'Unauthorized Access!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $by_module = false;
        $activities = DB::connection($user_db_conn_name)->table('activity')->orderBy('id', 'desc')->limit(2000)->get();
        return  view('layouts.management.activity', compact(['activities', 'by_module']));
    }
    public function moduleActivity(Request $request)
    {
        if (!isSuperAdmin()) {
            return redirect('/dashboard')->with('error', 'Unauthorized Access!');
        }

        $module_id = $request->get('module_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $by_module = true;
        $activities = DB::connection($user_db_conn_name)->table('activity')->where('module_id', $module_id)->orderBy('id', 'desc')->limit(2000)->get();
        return  view('layouts.management.activity', compact(['activities', 'by_module', 'module_id']));
    }
    public function sales_report(Request $request)
    {

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $parties = DB::connection($user_db_conn_name)->table('sales_party')->get();
        $projects = DB::connection($user_db_conn_name)->table('sales_project')->get();
        $companies = DB::connection($user_db_conn_name)->table('sales_company')->get();
        $heads = DB::connection($user_db_conn_name)->table('sales_dedadd')->get();
        return view('layouts.sales.sales_report', compact(['parties', 'projects', 'companies', 'heads']));
    }



    public function salesreport(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $report_code = $request->get('type');
        $party_id = $request->get('party_id');
        $head_id =  $request->get('head_id');
        $project_id =  $request->get('project_id');
        $company_id =  $request->get('company_id');
        $financial_year = $request->get('financial_year');
        addActivity(0, 'materials', "Sales Invoice Report Generated Of Data.", 3);

        if ($report_code == 1) {
            $invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('party_id',$party_id)->count();
            if($invoices == 0){
                return redirect('/sales_report')
                ->with('error', 'Party Don\'t Have Any Invoice To Generate Report!');
            }
            return $this->exportExcel($user_db_conn_name, $report_code, null, $party_id, null, null, null);
        } else if ($report_code == 2) {
            $invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id',$project_id)->count();
            if($invoices == 0){
                return redirect('/sales_report')
                ->with('error', 'Project Don\'t Have Any Invoice To Generate Report!');
            }
            return $this->exportExcel($user_db_conn_name, $report_code,  $project_id, null, null, null, null);
        } else if ($report_code == 3) {
            $invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('financial_year',$financial_year)->count();
            if($invoices == 0){
                return redirect('/sales_report')
                ->with('error', 'This Year Don\'t Have Any Invoice To Generate Report!');
            }
            return $this->exportExcel($user_db_conn_name, $report_code,  null, null, $financial_year, null, null);
        } else if ($report_code == 4) {
            $invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('company_id',$company_id)->where('financial_year',$financial_year)->count();
            if($invoices == 0){
                return redirect('/sales_report')
                ->with('error', 'This Year In This Company Don\'t Have Any Invoice To Generate Report!');
            }
            return $this->exportExcel($user_db_conn_name, $report_code,  null, null, $financial_year, $company_id, null);
        } else if ($report_code == 5) {
            $invoices = DB::connection($user_db_conn_name)->table('sales_invoice as si')
            ->whereIn('si.id', function ($query)use ($head_id) {
                $query->select('smi.invoice_id')
                      ->from('sales_manage_invoice as smi')
                      ->where('smi.type_id', $head_id);
            })
            ->count();
            if($invoices == 0){
                return redirect('/sales_report')
                ->with('error', 'This Head Don\'t Have Any Invoice In This Financial Year To Generate Report!');
            }
            
            return $this->exportExcel($user_db_conn_name, $report_code,  null, null, $financial_year, null, $head_id);
        }
    }
    public function exportExcel($user_db_conn_name, $report_code,  $projectname = null, $partyname = null, $financial_year = null, $companyname = null, $headname = null)
    {


        $file_name = "Sales Invoice ";

        if ($report_code == 1) {
            $file_name .= "Report According To Party";
        } else         if ($report_code == 2) {
            $file_name .= "Report According To Project";
        } else         if ($report_code == 3) {
            $file_name .= "Report According To Financial Year";
        } else         if ($report_code == 4) {
            $file_name .= "Report According To Company";
        } else         if ($report_code == 5) {
            $file_name .= "Report According To Invoice Head";
        }
        $file_name .= ".xlsx";


        return Excel::download(new SalesExport($user_db_conn_name, $report_code, $projectname, $partyname, $financial_year, $companyname, $headname), $file_name);
    }
}
