<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function get_expense_head(Request $request)
    {
        $conn = $request->conn;
        $user_db_conn_name = $conn;
        $data = DB::connection($user_db_conn_name)->table('expense_head')->get();
        return json_encode($data);
    }
    public function get_expense_party(Request $request)
    {
        $conn = $request->conn;
        $user_db_conn_name = $conn;
        $data = DB::connection($user_db_conn_name)->table('expense_party')->where('status','Active')->orWhere('status','Pending')->get();
        return json_encode($data);
    }
    public function get_expense(Request $request)
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
            $filters = [['expenses.site_id', '=', $site_id]];
        } 
        $data = DB::connection($conn)->table('expenses')->select('expenses.*')->where($filters)->whereBetween('expenses.create_datetime', [$min_date, $max_date])->limit(200)->orderBy('expenses.create_datetime', 'desc')->get();      
        return json_encode($data);
    }
    public function addexpenseparty(Request $request)
    {
        $name = $request->name;
        $pan = $request->pan_no;
        $address = $request->address;
        $site_id = $request->site_id;
        $uid = $request->uid;
        $conn = $request->conn;
        $role_id = getAppRoleByUId($uid, $conn);
        $status = getAppInitialEntryStatusByRole($role_id, $conn);

        $data = [
            'name' => $name,
            'address' => $address,
            'pan_no' => $pan,
            'site_id' => $site_id,
            'status' => $status
        ];

        $result = array();

        try {
            $id =  DB::connection($conn)->table('expense_party')->insertGetId($data);
            addActivity($id, 'expense_party', "New Expense Party Created - ".$name, 2,$uid,$conn);
            $result['status'] = 'Ok';
            $result['status_code'] = "200";
            $result['message'] = 'Expense Party Created Successfully!';
            $result['inserted_id'] = $id;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                $result['status'] = 'Failed';
                $result['status_code'] = "300";
                $result['message'] = 'Expense Party Already Exists!';
            } else {
                $result['status'] = 'Failed';
                $result['status_code'] = "400";
                $result['message'] = 'Error While Creating Expense Party!';
            }
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }
    
    public function addexpense(Request $request)
    {

   
        $conn = $request->conn;
        $particular = $request->particular;
        $amount = $request->amount;
        $remark = $request->remark;
        $head_id = $request->head_id;
        $party_id = $request->party_id;
        $party_type = $request->party_type;
        $date = $request->date;
        $site_id = $request->site_id;
        $uid = $request->uid;
        $location = $request->location;

        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $user_details = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $add_duration = getUserAddDuration($user_details, $conn);
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = substr($duration['today'], 0, 10);
        if (strtotime($date) < strtotime($min_date) || strtotime($date) > strtotime($max_date)) {
            $result = array();
            $result['status'] = 'Failed';
            $result['message'] = "You don't have permission to add entry for date: " . $date;
            $response = array();
            array_push($response, $result);
            return json_encode($response);
        }

        $imagePath = "images/expense.png";
        try {
            if (isset($request->image)) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
                $request->image->move(public_path('images/app_images/'.$conn.'/expense'), $imageName);
                $imagePath = "images/app_images/".$conn."/expense/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
        } catch (\Exception $e) {
            $imagePath = "images/expense.png";
        }



        $role_id = getAppRoleByUId($uid, $conn);
        $status = getAppInitialEntryStatusByRole($role_id, $conn);
        if(is_machinery_head($head_id,$conn) || is_asset_head($head_id,$conn)){
            $status = 'Pending';
        }
        $data = [
            'site_id' => $site_id,
            'user_id' => $uid,
            'party_id' => $party_id,
            'party_type'=>$party_type,
            'head_id' => $head_id,
            'particular' => $particular,
            'amount' => $amount,
            'remark' => $remark,
            'image' => $imagePath,
            'location' => $location,
            'status' => $status,
            'date' => $date,
        ];
        try {
            $id =  DB::connection($conn)->table('expenses')->insertGetId($data);
            addActivity($id, 'expenses', "New Expense Created Of Amount - ".$amount, 2,$uid,$conn);
            $result['status'] = 'Ok';
            $result['message'] = 'Expense Created Successfully!';
            $result['inserted_id'] = $id;
            $result['image'] = $imagePath;

            if($status == 'Approved'){
                   if ($party_type == 'bill') {
                    $party_status = DB::connection($conn)->table('bills_party')->where('id', '=', $party_id)->get()[0];
                } else {
                    $party_status = DB::connection($conn)->table('expense_party')->where('id', '=', $party_id)->get()[0];
                }
                if ($party_status->status == 'Active') {
                    $this->approve_expense($result['inserted_id'],$conn, $uid);
                }
            }
        } catch (\Exception $e) {
            $result['status'] = 'Failed';
            $result['message'] = 'Error While Creating Expense !';
        }
        $response = array();
        array_push($response, $result);
        return json_encode($response);
    }
    public function approve_expense($id, $user_db_conn_name, $uid)
    {
  $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->get()[0];
        DB::connection($user_db_conn_name)->table('expenses')->where('id', '=', $id)->update(['status' => 'Approved']);
        addActivity($id, 'expenses', "Expense Status Approved", 2,$uid,$user_db_conn_name);
        $site_trans = [
            'site_id' => $expense->site_id,
            'type' => 'Debit',
            'expense_id' => $id,
            'create_datetime' => $expense->create_datetime
        ];
        DB::connection($user_db_conn_name)->table('sites_transaction')->where('expense_id', $id)->delete();
        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($site_trans);

        if ($expense->party_type == 'bill') {
            $site_trans = [
                'party_id' => $expense->party_id,
                'type' => 'Credit',
                'particular' =>  $expense->particular,
                'expense_id' => $id,
                'create_datetime' => $expense->create_datetime
            ];
            DB::connection($user_db_conn_name)->table('bill_party_statement')->where('expense_id', $id)->delete();

            DB::connection($user_db_conn_name)->table('bill_party_statement')->insert($site_trans);
        }
        $head_id = $expense->head_id;

        if (is_asset_head($head_id, $user_db_conn_name)) {
            $asset = [
                'cost_price' => $expense->amount,
                'name' =>  $expense->particular,
                'expense_id' => $id,
                'site_id' => $expense->site_id,
                'status' => 'Active',
                'create_datetime' => $expense->create_datetime
            ];
            $asset_id = DB::connection($user_db_conn_name)->table('assets')->insertGetId($asset);
            addActivity($asset_id,'assets',"New Assets Created Via Expense",5,$uid,$user_db_conn_name);

        }
        if (is_machinery_head($head_id, $user_db_conn_name)) {
            $machine = [
                'qty' => '1',
                'name' =>  $expense->particular,
                'expense_id' => $id,
                'site_id' => $expense->site_id,
                'status' => 'Active',
                'create_datetime' => $expense->create_datetime
            ];
$machine_id = DB::connection($user_db_conn_name)->table('machinery_details')->insertGetId($machine);
            addActivity($machine_id, 'machinery_details', "New Machinery Created Via Expense", 6,$uid,$user_db_conn_name);
        }
    }
}
