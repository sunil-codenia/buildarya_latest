<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceHeadsController extends Controller
{
    //
    function sales_inv_head(Request $request){
        $data =array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = DB::connection($user_db_conn_name)->table('sales_dedadd')->get();

        return  view('layouts.sales.inv_head')->with('data',json_encode($data));
    }
    function delete_sales_inv_head(Request $request){
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
       $check = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('type_id','=',$id)->get();
       $headname_obj = DB::connection($user_db_conn_name)->table('sales_dedadd')->where('id','=',$id)->first();
       $headname = $headname_obj ? $headname_obj->name : 'Unknown';
       if(Count($check) > 0){
    return redirect('/sales_inv_head')
    ->with('error', 'This Head Cannot Be Deleted. Head Is Used In Invoices!');
   }else{
    DB::connection($user_db_conn_name)->table('sales_dedadd')->where('id','=',$id)->delete();
    addActivity(0,'sales_dedadd',"Sales Invoice Head Deleted - ".$headname, 7);
        return redirect('/sales_inv_head')
        ->with('success', 'Head Deleted Successfully!');
}
    }
    function updatesalesinv_head(Request $request){
        $id = $request->input('id');
        $name = $request->input('name');
        $type = $request->input('type');     
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
      
        try {
            DB::connection($user_db_conn_name)->table('sales_dedadd')->where('id', $id)->update(['name' => $name,'type'=>$type]);
            addActivity($id,'sales_dedadd',"Sales Invoice Head Updated", 7);
            return redirect('/sales_inv_head')
                ->with('success', 'Invoice Head Updated successfully!');
        } catch (\Exception $e){
         if($e->getCode() == 23000){
  return redirect('/sales_inv_head')
                ->with('error', 'Invoice Head Already Exists With Same Name!');
        }else{
              return redirect('/sales_inv_head')
                ->with('error', 'Error While Updating Invoice Head!');
        }
        }
    }
    function edit_sales_inv_head(Request $request){
        $id = $request->get('id');
        $data =array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('sales_dedadd')->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sales_dedadd')->where('id','=',$id)->get();
        return  view('layouts.sales.inv_head')->with('data',json_encode($data));
    }
    function addsalesinv_head(Request $request){
        $name = $request->input('name');
        $type = $request->input('type');     
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data=['name'=>$name,'type'=>$type
    ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
           $sales_id = DB::connection($user_db_conn_name)->table('sales_dedadd')->insertGetId($data);
            addActivity($sales_id,'sales_dedadd',"New Sale Invoice Head Created",7);

            return redirect('/sales_inv_head')
                ->with('success', 'Invoice Head Created successfully!');
        } catch (\Exception $e){
         if($e->getCode() == 23000){
  return redirect('/sales_inv_head')
                ->with('error', 'Invoice Head Already Exists!');
        }else{
              return redirect('/sales_inv_head')
                ->with('error', 'Error While Creating inv_head!');
        }
        }
}
}

