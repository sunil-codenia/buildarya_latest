<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    //    
    public function login(Request $request)
    {
        $uid = $request->companyid;
        $uname = $request->username;
        $pass = $request->password;
        $users = DB::table('companies')->select('id', 'name', 'db_name', 'db_pass', 'db_host', 'db_port', 'status')->where('uid', $uid)->count();
        if ($users == 1) {
            $compdata = DB::table('companies')->select('id', 'uid', 'name', 'db_conn_name', 'status')->where('uid', $uid)->first();
            if ($compdata->status == 'Active') {
                $usercount = DB::connection($compdata->db_conn_name)->table('users')->where('username', $uname)->where('pass', $pass)->count();
                if ($usercount == 1) {
                    $userdata = DB::connection($compdata->db_conn_name)->table('users')->where('username', $uname)->where('pass', $pass)->first();
                    $currency = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'currency')->first();
                    $expense_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'expense_upload_src')->first();
                    $material_first_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'material_first_upload_src')->first();
                    $material_second_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'material_second_upload_src')->first();
                    $machinery_doc_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'machinery_doc_upload_src')->first();
                    $machinery_service_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'machinery_service_upload_src')->first();
                    $document_upload_src = DB::connection($compdata->db_conn_name)->table('settings')->where('name', '=', 'document_upload_src')->first();
                    $site_id = $userdata->site_id;
                    if ($userdata->status == "Active") {
                        // if ($userdata->rank == "Admin") {
                        $roledata = getAppRoleDetailsById($userdata->role_id, $compdata->db_conn_name);
                        $sitedata = getAppSiteDetailsById($site_id, $compdata->db_conn_name);

                        $roledata = DB::connection($compdata->db_conn_name)->table('roles')->where('id', '=', $userdata->role_id)->first();
                        $view_duration = $userdata->view_duration ?? $roledata->view_duration;
                        $add_duration = $userdata->add_duration ?? $roledata->add_duration;

                        $rolename = $roledata->name;
                        $entry_at_site = $roledata->entry_at_site;

                        $data = [
                            "response" => "OK",
                            "uid" => $userdata->id,
                            "name" => $userdata->name,
                            "username" => $userdata->username,
                            "role" => $userdata->role_id,
                            "site_id" => $site_id,
                            "site_address" => $sitedata->address,
                            "image" => $userdata->image,
                            "comp_uid" => $compdata->uid,
                            "currency" => $currency->value,
                            "expense_upload_src" => $expense_upload_src->value,
                            "material_first_upload_src" => $material_first_upload_src->value,
                            "material_second_upload_src" => $material_second_upload_src->value,
                            "machinery_doc_upload_src" => $machinery_doc_upload_src->value,
                            "machinery_service_upload_src" => $machinery_service_upload_src->value,
                            "document_upload_src" => $document_upload_src->value,
                            "comp_id" => $compdata->id,
                            "comp_name" => $compdata->name,
                            "site_name" => $sitedata->name,
                            "role_name" => $rolename,
                            "comp_db_conn_name" => $compdata->db_conn_name,
                            'role_id' => $userdata->role_id,
                            'view_duration' => $view_duration,
                            'add_duration' => $add_duration,
                            'entry_at_site' => $entry_at_site,
                            'subscription_plan_id' => $userdata->subscription_plan_id
                        ];

                        // } else {
                        //     return view('/login')->with('errorcode', "You Are Not Allowed To Login Using Web Portal! Please Contact Your Administration!");
                        // }


                    } else {
                        $data = [
                            "response" => "Failed",
                            "message" => "Your Account Is Inactive! Please Contact Your Administration!"
                        ];
                    }
                } else {
                    $data = [
                        "response" => "Failed",
                        "message" => "Invalid Credentials! Please Check Your Credentials!"
                    ];
                }
            } else {
                $data = [
                    "response" => "Failed",
                    "message" => "This Company Is Inactive! Please Contact Your Administration."
                ];
            }
        } else {
            $data = [
                "response" => "Failed",
                "message" => "This Company Don't Exists! Please Contact Your Administration."
            ];
        }
        return json_encode($data);
    }
    public function get_users(Request $request)
    {
        $conn = $request->post('conn');
        $query = "SELECT users.id,users.name,users.image, fcm_id, roles.name as role , sites.name as site FROM users INNER JOIN roles ON roles.id = users.role_id INNER JOIN sites ON sites.id = users.site_id";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }

    public function get_all_data(Request $request)
    {
        $result_data = array();
        $conn = $request->get('conn');
        $uid = $request->get('uid');
        $userdata = DB::connection($conn)->table('users')->where('id', $uid)->first();
        $site_id = $userdata->site_id;
        $sitedata = getAppSiteDetailsById($site_id, $conn);
        $company = DB::table('companies')->where('db_conn_name', $conn)->first();
        $comp_id = $company->id;

        $role_id = getAppRoleByUId($uid, $conn);
        $role_details = getAppRoleDetailsById($role_id, $conn);
        $rolename = $role_details->name;
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $userdata->add_duration ?? $role_details->add_duration;
        $view_duration = $userdata->view_duration ?? $role_details->view_duration;
        $visiblity_at_site = $role_details->visiblity_at_site;
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];

        $expense_filters = array();
        $mat_filters = array();
        $bill_filters = array();
        if ($visiblity_at_site == 'current') {
            $expense_filters = [['expenses.site_id', '=', $site_id]];
            $mat_filters = [['material_entry.site_id', '=', $site_id]];
            $bill_filters = [['new_bill_entry.site_id', '=', $site_id]];
        }

        if ($uid != null && $uid != ""  && $conn != null && $conn != "") {


            $result_data['sites'] = DB::connection($conn)->select("SELECT id,name FROM sites");
            $result_data['permissions'] = DB::connection($conn)->select("SELECT module_id,can_view,can_add,can_edit,can_certify,can_pay,can_delete FROM user_permission WHERE user_id = '$uid'");
            $result_data['expense_head'] = DB::connection($conn)->table('expense_head')->get();
            $result_data['expense_party'] = DB::connection($conn)->table('expense_party')->get();

            $result_data['materials'] = DB::connection($conn)->table('materials')->get();
            $result_data['material_suppliers'] = DB::connection($conn)->table('material_supplier')->select('id', 'name', 'status')->get();
            $result_data['units'] = DB::connection($conn)->table('units')->get();
            if ($entry_at_site == 'current') {
                $result_data['bill_work'] = DB::connection($conn)->select("SELECT bw.name,br.work_id, br.id,br.rate,bw.unit, br.site_id FROM bills_rate as br INNER JOIN bills_work as bw ON bw.id = br.work_id WHERE br.site_id = $site_id ORDER BY bw.name ");
            } else {
                $result_data['bill_work'] = DB::connection($conn)->select("SELECT bw.name,br.work_id, br.id,br.rate,bw.unit, br.site_id FROM bills_rate as br INNER JOIN bills_work as bw ON bw.id = br.work_id ORDER BY bw.name ");
            }
            $result_data['bill_party'] = DB::connection($conn)->table('bills_party')->get();
            $result_data['assets'] = DB::connection($conn)->table('assets')->join('asset_head', 'asset_head.id', '=', 'assets.head_id')->where('site_id', $site_id)->select('assets.*', 'asset_head.name as head_name')->get();
            $result_data['machineries'] = DB::connection($conn)->table('machinery_details')->join('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head_name')->where('machinery_details.site_id', $site_id)->get();
            $result_data['machineries_document'] = DB::connection($conn)->select("SELECT md.id,md.machinery_id,md.name,md.issue_date,md.end_date,md.create_date,md.attachment,md.remark FROM machinery_documents as md INNER JOIN machinery_details ON machinery_details.id = md.machinery_id WHERE machinery_details.site_id = '$site_id' ");
            $result_data['machineries_service'] = DB::connection($conn)->select("SELECT ms.id,users.name as user_name,ms.machinery_id,ms.next_service_on,ms.user_id,ms.maintainence_item,ms.create_date,ms.image1,ms.image2,ms.image3,ms.image4,ms.image4,ms.image5,ms.remark FROM machinery_services as ms INNER JOIN machinery_details as md ON ms.machinery_id = md.id INNER JOIN users ON ms.user_id = users.id WHERE md.site_id = '$site_id' ");
            $result_data['expenses'] = DB::connection($conn)->table('expenses')
                ->leftJoin('bills_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'bills_party.id')
                        ->where('expenses.party_type', '=', 'bill');
                })
                ->leftJoin('expense_party', function ($join) {
                    $join->on('expenses.party_id', '=', 'expense_party.id')
                        ->where('expenses.party_type', '=', 'expense');
                })
                ->leftjoin('expense_head', 'expense_head.id', '=', 'expenses.head_id')
                ->leftjoin('sites', 'sites.id', '=', 'expenses.site_id')
                ->leftjoin('users', 'users.id', '=', 'expenses.user_id')
                ->selectRaw(
                    'expenses.*, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name, sites.name as site_name, users.name as user_name,expense_head.name as head_name'
                )
                ->where($expense_filters)
                ->whereBetween('expenses.date', [$min_date, $max_date])
                ->orderBy('expenses.date', 'desc')->limit(200)->get();

            $result_data['material_entry'] = DB::connection($conn)->table('material_entry')->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')->leftjoin('units', 'units.id', '=', 'material_entry.unit')->leftjoin('users', 'users.id', '=', 'material_entry.user_id')->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')->where($mat_filters)->whereBetween('material_entry.date', [$min_date, $max_date])->orderBy('material_entry.id', 'DESC')->limit(200)->get();
            $result_data['bill_entry']  = DB::connection($conn)->table('new_bill_entry')->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')->select('new_bill_entry.*', 'sites.name as site', 'users.name as user', 'bills_party.name as party')->where($bill_filters)->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])->orderBy('new_bill_entry.billdate', 'desc')->limit(200)->get();
            $bill_item = array();
            foreach ($result_data['bill_entry'] as $bill) {
                $items = DB::connection($conn)
                    ->table('new_bills_item_entry')
                    ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                    ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                    ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                    ->get();
                foreach ($items as $item) {
                    array_push($bill_item, $item);
                }
            }
            $result_data['bill_item_entry'] = $bill_item;
            $result_data['doc_head'] = DB::connection($conn)->table('doc_head')->get();
            $result_data['doc_head_option'] = DB::connection($conn)->table('doc_head_option')->get();
            $result_data['doc_upload'] = DB::connection($conn)->table('doc_upload')->where('created_by', $uid)->limit(100)->orderBy('id', 'desc')->get();

            // $transactions = DB::connection($conn)->select("SELECT * FROM sites_transaction WHERE site_id = '$site_id'");
            // $site_trans = array();
            // foreach ($transactions as $dd) {
            //     $amount = 0;
            //     if ($dd->expense_id != null) {
            //         $amount = DB::connection($conn)->select("SELECT amount FROM expenses WHERE id = $dd->expense_id")[0]->amount;
            //     } else if ($dd->payment_id != null) {
            //         $amount = DB::connection($conn)->select("SELECT amount FROM site_payments WHERE id = $dd->payment_id")[0]->amount;
            //     } else if ($dd->payment_voucher_id != null) {
            //         $amount = DB::connection($conn)->select("SELECT amount FROM payment_vouchers WHERE id = $dd->payment_voucher_id")[0]->amount;
            //     }
            //     $dt = array();
            //     $dt = $dd;
            //     $dt->amount = $amount;
            //     array_push($site_trans, $dt);
            // }
            // $result_data['site_transactions'] = $site_trans;
            return json_encode($result_data);
        } else {
            return "Invalid Values";
        }
    }
    public function update_fcm_id(Request $request)
    {
        try {

            $conn = $request->post('conn');
            $user_id = $request->post('user_id');
            $fcm_id = $request->post('fcm_id');
            DB::connection($conn)->table('users')->where('id', '=', $user_id)->update(['fcm_id' => $fcm_id]);
            $data = [
                "status" => "Ok",
                "status_code" => "200",
                "message" => "FCM Code Updated Successfully!"
            ];
        } catch (\Exception $e) {
            $data = [
                "status" => "Failed",
                "status_code" => "300",
                "message" => "FCM Code Updation Failed!"
            ];
        }
        return json_encode($data);
    }
    public function update_profile_picture(Request $request)
    {
        $conn = $request->post('conn');
        $user_id = $request->post('user_id');
        $imagePath = "images/noprofile.jpg";
        try {
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$conn.'/users'), $imageName);
            $imagePath = "images/app_images/".$conn."/users/" . $imageName;
            DB::connection($conn)->table('users')->where('id', '=', $user_id)->update(['image' => $imagePath]);
            $data = [
                "status" => "Ok",
                "status_code" => "200",
                "message" => "Profile Picture Updated Successfully!",
                "image" => $imagePath
            ];
        } catch (\Exception $e) {
            $data = [
                "status" => "Failed",
                "status_code" => "300",
                "message" => "Profile Picture Updation Failed!"
            ];
        }
        return json_encode($data);
    }
    public function get_sites(Request $request)
    {
        $conn = $request->post('conn');
        $query = "SELECT id,name FROM sites";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }
    public function get_permission(Request $request)
    {
        $conn = $request->post('conn');
        $uid = $request->post('uid');
        $query = "SELECT module_id,can_view,can_add,can_edit,can_certify,can_pay,can_delete FROM user_permission WHERE user_id = '$uid'";
        $data = DB::connection($conn)->select($query);
        return json_encode($data);
    }
    public function get_modules(Request $request)
    {
        $comp_id = $request->post('comp_id');
        $plan_id = $request->post('subscription_plan_id');
        
        $query = DB::table('subscription_plans');

        if ($plan_id) {
            $query->where('id', $plan_id);
        } elseif ($comp_id) {
            $query->where('company_id', $comp_id)->where('status', 'Active')->orderBy('id', 'desc');
        } else {
            return json_encode([]);
        }

        $sub = $query->first();
            
        if (!$sub) {
            // Fallback for legacy companies if necessary, or return empty
            // For now, following the new requirement strictly
            return json_encode([]);
        }

        // 2. Check Expiry
        if ($sub->expiry_date) {
            $expiry = \Illuminate\Support\Carbon::parse($sub->expiry_date);
            if (\Illuminate\Support\Carbon::now()->startOfDay()->greaterThan($expiry)) {
                // Subscription has expired
                return json_encode([]);
            }
        }

        // 3. Get Modules from JSON
        $moduleIds = json_decode($sub->modules, true);
        if (empty($moduleIds)) {
            return json_encode([]);
        }

        // 4. Fetch module names
        $data = DB::table('modules')
            ->whereIn('id', $moduleIds)
            ->get(['id', 'name']);

        return json_encode($data);
    }
    public function get_all_modules(Request $request)
    {
        $query = "SELECT id, name FROM modules";
        $data = DB::select($query);
        return json_encode($data);
    }
    public function get_site_transaction(Request $request)
    {
        $conn = $request->post('conn');
        $site_id = $request->post('site_id');

        $data = DB::connection($conn)->select("SELECT * FROM sites_transaction WHERE site_id = '$site_id'");
        $res = array();
        foreach ($data as $dd) {
            $amount = 0;
            if ($dd->expense_id != null) {
                $amount = DB::connection($conn)->select("SELECT amount FROM expenses WHERE id = $dd->expense_id")[0]->amount;
            } else if ($dd->payment_id != null) {
                $amount = DB::connection($conn)->select("SELECT amount FROM site_payments WHERE id = $dd->payment_id")[0]->amount;
            } else if ($dd->payment_voucher_id != null) {
                $amount = DB::connection($conn)->select("SELECT amount FROM payment_vouchers WHERE id = $dd->payment_voucher_id")[0]->amount;
            }
            $dt = array();
            $dt = $dd;
            $dt->amount = $amount;
            array_push($res, $dt);
        }
        return json_encode($res);
    }
    public function my_doc_upload_file(Request $request)
    {
        // Get inputs
        
        $name = $request->post('name');
        $date = $request->post('date');
        $particular = $request->post('particular');
        $remark = $request->post('remark');
        $filter = json_decode(stripslashes( $request->filter));

        $user_db_conn_name = $request->post('conn');
        $uid = $request->post('uid');


        $imagePath = "images/expense.png";
        try {
            if (isset($request->image)) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
                $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/documents'), $imageName);
                $imagePath = "images/app_images/".$user_db_conn_name."/documents/" . $imageName;
            } else {
                $result['status'] = 'Failed';
                $result['message'] = 'Document Is Required!';
                $response = array();
                array_push($response, $result);
                return json_encode($response);

            }
        } catch (\Exception $e) {
            $result['status'] = 'Failed';
            $result['message'] = 'Document Is Required!';
            $response = array();
            array_push($response, $result);
            return json_encode($response);
        }
        // Prepare data for insertion
        $doc_upload_data = [
            'name' => $name,
            'date' => $date,
            'particular' => $particular,
            'remark' => $remark,
            'path' => $imagePath,
            'created_by' => $uid
        ];
        $doc_id = DB::connection($user_db_conn_name)->table('doc_upload')->insertGetId($doc_upload_data);
        addActivity($doc_id, 'doc_upload', "New Document Uploaded ", 11,$uid,$user_db_conn_name);
        

        for ($j = 0; $j < count($filter); $j++) {
            $filt = $filter[$j];
            if ($filt != '') {
                $filter_explode = explode('=>', $filt);
                $head = $filter_explode[0];
                $option = $filter_explode[1];
                $doc_meta_data = [
                    'doc_id' => $doc_id,
                    'head_id' => $head,
                    'option_id' => $option,
                    'structure' => $filt
                ];
                DB::connection($user_db_conn_name)->table('doc_meta')->insert($doc_meta_data);
            }
        }

        $result['status'] = 'Ok';
        $result['message'] = 'Document Created Successfully!';
        $result['inserted_id'] = $doc_id;
        $result['image'] = $imagePath;
        $response = array();
        array_push($response, $result);
        return json_encode($response);

    }
}
