<?php

namespace App\Http\Controllers\users;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $roles = DB::connection($user_db_conn_name)->table('roles')->get();
        $all_users = DB::connection($user_db_conn_name)->table('users')->select('name', 'id', 'image', 'role_id')->get();
        for ($i = 0; $i < sizeof($roles); $i++) {
            $users = [];
            foreach ($all_users as $au) {
                if ($au->role_id == $roles[$i]->id) {
                    $users[] = $au;
                }
            }
            $data[$i]['roles'] = $roles[$i];
            $data[$i]['users'] = $users;
        }
        // print_r($data[0]);
        return  view('layouts.users.roles')->with('data', json_encode($data));
    }
    public function addnewrole(Request $request)
    {
        $data = [
            'name' => $request->input('name')
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('roles')->insertGetId($data);

            addActivity($id, 'roles', "Role Created ", 1);

            return redirect('/user_roles')
                ->with('success', 'Role Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/user_roles')
                    ->with('error', 'Role Already Exists!');
            } else {
                return redirect('/user_roles')
                    ->with('error', 'Error While Creating Role!');
            }
        }
    }
    public function edit_role(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->get();
        $roles = DB::connection($user_db_conn_name)->table('roles')->get();
        $all_users = DB::connection($user_db_conn_name)->table('users')->select('name', 'id', 'image', 'role_id')->get();
        for ($i = 0; $i < sizeof($roles); $i++) {
            $users = [];
            foreach ($all_users as $au) {
                if ($au->role_id == $roles[$i]->id) {
                    $users[] = $au;
                }
            }
            $rdata[$i]['roles'] = $roles[$i];
            $rdata[$i]['users'] = $users;
        }
        $data['data'] = $rdata;
        // print_r($data[0]);
        return  view('layouts.users.roles')->with('data', json_encode($data));
    }
    public function edit_role_settings(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['edit_setting'] = DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->get();
        $roles = DB::connection($user_db_conn_name)->table('roles')->get();
        $all_users = DB::connection($user_db_conn_name)->table('users')->select('name', 'id', 'image', 'role_id')->get();
        for ($i = 0; $i < sizeof($roles); $i++) {
            $users = [];
            foreach ($all_users as $au) {
                if ($au->role_id == $roles[$i]->id) {
                    $users[] = $au;
                }
            }
            $rdata[$i]['roles'] = $roles[$i];
            $rdata[$i]['users'] = $users;
        }
        $data['data'] = $rdata;
        // print_r($data[0]);
        return  view('layouts.users.roles')->with('data', json_encode($data));
    }
    public function delete_role(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('users')->where('role_id', '=', $id)->get();
        $rolename = DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->get()[0]->name;

        if (Count($check) > 0) {
            return redirect('/user_roles')
                ->with('error', 'Role Is In Use!');
        } else {

            DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->delete();
            addActivity(0, 'roles', "Role Deleted - " . $rolename, 1);
            return redirect('/user_roles')
                ->with('success', 'Role Deleted Successfully!');
        }
    }
    public function updaterole(Request $request)
    {
        $id = $request->input('id');
        $name = $request->get('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        try {
            DB::connection($user_db_conn_name)->table('roles')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'roles', "Role Data Updated", 1);
            return redirect('/user_roles')
                ->with('success', 'Role Updated successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/user_roles')
                    ->with('error', 'Role Already Exists!');
            } else {
                return redirect('/user_roles')
                    ->with('error', 'Error While Updating Role!');
            }
        }
    }
    public function updaterolesetting(Request $request)
    {
        $id = $request->input('id');
        $data = [
            'add_duration' => $request->input('add_duration'),
            'view_duration' => $request->input('view_duration'),
            'initial_entry_status' => $request->input('initial_entry_status'),
            'entry_at_site' => $request->input('entry_at_site'),
            'visiblity_at_site' => $request->input('visiblity_at_site'),
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('roles')->where('id', $id)->update($data);
        addActivity($id, 'roles', "Role Setting Updated", 1);
        return redirect('/user_roles')
            ->with('success', 'Role Setting Updated successfully!');
    }

    public function assign_role_permission(Request $request)
    {
        if (!isSuperAdmin()) {
            return redirect('/user_roles')->with('error', 'Unauthorized Access!');
        }

        $id = $request->get('id');
        $comp_id = $request->session()->get('comp_db_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $plan_id = $request->session()->get('subscription_plan_id');
        $sub = DB::table('subscription_plans')->where('id', $plan_id)->first();
        $allowedModules = $sub ? json_decode($sub->modules, true) : [];
        $raw_modules = DB::table('modules')->whereIn('id', $allowedModules)->get();
                             
        $sidebar_map = [
            1 => 'Sites & Users',
            2 => 'Expenses',
            3 => 'Material Purchase & Manage Stock',
            4 => 'Site Bills',
            6 => 'Machinery',
            5 => 'Assets',
            7 => 'Sales',
            8 => 'Payment Vouchers',
            11 => 'Document Management',
            10 => 'Contact Management',
            9 => 'Management'
        ];

        $modules = [];
        foreach ($sidebar_map as $sid => $sname) {
            foreach ($raw_modules as $rm) {
                if ($rm->id == $sid) {
                    $modules[] = ['id' => $sid, 'name' => $sname];
                    break;
                }
            }
        }
        $data['modules'] = $modules;
                             
        $data['permissions'] = DB::connection($user_db_conn_name)
                                 ->table('role_permission')
                                 ->where('role_id', '=', $id)
                                 ->get();
                                 
        $data['role_id'] = $id;
        $data['role_name'] = DB::connection($user_db_conn_name)->table('roles')->where('id', $id)->first()->name;

        return view('layouts.users.assign_role_permission')->with('data', json_encode($data));
    }

    public function update_role_permission(Request $request)
    {
        if (!isSuperAdmin()) {
            return redirect('/user_roles')->with('error', 'Unauthorized Access!');
        }

        $role_id = $request->input('role_id');
        $comp_id = $request->session()->get('comp_db_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $view = $request->get('view') ?? [];
        $add = $request->get('add') ?? [];
        $edit = $request->get('edit') ?? [];
        $certify = $request->get('certify') ?? [];
        $delete = $request->get('delete') ?? [];
        $pay = $request->get('pay') ?? [];
        $report = $request->get('report') ?? [];

        $plan_id = $request->session()->get('subscription_plan_id');
        $sub = DB::table('subscription_plans')->where('id', $plan_id)->first();
        $allowedModules = $sub ? json_decode($sub->modules, true) : [];
        $modules = DB::table('modules')->whereIn('id', $allowedModules)->get();

        $result = array();
        foreach ($modules as $module) {
            $result[] = [
                'role_id' => $role_id,
                'module_id' => $module->id,
                'can_view' => in_array($module->id, $view) ? 1 : 0,
                'can_add' => in_array($module->id, $add) ? 1 : 0,
                'can_edit' => in_array($module->id, $edit) ? 1 : 0,
                'can_certify' => in_array($module->id, $certify) ? 1 : 0,
                'can_delete' => in_array($module->id, $delete) ? 1 : 0,
                'can_pay' => in_array($module->id, $pay) ? 1 : 0,
                'can_report' => in_array($module->id, $report) ? 1 : 0,
            ];
        }

        try {
            DB::connection($user_db_conn_name)->table('role_permission')->where('role_id', '=', $role_id)->delete();
            DB::connection($user_db_conn_name)->table('role_permission')->insert($result);
            
            // Sync exactly these permissions to all users who have this role
            $users = DB::connection($user_db_conn_name)->table('users')->where('role_id', $role_id)->pluck('id');
            foreach ($users as $u_id) {
                DB::connection($user_db_conn_name)->table('user_permission')->where('user_id', $u_id)->delete();
                $user_res = [];
                foreach ($result as $r) {
                    $u = $r; 
                    unset($u['role_id']);    // Remove role_id from array
                    $u['user_id'] = $u_id;   // Add user_id to array
                    $user_res[] = $u;
                }
                DB::connection($user_db_conn_name)->table('user_permission')->insert($user_res);
            }
            
            addActivity($role_id, 'roles', "Role Permission Updated", 1);
            
            return redirect('/user_roles')
                ->with('success', 'Role Permissions Updated Successfully!');
        } catch (\Exception $e) {
            return redirect('/user_roles')
                ->with('error', 'Error While Assigning Role Permissions!');
        }
    }
}
