<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;

class LoginController extends Controller
{
    //
    public function loginfunc(Request $request)
    {
        $uid = $request->post('companyid');
        $uname = $request->post('username');
        $pass = $request->post('password');

        $users = DB::table('companies')->select('id', 'name', 'db_name', 'db_pass', 'db_host', 'db_port', 'status')->where('uid', $uid)->count();
        if ($users == 1) {
            $compdata = DB::table('companies')->select('id', 'uid', 'name', 'db_conn_name', 'status','address','mobile','email')->where('uid', $uid)->first();
            if ($compdata->status == 'Active') {
                $usercount = DB::connection($compdata->db_conn_name)->table('users')->where('username', '=', $uname)->where('pass', '=', $pass)->count();
                if ($usercount == 1) {
                    $userdata = DB::connection($compdata->db_conn_name)->table('users')->where('username', '=', $uname)->where('pass', '=', $pass)->first();
                    if ($userdata->status == "Active") {
                        if ($userdata->mobile_only == "no") {
                        $settings = DB::connection($compdata->db_conn_name)->table('settings')->select('name', 'value')->get();
                        $roledata = DB::connection($compdata->db_conn_name)->table('roles')->where('id', '=', $userdata->role_id)->first();
                        $role_perms_count = DB::connection($compdata->db_conn_name)->table('role_permission')->where('role_id', $userdata->role_id)->count();

                        $key =   $request->session()->regenerate();
                        $mytime = Carbon::now();
                        session([
                            'key' => $key,
                            "uid" => $userdata->id,
                            "name" => $userdata->name,
                            "username" => $userdata->username,
                            "role" => $userdata->role_id,
                            "is_superadmin" => $roledata->is_superadmin,
                            "role_perms_set" => ($role_perms_count > 0),
                            "site_id" => $userdata->site_id,
                            "image" => $userdata->image,
                            "comp_id" => $compdata->uid,
                            "comp_db_id" => $compdata->id,
                            "comp_name" => $compdata->name,
                            "comp_add" => $compdata->address,
                            "comp_mobile" => $compdata->mobile,
                            "comp_email" => $compdata->email,
                            "comp_db_conn_name" => $compdata->db_conn_name,
                            "view_duration" => !empty($userdata->view_duration) ? $userdata->view_duration : $roledata->view_duration,
                            "add_duration" => !empty($userdata->add_duration) ? $userdata->add_duration : $roledata->add_duration
                        ]);
                        foreach ($settings as $setting) {
                            $request->session()->push($setting->name, $setting->value);
                        }
                        $perm = DB::connection($compdata->db_conn_name)->select("SELECT * FROM user_permission WHERE user_id = $userdata->id");
                        $permissions = array();
                        foreach($perm as $per){
$permissions[$per->module_id]['can_view'] = $per->can_view;
$permissions[$per->module_id]['can_add'] = $per->can_add;
$permissions[$per->module_id]['can_edit'] = $per->can_edit;
$permissions[$per->module_id]['can_certify'] = $per->can_certify;
$permissions[$per->module_id]['can_pay'] = $per->can_pay;
$permissions[$per->module_id]['can_delete'] = $per->can_delete;
$permissions[$per->module_id]['can_report'] = $per->can_report;
                        }

                        $request->session()->push("permissions", $permissions);
                        DB::connection($compdata->db_conn_name)->table('session')->insert([
                            "uid" => $userdata->id,
                            "login_time" => $mytime->toDateTimeString(),
                            "ip_address" => $this->getIp(),
                            "browser" => $request->header('User-Agent'),
                            "session_key" => $key
                        ]);
                        return redirect('/dashboard');
                        } else {
                            return view('/login')->with('errorcode', "You Are Not Allowed To Login Using Web Portal! Please Contact Your Administration!");
                        }
                    } else {
                        return view('/login')->with('errorcode', "Your Account Is Inactive! Please Contact Your Administration!");
                    }
                } else {
                    return view('/login')->with('errorcode', "Invalid Credentials! Please Check Your Credentials!");
                }
            } else {
                return view('/login')->with('errorcode', "This Company Is Inactive! Please Contact Your Administration");
            }
        } else {
            return view('/login')->with('errorcode', "This Company Don't Exists!");
        }
    }
    public function logout(Request $request)
    {
        $request->session()->flush();
        return redirect('/login');
    }
    public function getIp()
    {
        foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return request()->ip(); // it will return server ip when no client ip found
    }
    public function checkAuth(Request $request)
    {
        if ($request->session()->get('key')) {
            return redirect('/dashboard');
        } else {
            return redirect('/login');
        }

    }


    // temprory functions
    public function register_user()
    {

        return view('/registration');
    }
    public function register_new_user(Request $request)
    {
        $imagePath = "images/noprofile.jpg";
        $name = $request->name;
        $username = $request->username;
        $password = $request->password;
        $contact_no = $request->contact_no;
        $site_id = '1';
        $role_id = '1';
        $pan_no = $request->pan_no;
        $image = $imagePath;
        $data = [
            'name' => $name,
            'username' => $username,
            'pass' => $password,
            'site_id' => $site_id,
            'role_id' => $role_id,
            'pan_no' => $pan_no,
            'image' => $image,
            'contact_no' => $contact_no
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        try {
            DB::connection($user_db_conn_name)->table('users')->insert($data);
            return view('/login')
                ->with('successcode', "Registration Successfull! You May Login Now.");
        } catch (\Exception $e) {

            return view('/registration')
                ->with('errorcode', "Error While Registration! Please Contact Your Administration!");
        }
    }
}
