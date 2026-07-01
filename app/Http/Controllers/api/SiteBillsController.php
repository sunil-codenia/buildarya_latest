<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;


class SiteBillsController extends Controller
{
    //
    public function get_site_bill_work(Request $request)
    {
        $conn = $request->conn;
        $query = "SELECT bw.name,br.work_id, br.id,br.rate,bw.unit, br.site_id FROM bills_rate as br INNER JOIN bills_work as bw ON bw.id = br.work_id ORDER BY bw.name";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }
    public function get_site_bill_work_name(Request $request)
    {
        $conn = $request->conn;

        $query = "SELECT id,name FROM bills_work";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }
    public function get_bill_parties(Request $request)
    {
        $conn = $request->conn;
        $data = DB::connection($conn)->table('bills_party')->where('status','Active')->orWhere('status','Pending')->get();
        return json_encode($data);
    }
    public function get_bill_entries(Request $request)
    {
        $conn = $request->conn;
        $site_id = $request->site_id;
        $uid = $request->uid;
        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $user_details = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $view_duration = getUserViewDuration($user_details, $conn);
        $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        $filters = array();
        if ($visiblity_at_site == 'current') {
            $filters = [['new_bill_entry.site_id', '=', $site_id]];
        } 
        $data = DB::connection($conn)->table('new_bill_entry')->select('new_bill_entry.*')->where($filters)->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])->limit(200)->orderBy('new_bill_entry.create_datetime', 'desc')->get();      
        return json_encode($data);
    }
    public function get_bill_item_entries(Request $request)
    {
        $conn = $request->conn;
        $site_id = $request->site_id;
        $uid = $request->uid;
        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $user_details = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $view_duration = getUserViewDuration($user_details, $conn);
        $visiblity_at_site = $role_details ? $role_details->visiblity_at_site : 'all';
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        $filters = array();
        if ($visiblity_at_site == 'current') {
            $filters = [['new_bill_entry.site_id', '=', $site_id]];
        } 
        $data = DB::connection($conn)->table('new_bills_item_entry')->select('new_bills_item_entry.*')->where($filters)->leftJoin('new_bill_entry', 'new_bill_entry.id', '=', 'new_bills_item_entry.bill_id')->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])->orderBy('new_bill_entry.create_datetime', 'desc')->get();      
        return json_encode($data);
    }
    public function addbillparty(Request $request)
    {

        $conn = $request->conn;
        $name = $request->name;
        $address = $request->address;
        $panno = $request->panno;
        $bank_ac = $request->bank_ac;
        $ifsc = $request->ifsc;
        $bankname = $request->bankname;
        $site_id = $request->site_id;
        $uid = $request->uid;
        $ac_holder_name = $request->ac_holder_name;
        $create_datetime = $request->create_datetime;
        $role_id = getAppRoleByUId($uid, $conn);
        $status = getAppInitialEntryStatusByRole($role_id, $conn);
        $data = [
            'name' => $name,
            'address' => $address,
            'panno' => $panno,
            'bank_ac' => $bank_ac,
            'ifsc' => $ifsc,
            'bankname' => $bankname,
            'status' => $status,
            'site_id' => $site_id,
            'ac_holder_name' => $ac_holder_name,
            'create_datetime' => $create_datetime,
        ];

        $result = array();
        try {
            $id =  DB::connection($conn)->table('bills_party')->insertGetId($data);
            addActivity($id, 'bills_party', "New Bill Party Created - ".$name, 4,$uid,$conn);
            $result['status'] = 'Ok';
            $result['status_code'] = "200";
            $result['message'] = 'Bill Party Created Successfully!';
            $result['inserted_id'] = $id;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                $result['status'] = 'Failed';
                $result['status_code'] = "300";
                $result['message'] = 'Bill Party Already Exists!';
            } else {
                $result['status'] = 'Failed';
                $result['status_code'] = "400";
                $result['message'] = 'Error While Creating Bill Party!';
            }
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }

    public function addnewbill(Request $request)
    {
        $bill_items = array();

        $user_db_conn_name = $request->conn;
        $user_id = $request->user_id;
        $role_id = getAppRoleByUId($user_id, $user_db_conn_name);
        $status = getAppInitialEntryStatusByRole($role_id, $user_db_conn_name);
        $bill_period = $request->bill_period;
        $party_id = $request->party_id;
        $location = $request->location;
        $bill_site_id = $request->bill_site_id;
        $bill_date = $request->bill_date;
        $remark = $request->remark;

        $role_details = getAppRoleDetailsById($role_id, $user_db_conn_name);
        $user_details = DB::connection($user_db_conn_name)->table('users')->where('id', $user_id)->first();
        $add_duration = getUserAddDuration($user_details, $user_db_conn_name);
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = substr($duration['today'], 0, 10);
        if (strtotime($bill_date) < strtotime($min_date) || strtotime($bill_date) > strtotime($max_date)) {
            $result = array();
            $result['status'] = 'Failed';
            $result['message'] = "You don't have permission to add entry for date: " . $bill_date;
            $response = array();
            array_push($response, $result);
            return json_encode($response);
        }

        $bill_no = getLatestBillNoForApp($user_db_conn_name);
        $items = json_decode(stripslashes($request->items));
        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $party_id)->get()[0];
        if ($party_status->status == 'Active') {
            $length = count($items);
            $amount = 0;
     try {
            for ($i = 0; $i < $length; $i++) {
                $amount += ($items[$i]->rate * $items[$i]->qty);
            }
            $billdata = [
                'party_id' => $party_id,
                'bill_no' => $bill_no,
                'site_id' => $bill_site_id,
                'billdate' => $bill_date,
                'bill_period' => $bill_period,
                'user_id' => $user_id,
                'location'=>$location,
                'status' => $status,
                'amount' => $amount,
                'remark' => $remark,
            ];
            $bill_id = DB::connection($user_db_conn_name)->table('new_bill_entry')->insertGetId($billdata);
            addActivity($bill_id, 'new_bill_entry', "Add New Bill Created Of Amount - ".$amount, 4,$user_id,$user_db_conn_name);

            for ($i = 0; $i < $length; $i++) {
                $rawd = [
                    'work_id' => $items[$i]->work_id,
                    'unit' => $items[$i]->unit,
                    'rate' => $items[$i]->rate,
                    'qty' => $items[$i]->qty,
                    'amount' => $items[$i]->rate * $items[$i]->qty,
                    'bill_id' => $bill_id
                ];
                array_push($bill_items, $rawd);
            }
       
                DB::connection($user_db_conn_name)->table('new_bills_item_entry')->insert($bill_items);
                if ($status == 'Approved') {
                    $this->approve_bill($bill_id, $user_db_conn_name);
                }
                $result['status'] = 'Ok';
                $result['message'] = 'Bill Created successfully!';
                $result['inserted_id'] = $bill_id;
                $result['bill_no'] = $bill_no;
            } catch (\Exception $e) {

                $result['status'] = 'Failed';
                $result['message'] = 'Error While Creating Bill. Please Try Again After Reconciling The Statement.!';
                    //    $result['message'] = $e->getMessage();
            }
        } else {
            $result['status'] = 'Failed';
            $result['message'] = 'Bill Party Is Not Active!';
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }


    public function approve_bill($id, $conn)
    {
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->get()[0];
        DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->update(['status' => 'Approved']);
        addActivity($id, 'new_bill_entry', "Bill Status Approved", 4,0,$conn);
        $party_statement = [
            'party_id' => $bill->party_id,
            'type' => 'Debit',
            'particular' => $bill->bill_no,
            'bill_no' => $id,
            'create_datetime' => $bill->create_datetime
        ];
        DB::connection($conn)->table('bill_party_statement')->where('bill_no', $id)->delete();

        DB::connection($conn)->table('bill_party_statement')->insert($party_statement);
    }



    public function getBillPartyBalance(Request $request)
    {
        $user_db_conn_name = $request->conn;
        $id = $request->id;
     $statement = DB::connection($user_db_conn_name)
                    ->table('bill_party_statement')
                    ->where('bill_party_statement.party_id', $id)                  
                    ->orderBy('bill_party_statement.id', 'asc')->get();
                $data = array();
                $total_credit = 0;
                $total_debit = 0;
                foreach ($statement as $statem) {
                    if ($statem->type == 'Credit') {
                        if (!is_null($statem->expense_id)) {
                            $expense = DB::connection($user_db_conn_name)->table('expenses')->join('users','users.id','=','expenses.user_id')->join('sites','sites.id','=','expenses.site_id')->select('expenses.*','sites.name as site' , 'users.name as user')->where('expenses.id', $statem->expense_id)->get()[0];
                            $amount = $expense->amount;
                            $total_credit += $amount;
                            $dat = ['date' => $expense->date, 'ref' => 'Expense', 'ref_no' => '', 'user_name' => $expense->user, 'site_name' => $expense->site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $expense->image];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->get()[0];
                            $amount = $payment->amount;
                            $total_credit += $amount;
                            $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => ''];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_voucher_id)) {
                            $pv = DB::connection($user_db_conn_name)->table('payment_vouchers')->join('users','users.id','=','payment_vouchers.created_by')->join('sites','sites.id','=','payment_vouchers.site_id')->select('payment_vouchers.*','sites.name as site' , 'users.name as user')->where('payment_vouchers.id', $statem->payment_voucher_id)->get()[0];
                            $amount = $pv->amount;
                            $total_credit += $amount;
                            $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $pv->user, 'site_name' =>  $pv->site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $pv->image];
                            array_push($data,$dat);
                        }
                    } else {
                        if (!is_null($statem->bill_no)) {
                            $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->join('users','users.id','=','new_bill_entry.user_id')->join('sites','sites.id','=','new_bill_entry.site_id')->select('new_bill_entry.*','sites.name as site' , 'users.name as user')->where('new_bill_entry.id', $statem->bill_no)->get()[0];
                            $amount = $bill->amount;
                            $total_debit += $amount;
                            $dat = ['date' => $bill->billdate, 'ref' => 'Site Bill', 'ref_no' => $bill->bill_no, 'user_name' => $bill->user, 'site_name' => $bill->site, 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular,'image'=>''];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->get()[0];
                            $amount = $payment->amount;
                            $total_debit += $amount;
                            $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular, 'image' => ''];
                            array_push($data,$dat);
                        }
                    }
                }
                usort($data, function($a, $b) {
                    $dateA = strtotime($a['date']);
                    $dateB = strtotime($b['date']);
                    return $dateA - $dateB;
                });

      $fdata = array();
      $fdata['statement'] = $data;
        return json_encode($fdata);
    }
}