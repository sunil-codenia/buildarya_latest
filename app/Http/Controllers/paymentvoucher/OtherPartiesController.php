<?php

namespace App\Http\Controllers\paymentvoucher;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OtherPartiesController extends Controller
{
    //
    public function index(Request $request){
        $data =array();
            $user_db_conn_name = $request->session()->get('comp_db_conn_name');
 
            $data = DB::connection($user_db_conn_name)->table('other_parties')
                ->leftJoin('expense_head', 'expense_head.id', '=', 'other_parties.cost_category_id')
                ->select('other_parties.*', 'expense_head.name as category_name')
                ->get();
            
            $cost_categories = getallCostCategories();

            return view('layouts.paymentvoucher.otherparty')
                ->with('data', json_encode($data))
                ->with('cost_categories', $cost_categories);
    }
    public function addotherparty(Request $request){
        $name = $request->input('name');
        $name = $request->input('name');
        $panno = $request->input('panno');
        $address = $request->input('address');
        $bank_ac = $request->input('bank_ac');
        $bank_ifsc = $request->input('bank_ifsc');
        $bank_name = $request->input('bank_name');
        $bank_ac_holder = $request->input('bank_ac_holder');
        $cost_category_id = $request->input('cost_category_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data=['name'=>$name,'panno'=>$panno,'address'=>$address,'bank_ac'=>$bank_ac,'bank_ifsc'=>$bank_ifsc,'bank_name'=>$bank_name,'bank_ac_holder'=>$bank_ac_holder, 'cost_category_id'=>$cost_category_id
    ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
           $id = DB::connection($user_db_conn_name)->table('other_parties')->insertGetId($data);
            addActivity($id,'other_parties',"New Other Party Created",8);
            DB::connection($user_db_conn_name)->table('contact_profile')->insert(['comp_name' => $name, 'contact_name' => $name, 'category' => 'Other Party']);

            return redirect('/otherparty')
                ->with('success', 'Party Created successfully!');
        } catch (\Exception $e){
         if($e->getCode() == 23000){
  return redirect('/otherparty')
                ->with('error', 'Party Already Exists!');
        }else{
              return redirect('/otherparty')
                ->with('error', 'Error While Creating other Party!');
        }
        }
         
    }
    public function updateotherparty(Request $request){
        $id = $request->input('id');
        $name = $request->input('name');
        $panno = $request->input('panno');
        $address = $request->input('address');
        $bank_ac = $request->input('bank_ac');
        $bank_ifsc = $request->input('bank_ifsc');
        $bank_name = $request->input('bank_name');
        $bank_ac_holder = $request->input('bank_ac_holder');
        $cost_category_id = $request->input('cost_category_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
         
         try {
            DB::connection($user_db_conn_name)->table('other_parties')->where('id', $id)->update(['name'=>$name,'panno'=>$panno,'address'=>$address,'bank_ac'=>$bank_ac,'bank_ifsc'=>$bank_ifsc,'bank_name'=>$bank_name,'bank_ac_holder'=>$bank_ac_holder, 'cost_category_id'=>$cost_category_id]);
            addActivity($id,'other_parties',"Other Party Data Updated",8);
 
            return redirect('/otherparty')
                 ->with('success', 'Party Updated successfully!');
         } catch (\Exception $e){
          if($e->getCode() == 23000){
   return redirect('/otherparty')
                 ->with('error', 'Party Already Exists!');
         }else{
               return redirect('/otherparty')
                 ->with('error', 'Error While Updating other Party!');
         }
         }
          
    }
    public function edit_otherparty(Request $request){
        $id = $request->get('id');
        $data =array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = DB::connection($user_db_conn_name)->table('other_parties')
            ->leftJoin('expense_head', 'expense_head.id', '=', 'other_parties.cost_category_id')
            ->select('other_parties.*', 'expense_head.name as category_name')
            ->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('other_parties')->where('id','=',$id)->get();
        $cost_categories = getallCostCategories();
        
        return view('layouts.paymentvoucher.otherparty')
            ->with('data', json_encode($data))
            ->with('cost_categories', $cost_categories);
    }
    public function delete_otherparty(Request $request){
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
       $check = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('party_id','=',$id)->where('party_type','=','other')->get();
       $other_parties = DB::connection($user_db_conn_name)->table('other_parties')->where('id','=',$id)->get()[0]->name;
       if(Count($check) > 0){
    return redirect('/otherparty')
    ->with('error', 'Party Is In Use!');
   }else{
    DB::connection($user_db_conn_name)->table('other_parties')->where('id','=',$id)->delete();
    addActivity(0,'other_parties',"Other Party Deleted - ".$other_parties,8);

        return redirect('/otherparty')
        ->with('success', 'Party Deleted Successfully!');
}
    
    }
    public function update_other_party_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $id)->update(['status' => $status]);
        addActivity(0,'other_parties',"Other Party Status Updated To - ".$status,8);

        if ($status == 'Active') {
            return redirect('/otherparty')
                ->with('success', 'Party Activated!');
        } else {
            return redirect('/otherparty')
                ->with('success', 'Party Deactivated!');
        }
    }
   
}