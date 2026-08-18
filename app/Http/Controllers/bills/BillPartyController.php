<?php

namespace App\Http\Controllers\bills;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use File;
use Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SiteBillExport;
use App\Exports\BillpartylazerExport;
use Barryvdh\DomPDF\Facade\Pdf;

class BillPartyController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $query = DB::connection($user_db_conn_name)->table('bills_party')
            ->leftJoin('expense_head', 'expense_head.id', '=', 'bills_party.cost_category_id')
            ->select('bills_party.*', 'expense_head.name as category_name');

        if ($request->get('status')) {
            $query->where('bills_party.status', $request->get('status'));
        }

        if ($request->get('site_id') && $request->get('site_id') != 'all') {
            $query->where('bills_party.site_id', $request->get('site_id'));
        }

        if ($request->get('from_date') && $request->get('to_date')) {
            $from = date('Y-m-d 00:00:00', strtotime($request->get('from_date')));
            $to = date('Y-m-d 23:59:59', strtotime($request->get('to_date')));
            $query->whereBetween('bills_party.create_datetime', [$from, $to]);
        } else {
            $view_duration = $request->session()->get('view_duration');
            $dates = getdurationdates($view_duration);
            $min_date = $dates['min'];
            $max_date = $dates['max'];
            $query->whereBetween('bills_party.create_datetime', [$min_date, $max_date]);
        }

        $data = $query->get();
        $cost_categories = getallCostCategories();

        return view('layouts.bills.billparty')
            ->with('data', json_encode($data))
            ->with('cost_categories', $cost_categories);
    }
    public function bill_party_payment(Request $request){
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $request->query->get('id');
        $party = DB::connection($user_db_conn_name)->table('bills_party')->where('id',$id)->first();
        $bill_party_name = $party ? $party->name : '';
        $data = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('party_id',$id)->get();
        return  view('layouts.bills.billparty_payments',compact(['id','bill_party_name','data']));
    }
    public function addbillparty(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $panno = $request->input('panno');

        $bank_ac = $request->input('bank_ac');
        $ifsc = $request->input('ifsc');
        $bankname = $request->input('bankname');
        $ac_holder_name = $request->input('ac_holder_name');
        $cost_category_id = $request->input('cost_category_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $comp_id = $request->session()->get('comp_id');

        $qrCodePath = null;
        if ($request->hasFile('qr_code')) {
            $file = $request->file('qr_code');
            $dirPath = public_path("images/app_images/{$comp_id}/qrcode");
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
            $fileName = time() . '_qr_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dirPath, $fileName);
            $qrCodePath = "images/app_images/{$comp_id}/qrcode/{$fileName}";
        }

        $data = [
            'name' => $name, 
            'address' => $address, 
            'panno' => $panno, 
            'bank_ac' => $bank_ac, 
            'ifsc' => $ifsc, 
            'bankname' => $bankname, 
            'ac_holder_name' => $ac_holder_name, 
            'cost_category_id' => $cost_category_id,
            'qr_code' => $qrCodePath,
            'create_datetime' => date('Y-m-d H:i:s')
        ];

        try {
            DB::connection($user_db_conn_name)->table('bills_party')->insert($data);
            return redirect('/billparty')
                ->with('success', 'Bill Party Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/billparty')
                    ->with('error', 'Bill Party Already Exists!');
            } else {
                return redirect('/billparty')
                    ->with('error', 'Error While Creating Bill Party!');
            }
        }
    }
    public function addBillPartyBalance(Request $request)
    {
        $id = $request->input('party_id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $date = $request->input('date');
        $data = [
            'party_id' => $id,
            'amount' => $amount,
            'remark' => $remark,
            'date' => $date
        ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $pay_id =   DB::connection($user_db_conn_name)->table('bill_party_payments')->insertGetId($data);
            addActivity($pay_id,'bill_party_payments',"Bill Party Payment Done Of Amount - ".$amount,4);
            $tdata = [
                'party_id' => $id,
                'type' => 'Credit',
                'payment_id' => $pay_id,
                'particular' => $remark
            ];
            DB::connection($user_db_conn_name)->table('bill_party_statement')->where('payment_id', $pay_id)->delete();

            DB::connection($user_db_conn_name)->table('bill_party_statement')->insert($tdata);

            return redirect('/bill_party_payment?id='.$id)
                ->with('success', 'Bill Party Balance Credit successfully!');
        } catch (\Exception $e) {
            return redirect('/bill_party_payment?id='.$id)
                ->with('error', 'Error While Bill Party Balance Credit!');
        }
    }
    public function updateBillPartyBalance(Request $request){
        $id = $request->input('id');
        $party_id = $request->input('party_id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $date = $request->input('date');
        $data = [        
            'amount' => $amount,
            'remark' => $remark,
            'date' => $date
        ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
         DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id',$id)->update($data);
            addActivity($id,'bill_party_payments',"Bill Party Payment Updated Of Amount - ".$amount,4);
            $tdata = [               
                'particular' => $remark
            ];
            DB::connection($user_db_conn_name)->table('bill_party_statement')->where('payment_id', $id)->update($tdata);

            return redirect('/bill_party_payment?id='.$party_id)
                ->with('success', 'Bill Party Balance Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/bill_party_payment?id='.$party_id)
                ->with('error', 'Error While Bill Party Balance Update!');
        }
    }
    public function updatebillparty(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $panno = $request->input('panno');
        $address = $request->input('address');

        $bank_ac = $request->input('bank_ac');
        $ifsc = $request->input('ifsc');
        $bankname = $request->input('bankname');
        $ac_holder_name = $request->input('ac_holder_name');
        $cost_category_id = $request->input('cost_category_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $comp_id = $request->session()->get('comp_id');

        $updateData = [
            'name' => $name, 
            'panno' => $panno, 
            'address' => $address,
            'bank_ac' => $bank_ac, 
            'ifsc' => $ifsc, 
            'bankname' => $bankname, 
            'ac_holder_name' => $ac_holder_name, 
            'cost_category_id' => $cost_category_id
        ];

        if ($request->hasFile('qr_code')) {
            $party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $id)->first();
            if ($party && !empty($party->qr_code) && File::exists(public_path($party->qr_code))) {
                File::delete(public_path($party->qr_code));
            }
            $file = $request->file('qr_code');
            $dirPath = public_path("images/app_images/{$comp_id}/qrcode");
            if (!File::exists($dirPath)) {
                File::makeDirectory($dirPath, 0755, true);
            }
            $fileName = time() . '_qr_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move($dirPath, $fileName);
            $updateData['qr_code'] = "images/app_images/{$comp_id}/qrcode/{$fileName}";
        }

        DB::connection($user_db_conn_name)->table('bills_party')->where('id', $id)->update($updateData);
        addActivity($id,'bills_party',"Bill Party Data Updated.",4);
        return redirect('/billparty')->with('success', 'Bill Party Updated successfully!');
    }
    public function edit_billparty(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $view_duration = $request->session()->get('view_duration');
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];

        $data['data'] = DB::connection($user_db_conn_name)->table('bills_party')
            ->leftJoin('expense_head', 'expense_head.id', '=', 'bills_party.cost_category_id')
            ->select('bills_party.*', 'expense_head.name as category_name')
            ->whereBetween('bills_party.create_datetime', [$min_date, $max_date])
            ->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->get();
        $cost_categories = getallCostCategories();
        
        return view('layouts.bills.billparty')
            ->with('data', json_encode($data))
            ->with('cost_categories', $cost_categories);
    }
    public function delete_billparty(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('party_id', '=', $id)->get();
        $party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->first();
        $billsparty_delete = $party ? $party->name : '';
        if (Count($check) > 0) {
            return redirect('/billparty')
                ->with('error', 'Bill Party Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->delete();
            addActivity(0,'bills_party',"Bill Party Deleted - ".$billsparty_delete,4);

            return redirect('/billparty')
                ->with('success', 'Bill Party Deleted Successfully!');
        }
    }
    public function update_bill_party_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $party =  DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->first(); 
        DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id,'bills_party',"Bill Party Status Updated To - ".$status,4);

        if ($status == 'Active') {
            if($party->status == 'Pending'){
                DB::connection($user_db_conn_name)->table('contact_profile')->insert(['comp_name'=>$party->name,'contact_name'=>$party->name,'category'=>'Bills Party']);
            }
            return redirect('/billparty')
                ->with('success', 'Bill Party Activated!');
        } else {
            return redirect('/billparty')
                ->with('success', 'Bill Party Deactivated!');
        }
    }

   


}
