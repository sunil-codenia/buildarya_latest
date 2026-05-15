<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class InvoiceManageController extends Controller
{
    function sales_manage_invoice(Request $request){
        $data =array();
        $invoice_id = $request->get('invoice_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['types'] = DB::connection($user_db_conn_name)->table('sales_dedadd')->get();
        $data['manage'] = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->leftJoin('sales_dedadd','sales_dedadd.id','=','sales_manage_invoice.type_id')->where('sales_manage_invoice.invoice_id','=',$invoice_id)->select('sales_manage_invoice.*','sales_dedadd.name as type_name','sales_dedadd.type as type')->get();
        $data['invoice'] = DB::connection($user_db_conn_name)->table('sales_invoice')->leftJoin('sales_project','sales_project.id','=','sales_invoice.project_id')->leftJoin('sales_party','sales_party.id','=','sales_invoice.party_id')->leftJoin('sales_company','sales_company.id','=','sales_invoice.company_id')->where('sales_invoice.id','=',$invoice_id)->select('sales_invoice.*','sales_project.name as project','sales_company.name as company','sales_party.name as party')->get()[0];
        return  view('layouts.sales.manage_invoice')->with('data',json_encode($data));
    }
    function delete_sales_manage_invoice(Request $request){
        $id = $request->get('id');
        $invoice_id= $request->get('invoice_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $manage_invoice = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('id', $id)->get()[0];
        if (File::exists($manage_invoice->pdf)) {
            File::delete($manage_invoice->pdf);
        }
        if (File::exists($manage_invoice->image)) {
            File::delete($manage_invoice->image);
        }
    DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('id','=',$id)->delete();
    addActivity(0,'sales_manage_invoice',"Sales Invoice Item Deleted Of Amount - ".$manage_invoice->amount,7);
        return redirect('/sales_manage_invoice/?invoice_id='.$invoice_id)
        ->with('success', 'Entry Deleted Successfully!');
    }
    function updatesales_manage_invoice(Request $request){
        $id = $request->get('id');
        $invoice_id= $request->get('invoice_id');   
        $amount = $request->input('amount');
        $type_id = $request->input('type_id');  
        $date = $request->input('date');  
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $manage_invoice = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('id', $id)->get()[0];
      
        if (isset($request->pdf)) {
            if (File::exists($manage_invoice->pdf)) {
                File::delete($manage_invoice->pdf);
            }
            $pdfName = time() . rand(10000, 1000000) . '.' . $request->pdf->extension();
            $request->pdf->move(public_path('images/app_images/'.$user_db_conn_name.'/invoices'), $pdfName);
            $pdfPath = "images/app_images/".$user_db_conn_name."/invoices/" . $pdfName;
        } else {
            $pdfPath = $manage_invoice->pdf;
        }
        if (isset($request->image)) {
            if (File::exists($manage_invoice->image)) {
                File::delete($manage_invoice->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/invoices'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/invoices/" . $imageName;
        } else {
            $imagePath = $manage_invoice->image;
        }


        try {
            DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('id', $id)->update(['invoice_id'=>$invoice_id,'type_id'=>$type_id,'amount'=>$amount,'date'=>$date,'image'=>$imagePath,'pdf'=>$pdfPath]);
            addActivity($id,'sales_manage_invoice',"Sales Invoice Item Updated Of Amount - ".$amount,7);
            return redirect('/sales_manage_invoice/?invoice_id='.$invoice_id)
            ->with('success', 'Entry Updated successfully!');
        } catch (\Exception $e){
            return redirect('/sales_manage_invoice/?invoice_id='.$invoice_id)
            ->with('error', 'Error While Updating Entry!');
        }
    }
    function edit_sales_manage_invoice(Request $request){
        $id = $request->get('id');
        $data =array();
        $invoice_id= $request->get('invoice_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['types'] = DB::connection($user_db_conn_name)->table('sales_dedadd')->get();
        $data['manage'] = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->leftJoin('sales_dedadd','sales_dedadd.id','=','sales_manage_invoice.type_id')->where('sales_manage_invoice.invoice_id','=',$invoice_id)->select('sales_manage_invoice.*','sales_dedadd.name as type_name','sales_dedadd.type as type')->get();
        $data['invoice'] = DB::connection($user_db_conn_name)->table('sales_invoice')->leftJoin('sales_project', 'sales_project.id', '=', 'sales_invoice.project_id')->leftJoin('sales_party', 'sales_party.id', '=', 'sales_invoice.party_id')->leftJoin('sales_company', 'sales_company.id', '=', 'sales_invoice.company_id')->where('sales_invoice.id', '=', $invoice_id)->select('sales_invoice.*', 'sales_project.name as project', 'sales_company.name as company', 'sales_party.name as party')->get()[0];
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('id','=',$id)->get();
        return  view('layouts.sales.manage_invoice')->with('data',json_encode($data));
    }
    function addsales_manage_invoice(Request $request){
        $invoice_id= $request->get('invoice_id');   
        $amount = $request->input('amount');
        $type_id = $request->input('type_id');  
        $date = $request->input('date');  
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (isset($request->pdf)) {
            $pdfName = time() . rand(10000, 1000000) . '.' . $request->pdf->extension();
            $request->pdf->move(public_path('images/app_images/'.$user_db_conn_name.'/invoices'), $pdfName);
            $pdfPath = "images/app_images/".$user_db_conn_name."/invoices/" . $pdfName;
        } else {
            $pdfPath = "";
        }
        if (isset($request->image)) {
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/invoices'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/invoices/" . $imageName;
        } else {
            $imagePath = "";
        }

        $data=['invoice_id'=>$invoice_id,'type_id'=>$type_id,'amount'=>$amount,'date'=>$date,'image'=>$imagePath,'pdf'=>$pdfPath];

        try {
         $sales_manage_invoice = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->insertGetId($data);
            addActivity($sales_manage_invoice,'sales_manage_invoice',"Sales Invoice Item Added Of Amount - ".$amount,7);
            return redirect('/sales_manage_invoice/?invoice_id='.$invoice_id)
                ->with('success', 'Entry Created successfully!');
        } catch (\Exception $e){
              return redirect('/sales_manage_invoice/?invoice_id='.$invoice_id)
                ->with('error', 'Error While Creating Entry!');
        }
}
}
