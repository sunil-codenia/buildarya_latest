<?php

namespace App\Http\Controllers\sales;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProjectController extends Controller
{
    //
    function sales_project(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;
        $site_id = $request->session()->get('site_id');

        $query = DB::connection($user_db_conn_name)->table('sales_project');

        if ($visiblity_at_site == 'current') {
            if ($site_id == 'all') {
                $assigned_site_ids = $request->session()->get('assigned_site_ids', []);
                $project_ids = DB::connection($user_db_conn_name)->table('sites')->whereIn('id', $assigned_site_ids)->where('project_id', '!=', 0)->pluck('project_id')->toArray();
                $query->whereIn('id', $project_ids);
            } else {
                $project_id = DB::connection($user_db_conn_name)->table('sites')->where('id', $site_id)->value('project_id');
                $query->where('id', $project_id);
            }
        }

        $data = $query->get();
        for ($i = 0; $i < count($data); $i++) {
            $data[$i]->invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $data[$i]->id)->COUNT('id');
        }

        return  view('layouts.sales.project')->with('data', json_encode($data));
    }
    function delete_sales_project(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $id)->get();
        if (Count($check) > 0) {
            return redirect('/sales_project')
                ->with('error', 'This Project Cannot Be Deleted. Project Has Invoices In Its Name!');
        } else {
            $project = DB::connection($user_db_conn_name)->table('sales_project')->where('id', $id)->get()[0];
            if (File::exists($project->attachment)) {
                File::delete($project->attachment);
            }
            DB::connection($user_db_conn_name)->table('sales_project')->where('id', '=', $id)->delete();
            addActivity(0,'sales_project',"Sales Project Deleted - ".$project->name, 7);
            return redirect('/sales_project')
                ->with('success', 'Project Deleted Successfully!');
        }
    }
    function updatesalesProject(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $details = $request->input('details');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $project = DB::connection($user_db_conn_name)->table('sales_project')->where('id', $id)->get()[0];
        if (isset($request->attachment)) {
            if (File::exists($project->attachment)) {
                File::delete($project->attachment);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->attachment->extension();
            $request->attachment->move(public_path('images/app_images/'.$user_db_conn_name.'/projects'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/projects/" . $imageName;
        } else {
            $imagePath = $project->attachment;
        }
        try {
            DB::connection($user_db_conn_name)->table('sales_project')->where('id', $id)->update(['name' => $name, 'details' => $details, 'attachment' => $imagePath]);
            addActivity($id,'sales_project',"Sales Project Data Updated",7);
            return redirect('/sales_project')
                ->with('success', 'Project Updated successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_project')
                    ->with('error', 'Project Already Exists With Same Name!');
            } else {
                return redirect('/sales_project')
                    ->with('error', 'Error While Updating Project!');
            }
        }
    }
    function edit_sales_project(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('sales_project')->get();
        for ($i = 0; $i < count($data['data']); $i++) {
            $data['data'][$i]->invoices = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $data['data'][$i]->id)->count('id');
        }
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sales_project')->where('id', '=', $id)->get();
        return  view('layouts.sales.project')->with('data', json_encode($data));
    }
    function update_sales_project_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('sales_project')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id,'sales_project',"Sales Project Status Updated - ".$status,7);
        if ($status == 'Active') {
            return redirect('/sales_project')
                ->with('success', 'Project Activated!');
        } else {
            return redirect('/sales_project')
                ->with('success', 'Project Deactivated!');
        }
    }



    function addsalesProject(Request $request)
    {
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $details = $request->input('details');
        if (isset($request->attachment)) {
            $imageName = time() . rand(10000, 1000000) . '.' . $request->attachment->extension();
            $request->attachment->move(public_path('images/app_images/'.$user_db_conn_name.'/projects'), $imageName);
            $imagePath = "images/app_images/".$user_db_conn_name."/projects/" . $imageName;
        } else {
            $imagePath = "";
        }
        $data = ['name' => $name, 'details' => $details, 'attachment' => $imagePath];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
         $addsalesProject = DB::connection($user_db_conn_name)->table('sales_project')->insertGetId($data);
            addActivity($addsalesProject,'sales_project',"New Sales Project Created",7);            
            return redirect('/sales_project')
                ->with('success', 'Project Created successfully!')
                ->with('ask_create_site', $addsalesProject);
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sales_project')
                    ->with('error', 'Project With Same Name Already Exists!');
            } else {
                return redirect('/sales_project')
                    ->with('error', 'Error While Creating Project!');
            }
        }
    }
}
