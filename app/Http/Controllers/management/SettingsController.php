<?php

namespace App\Http\Controllers\management;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Session;
class SettingsController extends Controller
{
    public function changetheme(Request $request){
        $themecolor = $request->color;
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'theme'],
            ['value' => $themecolor, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );        
    $settings = DB::connection($db_conn)->table('settings')->select('name','value')->where('name','theme')->first();
    addActivity(0,'settings',"System Theme Changed",9);

    $color = $settings->value;
if($request->session()->has('theme')){
    $request->session()->forget('theme');
    $request->session()->push('theme',$color);
}else{
    $request->session()->push('theme',$color);
    }
    return redirect('/dashboard');
}
public function index(Request $request){
    $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('settings')->get();
    return  view('layouts.management.settings')->with('data',json_encode($data));
}
    public function menutheme(Request $request){
        $themecolor = $request->themecolor;
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'menutheme'],
            ['value' => $themecolor, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );        
    $settings = DB::connection($db_conn)->table('settings')->select('value')->where('name','menutheme')->first();
    $themecolor = $settings->value;
if($request->session()->has('menutheme')){
    $request->session()->forget('menutheme');
    $request->session()->push('menutheme',$themecolor);
}else{
    $request->session()->push('menutheme',$themecolor);
}

return redirect('/dashboard');
    }
    public function updatebillsequence(Request $request){
        $bill_sequence = $request->get('bill_sequence');
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'bill_sequence'],
            ['value' => $bill_sequence, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );    
        addActivity(0,'settings',"Site Bills Sequence Changed",9);    
        return redirect('/settings')->with('success', 'Bill Sequence Updated!');; 
    }

    public function updatecurrency(Request $request){
        $currency = $request->get('currency_name');
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'currency'],
            ['value' => $currency, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );       
        addActivity(0,'settings',"System Currency Changed",9); 
        return redirect('/settings')->with('success', 'Bill Sequence Updated!');; 
    }
    public function updateuploadsrc(Request $request){
        $expense_upload_src = $request->get('expense_upload_src');
        $material_first_upload_src = $request->get('material_first_upload_src');
        $material_second_upload_src = $request->get('material_second_upload_src');
        
        $machinery_doc_upload_src = $request->get('machinery_doc_upload_src');
        $machinery_service_upload_src = $request->get('machinery_service_upload_src');
        $document_upload_src = $request->get('document_upload_src');
        
        $db_conn = $request->session()->get('comp_db_conn_name');

        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'expense_upload_src'],
            ['value' => $expense_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );   
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'material_first_upload_src'],
            ['value' => $material_first_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );   
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'material_second_upload_src'],
            ['value' => $material_second_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );       
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'machinery_service_upload_src'],
            ['value' => $machinery_service_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );       
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'machinery_doc_upload_src'],
            ['value' => $machinery_doc_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );       
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'document_upload_src'],
            ['value' => $document_upload_src, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );       
        addActivity(0,'settings',"Mobile App Upload Source Updated!",9); 
        return redirect('/settings')->with('success', 'Upload Sources Updated!');; 

    }

    

    public function updatepaymentvouchersequence(Request $request){
        $payment_voucher_sequence = $request->get('payment_voucher_sequence');
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'payment_voucher_sequence'],
            ['value' => $payment_voucher_sequence, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );        
        addActivity(0,'settings',"Payment Voucher Sequence Changed",9);
        return redirect('/settings')->with('success', 'Payment Voucher Sequence Updated!');; 
    }
    
    public function changecolor(Request $request){
        $primary = $request->input('primary_color');
        $secondry = $request->input('secondry_color');
        $start = $request->input('gradient_start');
        $end = $request->input('gradient_end');
        $db_conn = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $mytime = Carbon::now();
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'primary_color'],
            ['value' => $primary, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );     
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'secondry_color'],
            ['value' => $secondry, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );     
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'gradient_start'],
            ['value' => $start, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );     
        DB::connection($db_conn)->table('settings')->updateOrInsert(
            ['name' => 'gradient_end'],
            ['value' => $end, 'uid' =>$uid,'updated_at'=> $mytime->toDateTimeString()]
        );        
    $primary_color = DB::connection($db_conn)->table('settings')->select('name','value')->where('name','primary_color')->first();
    $pcolor = $primary_color->value;
if($request->session()->has('primary_color')){
    $request->session()->forget('primary_color');
    $request->session()->push('primary_color',$pcolor);
}else{
    $request->session()->push('primary_color',$pcolor);
}
$secondry_color = DB::connection($db_conn)->table('settings')->select('name','value')->where('name','secondry_color')->first();
$scolor = $secondry_color->value;
if($request->session()->has('secondry_color')){
$request->session()->forget('secondry_color');
$request->session()->push('secondry_color',$scolor);
}else{
$request->session()->push('secondry_color',$scolor);
}
$settings = DB::connection($db_conn)->table('settings')->select('name','value')->where('name','gradient_start')->first();
$gstart = $settings->value;
if($request->session()->has('gradient_start')){
$request->session()->forget('gradient_start');
$request->session()->push('gradient_start',$gstart);
}else{
$request->session()->push('gradient_start',$gstart);
}
$gradient_end = DB::connection($db_conn)->table('settings')->select('name','value')->where('name','gradient_end')->first();
$color = $gradient_end->value;
if($request->session()->has('gradient_end')){
$request->session()->forget('gradient_end');
$request->session()->push('gradient_end',$color);
}else{
$request->session()->push('gradient_end',$color);
}
addActivity(0,'settings',"System Theme Colors Changed",9);
return redirect('/dashboard')
        ->with('success', 'Colors Updated Successfully!');
    }
}

