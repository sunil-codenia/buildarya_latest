<?php

namespace App\Http\Controllers\users;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
// use Symfony\Component\Console\Input\Input;

class UserController extends Controller
{
    public function users(Request $request)
    {
        return view('layouts.users.users');
    }

    public function get_users_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $current_role = $request->session()->get('role');
        
        $query = DB::connection($user_db_conn_name)->table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->select('users.*', 'roles.name as role_name');

        $companyName = $request->session()->get('comp_name', 'N/A');

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'LIKE', "%{$search}%")
                    ->orWhere('users.username', 'LIKE', "%{$search}%")
                    ->orWhere('users.contact_no', 'LIKE', "%{$search}%")
                    ->orWhere('users.pan_no', 'LIKE', "%{$search}%")
                    ->orWhere('roles.name', 'LIKE', "%{$search}%");
            });
        }

        // Individual Column Searching
        $columns_search = $request->input('columns');
        if ($columns_search) {
            foreach ($columns_search as $index => $column) {
                $search_val = $column['search']['value'];
                if (!empty($search_val)) {
                    switch ($index) {
                        case 3: // Name/Role
                            $query->where(function ($q) use ($search_val) {
                                $q->where('users.name', 'LIKE', "%{$search_val}%")
                                  ->orWhere('roles.name', 'LIKE', "%{$search_val}%");
                            });
                            break;
                        case 4: // Site
                            $query->whereExists(function ($q) use ($search_val) {
                                $q->select(DB::raw(1))
                                  ->from('sites')
                                  ->whereRaw("FIND_IN_SET(sites.id, users.site_id)")
                                  ->where('sites.name', 'LIKE', "%{$search_val}%");
                            });
                            break;
                        case 7: // Status
                            $query->where('users.status', 'LIKE', "%{$search_val}%");
                            break;
                        case 8: // Username
                            $query->where('users.username', 'LIKE', "%{$search_val}%");
                            break;
                        case 9: // Contact
                            $query->where('users.contact_no', 'LIKE', "%{$search_val}%");
                            break;
                        case 10: // PAN
                            $query->where('users.pan_no', 'LIKE', "%{$search_val}%");
                            break;
                        case 11: // Pass (if role 1) or Created (if not)
                            if ($current_role == 1) {
                                $query->where('users.pass', 'LIKE', "%{$search_val}%");
                            } else {
                                $query->where('users.create_datetime', 'LIKE', "%{$search_val}%");
                            }
                            break;
                        case 12: // Created (if role 1)
                            if ($current_role == 1) {
                                $query->where('users.create_datetime', 'LIKE', "%{$search_val}%");
                            }
                            break;
                    }
                }
            }
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');

        $columns = [
            3 => 'users.name',
            7 => 'users.status',
            8 => 'users.username',
            9 => 'users.contact_no',
            10 => 'users.pan_no',
        ];

        if ($current_role == 1) {
            $columns[12] = 'users.create_datetime';
        } else {
            $columns[11] = 'users.create_datetime';
        }

        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('users.id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);

        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $users = $query->get();

        $all_sites = DB::connection($user_db_conn_name)->table('sites')->pluck('name', 'id')->toArray();
        $all_possible_members = [];
        
        $site_ids_first = $users->map(fn($u) => explode(',', (string)$u->site_id)[0] ?? null)->unique()->filter()->toArray();
        if (!empty($site_ids_first)) {
            $all_possible_members = DB::connection($user_db_conn_name)->table('users')
                ->whereIn('site_id', $site_ids_first)
                ->select('name', 'id', 'image', 'site_id')
                ->get()
                ->groupBy('site_id');
        }

        $formattedData = [];
        $i = $start + 1;

        foreach ($users as $user) {
            $ddid = $user->id;
            
            $image = '<img class="rounded avatar" style="max-height: 40px;" src="'.asset($user->image).'" alt="im">';
            
            $role_name = $user->role_name ?? 'N/A';
            $nameInfo = '<a class="single-user-name" href="#">'.htmlspecialchars($user->name).'</a><br><small>'.htmlspecialchars($role_name).'</small>';
            
            $assigned_site_ids = explode(',', (string)$user->site_id);
            $site_names = [];
            foreach ($assigned_site_ids as $sid) {
                if (isset($all_sites[$sid])) {
                    $site_names[] = $all_sites[$sid];
                }
            }
            $siteInfo = '<strong>'.htmlspecialchars(implode(', ', $site_names)).'</strong>';
            $companyInfo = '<strong>'.htmlspecialchars($companyName).'</strong>';

            // Other Site Members Avatars (using first assigned site)
            $first_site_id = $assigned_site_ids[0] ?? null;
            $other_members_html = '<ul class="list-unstyled team-info margin-0">';
            if (isset($all_possible_members[$first_site_id])) {
                foreach ($all_possible_members[$first_site_id] as $member) {
                    if ($member->id != $user->id) {
                        $other_members_html .= '<li><a title="'.htmlspecialchars($member->name).'"><img src="'.asset($member->image).'" style="max-height: 40px;" alt="'.htmlspecialchars($member->name).'"></a></li>';
                    }
                }
            }
            $other_members_html .= '</ul>';

            $statusClass = ($user->status == 'Active') ? 'badge-success' : 'badge-danger';
            $statusHtml = '';
            if (checkmodulepermission(1, 'can_certify') == 1) {
                $newStatus = ($user->status == 'Active') ? 'Deactive' : 'Active';
                $statusHtml = '<span onclick="updateuserstatus(\''.$ddid.'\',\''.$newStatus.'\')" class="badge '.$statusClass.'">'.$user->status.'</span>';
            } else {
                $statusHtml = '<span class="badge '.$statusClass.'">'.$user->status.'</span>';
            }

            $actionHtml = '';
            if (checkmodulepermission(1, 'can_edit') == 1) {
                $actionHtml .= '<button title="Assign Permission" onclick="assignPerm('.$ddid.')" style="all:unset"><img src="'.asset('/images/permission.png').'" style="width:20px" /></button>&nbsp;';
                $actionHtml .= '<button title="Edit" onclick="editdata('.$ddid.')" style="all:unset;"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
            }
            if (isUserDeletable($ddid) && checkmodulepermission(1, 'can_delete') == 1) {
                $actionHtml .= '<button title="delete" onclick="deleteUser('.$ddid.')" style="all:unset"><i class="zmdi zmdi-delete"></i></button>';
            }

            $checkboxHtml = '<input type="checkbox" class="user-checkbox" value="'.$ddid.'">';
            
            $rowData = [
                $checkboxHtml,
                $i++,
                $image,
                $nameInfo,
                $siteInfo,
                $companyInfo,
                $other_members_html,
                $statusHtml,
                $user->username,
                $user->contact_no,
                $user->pan_no
            ];

            if ($current_role == 1) {
                $rowData[] = $user->pass;
            }
            
            $rowData[] = $user->create_datetime;
            $rowData[] = $actionHtml;

            $formattedData[] = $rowData;
        }

        return response()->json([
            "draw" => intval($request->input('draw')),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $filteredRecords,
            "data" => $formattedData
        ]);
    }
    public function user_report(Request $request)
    {
        return  view('layouts.users.reports');
    }

    
    public function addnewuser(Request $request)
    {


        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $imageName = "images/noprofile.jpg";
        $imagePath = "images/noprofile.jpg";
        $name = $request->name;
        
        $username = $request->username;
        $password = $request->password;
        $contact_no = $request->contact_no;
        $site_id = is_array($request->site_id) ? implode(',', $request->site_id) : $request->site_id;
        $role_id = $request->role_id;
        $pan_no = $request->pan_no;
        $mobile_only = $request->mobile_only;
        $status = $request->status;
       
        $request->validate([
            'contact_no' => 'required|digits:10',
            'username' => 'required|min:5',
            'name' => 'required|min:3',
            'password' => 'required|min:5'
        ], [
            'contact_no.digits' => 'Contact Number Should be 10 digits',
            'contact_no.required' => 'Contact Numeber Is Required',
            'username.min' => 'Username Should Be Minimum Of 5 Characters',
            'name.min' => 'Name Should Be Minimum Of 3 Characters',
            'password.min' => 'Password Should Be Minimum Of 5 Characters',
        ]);

        $valid_username=DB::connection($user_db_conn_name)->table('users')->where('username','=',$username)->count();
        if( $valid_username ==0 ){


        $imagePath = "images/noprofile.jpg";
        if (isset($request->image)) {
            $request->validate(
                [
                    'image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                ],
                [
                    'image.mimes'   => 'Please Select Valid Image Format (Jpeg,Png,Jpg,Gif)',
                    'image.image' => 'Please Select Valid Image (Jpeg,Png,Jpg,Gif)',
                    'image.uploaded' => 'Please Choose Image Less Than 2 Mb',
                ]
            );
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/users'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/users/" . $imageName;
        }

        // Logic to automatically assign company's active plan if none provided in request
        $subscription_plan_id = $request->subscription_plan_id;
        if (!$subscription_plan_id) {
            // Priority 1: Inherit from the logged-in SuperAdmin's current session
            $subscription_plan_id = $request->session()->get('subscription_plan_id');

            // Priority 2: Fallback to latest active plan in DB if session is missing it
            if (!$subscription_plan_id) {
                $comp_db_id = $request->session()->get('comp_db_id');
                $active_plan = DB::table('subscription_plans')
                    ->where('company_id', $comp_db_id)
                    ->where('status', 'Active')
                    ->orderBy('id', 'desc')
                    ->first();
                $subscription_plan_id = $active_plan ? $active_plan->id : null;
            }
        }
        
        $data = [
            'name' => $name,
            'username' => $username,
            'pass' => $password,
            'subscription_plan_id' => $subscription_plan_id,
            'site_id' => $site_id,
            'role_id' => $role_id,
            'pan_no' => $pan_no,
            'image' => $imagePath,
            'contact_no' => $contact_no,
            'mobile_only'=>$mobile_only,
            'view_duration' => $request->view_duration,
            'add_duration' => $request->add_duration,
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $user_id = DB::connection($user_db_conn_name)->table('users')->insertGetId($data);
            $rolename = getRoleDetailsById($role_id)->name;
            DB::connection($user_db_conn_name)->table('contact')->insert(['profile_id' => "1", 'name' => $name, 'phone'=>$contact_no,'position' => $rolename]);
            $sub = DB::table('subscription_plans')->where('id', $request->subscription_plan_id)->first();
            $allowedModules = $sub ? json_decode($sub->modules, true) : [];
            $modules = DB::table('modules')->whereIn('id', $allowedModules)->get();
            
            // Fetch default permissions for the assigned role
            $role_permissions = DB::connection($user_db_conn_name)->table('role_permission')->where('role_id', '=', $role_id)->get()->keyBy('module_id');
            
            $permission = array();
            $perm_result = array();
            foreach ($modules as $module) {
                // If the role has default permissions for this module, use them. Otherwise default to 0.
                $def = isset($role_permissions[$module->id]) ? $role_permissions[$module->id] : null;
                
                $permission[$module->id]['can_view'] = $def ? $def->can_view : 0;
                $permission[$module->id]['can_edit'] = $def ? $def->can_edit : 0;
                $permission[$module->id]['can_certify'] = $def ? $def->can_certify : 0;
                $permission[$module->id]['can_add'] = $def ? $def->can_add : 0;
                $permission[$module->id]['can_delete'] = $def ? $def->can_delete : 0;
                $permission[$module->id]['can_pay'] = $def ? $def->can_pay : 0;
                $permission[$module->id]['can_report'] = $def ? $def->can_report : 0;
                
                $res = array();
                $res['user_id'] = $user_id;
                $res['module_id'] = $module->id;
                $res['subscription_plan_id'] = $request->subscription_plan_id;
                $res['can_view'] = $permission[$module->id]['can_view'];
                $res['can_add'] = $permission[$module->id]['can_add'];
                $res['can_delete'] = $permission[$module->id]['can_delete'];
                $res['can_edit'] = $permission[$module->id]['can_edit'];
                $res['can_certify'] = $permission[$module->id]['can_certify'];
                $res['can_pay'] = $permission[$module->id]['can_pay'];
                $res['can_report'] = $permission[$module->id]['can_report'];
                array_push($perm_result, $res);
            }
                try {
                    DB::connection($user_db_conn_name)->table('user_permission')->where('user_id', '=', $user_id)->delete();
                    DB::connection($user_db_conn_name)->table('user_permission')->insert($perm_result);

                    addActivity($user_id,'users',"User Created",1);

                    return redirect('/users')
                        ->with('success', 'User Created successfully!');
                } catch (\Exception $e) {
                    print_r($e);
                    return redirect('/users')
                        ->with('error', 'Error While Assigning Permissions!');
                }
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/users')
                    ->with('error', 'User Already Exists!');
            } else {
                return redirect('/users')
                    ->with('error', 'Error While Creating User!');
            }
        }
 }else{
    return redirect('/users')
    ->with('error', 'Username Already Exist!');

    }

    }
    public function update_user_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('users')->where('id', '=', $id)->update(['status' => $status]);

        if ($status == 'Active') {
            addActivity($id,'users',"User Activated",1);
            return redirect('/users')
                ->with('success', 'User Activated!');
        } else {
            addActivity($id,'users',"User Deactivated",1);
            return redirect('/users')
                ->with('success', 'User Deactivated!');
        }
    }


    public function edit_users(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $users_list = array();
        $users = DB::connection($user_db_conn_name)->table('users')->get();
        // Skip team enrichment here as it's complex for multi-site and might not be used in the edit view
        for ($i = 0; $i < sizeof($users); $i++) {
            $users_list[$i]['data'] = $users[$i];
            $users_list[$i]['list'] = [];
        }
        $data['data'] = $users_list;
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('users')->where('id', '=', $id)->get();
        return  view('layouts.users.users')->with('data', json_encode($data));
    }

    public function delete_users(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $users = DB::connection($user_db_conn_name)->table('users')->where('id',$id)->get()[0]->name;
        DB::connection($user_db_conn_name)->table('users')->where('id', '=', $id)->delete();
        addActivity(0,'users',"User Deleted - ".$users,1);
        return redirect('/users')
            ->with('success', 'Users Deleted Successfully!');
    }

    public function bulk_update_users_status(Request $request)
    {
        $ids = $request->input('ids');
        $status = $request->input('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (!empty($ids) && !empty($status)) {
            DB::connection($user_db_conn_name)->table('users')->whereIn('id', $ids)->update(['status' => $status]);
            foreach ($ids as $id) {
                addActivity($id, 'users', "User Status Updated to $status via Bulk Action", 1);
            }
            return response()->json(['status' => 'Ok', 'message' => "Users marked as $status successfully"]);
        }
        return response()->json(['status' => 'Error', 'message' => 'Invalid data'], 400);
    }

    public function bulk_delete_users(Request $request)
    {
        $ids = $request->input('ids');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (!empty($ids)) {
            // Check if any user is not deletable (optional, but good for safety)
            $deletable_ids = [];
            foreach ($ids as $id) {
                if (isUserDeletable($id)) {
                    $deletable_ids[] = $id;
                }
            }

            if (!empty($deletable_ids)) {
                DB::connection($user_db_conn_name)->table('users')->whereIn('id', $deletable_ids)->delete();
                foreach ($deletable_ids as $id) {
                    addActivity($id, 'users', "User Deleted via Bulk Action", 1);
                }
                return response()->json(['status' => 'Ok', 'message' => count($deletable_ids) . " Users deleted successfully"]);
            }
            return response()->json(['status' => 'Error', 'message' => 'Selected users cannot be deleted'], 400);
        }
        return response()->json(['status' => 'Error', 'message' => 'No IDs provided'], 400);
    }

    public function updateusers(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $username = $request->input('username');
        $password = $request->input('pass');
        $contact_no = $request->input('contact_no');
        $site_id = is_array($request->input('site_id')) ? implode(',', $request->input('site_id')) : $request->input('site_id');
        $role_id = $request->input('role_id');
        $pan_no = $request->input('pan_no');
        $mobile_only=$request->input('mobile_only');
        
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        // Ensure subscription_plan_id is not wiped if missing from request
        $subscription_plan_id = $request->subscription_plan_id;
        if (!$subscription_plan_id) {
            $existing_user = DB::connection($user_db_conn_name)->table('users')->where('id', $id)->first();
            $subscription_plan_id = $existing_user ? $existing_user->subscription_plan_id : null;
        }

        // If still null, try to fallback to company active plan
        if (!$subscription_plan_id) {
            $comp_db_id = $request->session()->get('comp_db_id');
            $active_plan = DB::table('subscription_plans')
                ->where('company_id', $comp_db_id)
                ->where('status', 'Active')
                ->orderBy('id', 'desc')
                ->first();
            $subscription_plan_id = $active_plan ? $active_plan->id : null;
        }

        $updateData = [
            'name' => $name,
            'username' => $username,
            'pass' => $password,
            'subscription_plan_id' => $subscription_plan_id,
            'site_id' => $site_id,
            'role_id' => $role_id,
            'pan_no' => $pan_no,
            'contact_no' => $contact_no,
            'mobile_only'=>$mobile_only,
            'view_duration' => $request->view_duration,
            'add_duration' => $request->add_duration,
        ];

        if (isset($request->image)) {
            $request->validate(
                [
                    'image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                ],
                [
                    'image.mimes'   => 'Please Select Valid Image Format (Jpeg,Png,Jpg,Gif)',
                    'image.image' => 'Please Select Valid Image (Jpeg,Png,Jpg,Gif)',
                    'image.uploaded' => 'Please Choose Image Less Than 2 Mb',
                ]
            );
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/users'), $imageName);
            $updateData['image'] = "images/app_images/".$user_db_conn_name."/users/" . $imageName;
        }

        DB::connection($user_db_conn_name)->table('users')->where('id', $id)->update($updateData);

        // Update session if the admin is editing their own profile
        if ($id == session()->get('uid')) {
            session()->put('name', $name);
            session()->put('username', $username);
            if (isset($updateData['image'])) {
                session()->put('image', $updateData['image']);
            }
        }

        addActivity($id,'users',"User Data Updated",1);
        return redirect('/users')->with('success', 'User Updated successfully!');
    }
    public function edit_site(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = DB::connection($user_db_conn_name)->table('users')->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('users')->where('id', '=', $id)->get();
        return  view('layouts.users.users')->with('data', json_encode($data));
    }
    public function assign_permission(Request $request)
    {
        $id = $request->get('id');

        $comp_id = $request->session()->get('comp_db_id');
        $plan_id = $request->session()->get('subscription_plan_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
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
            13 => 'Attendance Management',
            14 => 'Task Management',
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
        $data['permissions'] = DB::connection($user_db_conn_name)->table('user_permission')->where('user_id', '=', $id)->get();
        $data['user_id'] = $id;

        return  view('layouts.users.assign_permission')->with('data', json_encode($data));
    }
    public function update_user_permission(Request $request)
    {
        $result = array();
        if (!empty($request->get('view'))) {
            $view = $request->get('view');
        }
        if (!empty($request->get('add'))) {
            $add = $request->get('add');
        }
        if (!empty($request->get('edit'))) {
            $edit = $request->get('edit');
        }
        if (!empty($request->get('certify'))) {
            $certify = $request->get('certify');
        }
        if (!empty($request->get('delete'))) {
            $delete = $request->get('delete');
        }
        if (!empty($request->get('pay'))) {
            $pay = $request->get('pay');
        }
        if (!empty($request->get('report'))) {
            $report = $request->get('report');
        }
        $user_id = $request->input('user_id');
        $comp_id = $request->session()->get('comp_db_id');
        $plan_id = $request->session()->get('subscription_plan_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $sub = DB::table('subscription_plans')->where('id', $plan_id)->first();
        $allowedModules = $sub ? json_decode($sub->modules, true) : [];
        $modules = DB::table('modules')->whereIn('id', $allowedModules)->get();
        $permission = array();
        foreach ($modules as $module) {

            if (isset($view)) {
                if (in_array($module->id, $view)) {
                    $permission[$module->id]['can_view'] = 1;
                } else {
                    $permission[$module->id]['can_view'] = 0;
                }
            } else {
                $permission[$module->id]['can_view'] = 0;
            }
            if (isset($edit)) {
                if (in_array($module->id, $edit)) {
                    $permission[$module->id]['can_edit'] = 1;
                } else {
                    $permission[$module->id]['can_edit'] = 0;
                }
            } else {
                $permission[$module->id]['can_edit'] = 0;
            }
            if (isset($certify)) {
                if (in_array($module->id, $certify)) {
                    $permission[$module->id]['can_certify'] = 1;
                } else {
                    $permission[$module->id]['can_certify'] = 0;
                }
            } else {
                $permission[$module->id]['can_certify'] = 0;
            }
            if (isset($add)) {
                if (in_array($module->id, $add)) {
                    $permission[$module->id]['can_add'] = 1;
                } else {
                    $permission[$module->id]['can_add'] = 0;
                }
            } else {
                $permission[$module->id]['can_add'] = 0;
            }
            if (isset($delete)) {
                if (in_array($module->id, $delete)) {
                    $permission[$module->id]['can_delete'] = 1;
                } else {
                    $permission[$module->id]['can_delete'] = 0;
                }
            } else {
                $permission[$module->id]['can_delete'] = 0;
            }

            if (isset($pay)) {
                if (in_array($module->id, $pay)) {
                    $permission[$module->id]['can_pay'] = 1;
                } else {
                    $permission[$module->id]['can_pay'] = 0;
                }
            } else {
                $permission[$module->id]['can_pay'] = 0;
            }
            if (isset($report)) {
                if (in_array($module->id, $report)) {
                    $permission[$module->id]['can_report'] = 1;
                } else {
                    $permission[$module->id]['can_report'] = 0;
                }
            } else {
                $permission[$module->id]['can_report'] = 0;
            }

            $res['user_id'] = $user_id;
            $res['module_id'] = $module->id;
            $res['subscription_plan_id'] = $plan_id;
            $res['can_view'] = $permission[$module->id]['can_view'];
            $res['can_add'] = $permission[$module->id]['can_add'];
            $res['can_delete'] = $permission[$module->id]['can_delete'];
            $res['can_edit'] = $permission[$module->id]['can_edit'];
            $res['can_certify'] = $permission[$module->id]['can_certify'];
            $res['can_pay'] = $permission[$module->id]['can_pay'];
            $res['can_report'] = $permission[$module->id]['can_report'];
            array_push($result, $res);
        }
        // dd($result);
         try {
                DB::connection($user_db_conn_name)->table('user_permission')->where('user_id', '=', $user_id)->delete();

                DB::connection($user_db_conn_name)->table('user_permission')->insert($result);
                addActivity($user_id,'users',"User Permission Updated",1);
                return redirect('/users')
                    ->with('success', 'Permission Updated Successfully!');
            } catch (\Exception $e) {
                print_r($e);
                return redirect('/users')
                    ->with('error', 'Error While Assigning Permissions!');
            }
    }


    public function profile(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $user_id = session()->get('uid');
        
        $user = DB::connection($user_db_conn_name)->table('users')->where('id', $user_id)->first();
        
        if (!$user) {
            return redirect('/login')->with('error', 'Please login to access profile.');
        }
        
        return view('layouts.users.profile')->with('user', $user);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:5|confirmed',
        ]);

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $user_id = session()->get('uid');
        
        $user = DB::connection($user_db_conn_name)->table('users')->where('id', $user_id)->first();

        if ($user->pass !== $request->current_password) {
            return redirect()->back()->with('error', 'Current password does not match!');
        }

        DB::connection($user_db_conn_name)->table('users')->where('id', $user_id)->update([
            'pass' => $request->new_password
        ]);

        addActivity($user_id, 'users', "User Password Updated", 1);

        return redirect()->back()->with('success', 'Password updated successfully!');
    }

    public function updateProfile(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $user_id = session()->get('uid');

        $request->validate([
            'name' => 'required|min:3',
            'username' => 'required|min:5',
            'contact_no' => 'required|digits:10',
            'pan_no' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $updateData = [
            'name' => $request->name,
            'username' => $request->username,
            'contact_no' => $request->contact_no,
            'pan_no' => $request->pan_no,
        ];

        if ($request->hasFile('image')) {
            $imageName = time() . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/'.$user_db_conn_name.'/users'), $imageName);
            $updateData['image'] = "images/app_images/".$user_db_conn_name."/users/" . $imageName;
        }

        // Check if username is already taken by another user
        $exists = DB::connection($user_db_conn_name)->table('users')
            ->where('username', $request->username)
            ->where('id', '!=', $user_id)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', 'Username already taken by another user!');
        }

        DB::connection($user_db_conn_name)->table('users')->where('id', $user_id)->update($updateData);

        // Update session variables to reflect changes in sidebar and other layouts
        session()->put('name', $request->name);
        session()->put('username', $request->username);
        if (isset($updateData['image'])) {
            session()->put('image', $updateData['image']);
        }

        addActivity($user_id, 'users', "Profile Updated", 1);

        return redirect()->back()->with('success', 'Profile updated successfully!');
    }
}
