<?php

namespace App\Http\Controllers\material;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class StockController extends Controller
{

    public function verified_consumption(Request $request)
    {
        $data = array();

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        if ($visiblity_at_site == 'current') {
            $filters_1 = [['material_consumption.status', '!=', 'Pending'], ['material_consumption.site_id', '=', $site_id]];
            $filters_2 = [['material_wastage.status', '!=', 'Pending'], ['material_wastage.site_id', '=', $site_id]];
        } else {
            $filters_1 = [['material_consumption.status', '!=', 'Pending']];
            $filters_2 = [['material_wastage.status', '!=', 'Pending']];
        }
        $material_consumption = DB::connection($user_db_conn_name)->table('material_consumption')->leftjoin('materials', 'materials.id', '=', 'material_consumption.material_id')->leftjoin('sites', 'sites.id', '=', 'material_consumption.site_id')->leftjoin('units', 'units.id', '=', 'material_consumption.unit')->leftjoin('users', 'users.id', '=', 'material_consumption.user_id')->select('material_consumption.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user')->where($filters_1)->whereBetween('material_consumption.date', [$min_date, $max_date])->orderBy('material_consumption.id', 'DESC')->get();
        $material_wastage = DB::connection($user_db_conn_name)->table('material_wastage')->leftjoin('materials', 'materials.id', '=', 'material_wastage.material_id')->leftjoin('sites', 'sites.id', '=', 'material_wastage.site_id')->leftjoin('units', 'units.id', '=', 'material_wastage.unit')->leftjoin('users', 'users.id', '=', 'material_wastage.user_id')->select('material_wastage.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user')->where($filters_2)->whereBetween('material_wastage.date', [$min_date, $max_date])->orderBy('material_wastage.id', 'DESC')->get();

        return  view('layouts.material.verified_consumption', compact(['material_consumption', 'material_wastage']));
    }
    public function pending_consumption(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        if ($visiblity_at_site == 'current') {
            $filters_1 = [['material_consumption.status', '=', 'Pending'], ['material_consumption.site_id', '=', $site_id]];
            $filters_2 = [['material_wastage.status', '=', 'Pending'], ['material_wastage.site_id', '=', $site_id]];
        } else {
            $filters_1 = [['material_consumption.status', '=', 'Pending']];
            $filters_2 = [['material_wastage.status', '=', 'Pending']];
        }
        $material_consumption = DB::connection($user_db_conn_name)->table('material_consumption')->leftjoin('materials', 'materials.id', '=', 'material_consumption.material_id')->leftjoin('sites', 'sites.id', '=', 'material_consumption.site_id')->leftjoin('units', 'units.id', '=', 'material_consumption.unit')->leftjoin('users', 'users.id', '=', 'material_consumption.user_id')->select('material_consumption.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user')->where($filters_1)->whereBetween('material_consumption.date', [$min_date, $max_date])->orderBy('material_consumption.id', 'DESC')->get();
        $material_wastage = DB::connection($user_db_conn_name)->table('material_wastage')->leftjoin('materials', 'materials.id', '=', 'material_wastage.material_id')->leftjoin('sites', 'sites.id', '=', 'material_wastage.site_id')->leftjoin('units', 'units.id', '=', 'material_wastage.unit')->leftjoin('users', 'users.id', '=', 'material_wastage.user_id')->select('material_wastage.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user')->where($filters_2)->whereBetween('material_wastage.date', [$min_date, $max_date])->orderBy('material_wastage.id', 'DESC')->get();

        return  view('layouts.material.pending_consumption', compact(['material_consumption', 'material_wastage']));
    }

    public function new_consumption(Request $request)
    {

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();

        return  view('layouts.material.newconsumption', compact(['materials', 'units', 'material_stock_record', 'sites']));
    }
    public function add_new_consumption(Request $request)
    {
        $result = false;
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $length = count($data['site_id']);
        for ($i = 0; $i < $length; $i++) {
            if (isset($request->image[$i])) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image[$i]->extension();
                $request->image[$i]->move(public_path('images/app_images/' . $user_db_conn_name . '/consumption'), $imageName);
                $imagePath = "images/app_images/" . $user_db_conn_name . "/consumption/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
            $consumption_wastage = $data['consumption_wastage'][$i];
            if ($consumption_wastage == 'Consumption') {
                $rawd = [

                    'material_id' => $data['material_id'][$i],
                    'site_id' => $data['site_id'][$i],
                    'unit' => $data['unit'][$i],
                    'qty' => $data['qty'][$i],
                    'user_id' => $user_id,
                    'image' => $imagePath,
                    'remark' => $data['remark'][$i],

                    'date' => $data['date'][$i],
                ];
            } else {
                $rawd = [

                    'material_id' => $data['material_id'][$i],
                    'site_id' => $data['site_id'][$i],
                    'unit' => $data['unit'][$i],
                    'qty' => $data['qty'][$i],
                    'user_id' => $user_id,
                    'image' => $imagePath,
                    'remark' => $data['remark'][$i],
                    'reason' => $data['reason'][$i],

                    'date' => $data['date'][$i],
                ];
            }

            try {
                if ($consumption_wastage == 'Consumption') {
                    $id =  DB::connection($user_db_conn_name)->table('material_consumption')->insertGetId($rawd);
                    addActivity($id, 'material_consumption', "New Material Consumption Created ", 3);

                    if ($status == 'Approved') {
                        $this->approveConsumptionReq($id, $user_db_conn_name);
                    }
                } else {
                    $id =  DB::connection($user_db_conn_name)->table('material_wastage')->insertGetId($rawd);
                    addActivity($id, 'material_wastage', "New Material Wastage Created ", 3);

                    if ($status == 'Approved') {
                        $this->approveWastageReq($id, $user_db_conn_name);
                    }
                }

                $result = true;
            } catch (\Exception $e) {
                $result = false;
            }
        }

        if ($result) {
            return redirect('/verified_consumption')
                ->with('success', 'Material Consumption Created successfully!');
        } else {
            return redirect('/verified_consumption')
                ->with('error', 'Error While Creating Material Consumption. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function approveConsumptionReq($id, $user_db_conn_name)
    {

        $material_consumption = DB::connection($user_db_conn_name)->table('material_consumption')->join('materials', 'materials.id', '=', 'material_consumption.material_id')->join('units', 'units.id', '=', 'material_consumption.unit')->select('material_consumption.*', 'materials.name as material', 'units.name as unitname')->where('material_consumption.id', $id)->get()[0];
        $stock_data = ['site_id' => $material_consumption->site_id, 'material_id' => $material_consumption->material_id, 'qty' => $material_consumption->qty, 'unit' => $material_consumption->unit, 'type' => 'OUT', 'refrence' => 'Consumption', 'refrence_id' => $material_consumption->id];
        $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_consumption->site_id)->where('material_id', '=', $material_consumption->material_id)->where('unit', '=', $material_consumption->unit)->get();
        if (count($check_current_stock) > 0) {
            $current_qty = $check_current_stock[0]->qty;
            $new_qty = $current_qty - $material_consumption->qty;
            if ($new_qty < 0) {
                return redirect('/verified_consumption')
                    ->with('error', 'This Site Does Not Have Enough Stock For This Consumption. Stock Can Not Be Negative!');
            } else {
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($stock_data);
                DB::connection($user_db_conn_name)->table('material_consumption')->where('id', '=', $id)->update(['status' => 'Approved']);
                sendAlertNotification($material_consumption->user_id, 'Your consumption of ' . $material_consumption->material . ' of ' . $material_consumption->qty . ' ' . $material_consumption->unitname . ' has been approved. Check Application For More Information.', 'Material Consumption Approved');
                addActivity($id, 'material_consumption', "Material Consumption Approved ", 3);
            }
        } else {
            return redirect('/verified_consumption')
                ->with('error', 'This Site Does Not Have Enough Stock For This Consumption. Stock Can Not Be Negative!');
        }
    }
    public function approveWastageReq($id, $user_db_conn_name)
    {

        $material_wastage = DB::connection($user_db_conn_name)->table('material_wastage')->join('materials', 'materials.id', '=', 'material_wastage.material_id')->join('units', 'units.id', '=', 'material_wastage.unit')->select('material_wastage.*', 'materials.name as material', 'units.name as unitname')->where('material_wastage.id', $id)->get()[0];
        $stock_data = ['site_id' => $material_wastage->site_id, 'material_id' => $material_wastage->material_id, 'qty' => $material_wastage->qty, 'unit' => $material_wastage->unit, 'type' => 'OUT', 'refrence' => 'Wastage', 'refrence_id' => $material_wastage->id];
        $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_wastage->site_id)->where('material_id', '=', $material_wastage->material_id)->where('unit', '=', $material_wastage->unit)->get();
        if (count($check_current_stock) > 0) {
            $current_qty = $check_current_stock[0]->qty;
            $new_qty = $current_qty - $material_wastage->qty;
            if ($new_qty < 0) {
                return redirect('/verified_consumption')
                    ->with('error', 'This Site Does Not Have Enough Stock For This Wastage. Stock Can Not Be Negative!');
            } else {
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($stock_data);
                DB::connection($user_db_conn_name)->table('material_wastage')->where('id', '=', $id)->update(['status' => 'Approved']);
                sendAlertNotification($material_wastage->user_id, 'Your material wastage of ' . $material_wastage->material . ' of ' . $material_wastage->qty . ' ' . $material_wastage->unitname . ' has been approved. Check Application For More Information.', 'Material Wastage Approved');
                addActivity($id, 'material_wastage', "Material Wastage Approved ", 3);
            }
        } else {
            return redirect('/verified_consumption')
                ->with('error', 'This Site Does Not Have Enough Stock For This Wastage. Stock Can Not Be Negative!');
        }
    }

    public function rejectConsumptionReq($id, $user_db_conn_name)
    {
        $material_consumption = DB::connection($user_db_conn_name)->table('material_consumption')->join('materials', 'materials.id', '=', 'material_consumption.material_id')->join('units', 'units.id', '=', 'material_consumption.unit')->select('material_consumption.*', 'materials.name as material', 'units.name as unitname')->where('material_consumption.id', $id)->get()[0];
        $check_entry_approved = DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_consumption->id)->where('refrence', '=', 'Consumption')->get();

        DB::connection($user_db_conn_name)->table('material_consumption')->where('id', '=', $id)->update(['status' => 'Rejected']);
        if (count($check_entry_approved) == 1) {
            DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_consumption->id)->where('refrence', '=', 'Consumption')->delete();
            $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_consumption->site_id)->where('material_id', '=', $material_consumption->material_id)->where('unit', '=', $material_consumption->unit)->get();
            if (count($check_current_stock) >= 0) {
                $current_qty = $check_current_stock[0]->qty;
                $new_qty = $current_qty + $material_consumption->qty;
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
            }
        }
        sendAlertNotification($material_consumption->user_id, 'Your consumption of ' . $material_consumption->material . ' of ' . $material_consumption->qty . ' ' . $material_consumption->unitname . ' has been rejected. Check Application For More Information.', 'Material Consumption Rejected');

        addActivity($id, 'material_consumption', "Material Consumption Rejected ", 3);
    }

    public function rejectWastageReq($id, $user_db_conn_name)
    {
        $material_wastage = DB::connection($user_db_conn_name)->table('material_wastage')->join('materials', 'materials.id', '=', 'material_wastage.material_id')->join('units', 'units.id', '=', 'material_wastage.unit')->select('material_wastage.*', 'materials.name as material', 'units.name as unitname')->where('material_wastage.id', $id)->get()[0];
        $check_entry_approved = DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_wastage->id)->where('refrence', '=', 'Wastage')->get();

        DB::connection($user_db_conn_name)->table('material_wastage')->where('id', '=', $id)->update(['status' => 'Rejected']);
        if (count($check_entry_approved) == 1) {
            DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('refrence_id', '=', $material_wastage->id)->where('refrence', '=', 'Wastage')->delete();
            $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $material_wastage->site_id)->where('material_id', '=', $material_wastage->material_id)->where('unit', '=', $material_wastage->unit)->get();
            if (count($check_current_stock) >= 0) {
                $current_qty = $check_current_stock[0]->qty;
                $new_qty = $current_qty + $material_wastage->qty;
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
            }
        }
        sendAlertNotification($material_wastage->user_id, 'Your wastage of ' . $material_wastage->material . ' of ' . $material_wastage->qty . ' ' . $material_wastage->unitname . ' has been rejected. Check Application For More Information.', 'Material Wastage Rejected');

        addActivity($id, 'material_wastage', "Material Wastage Rejected ", 3);
    }

    public function reject_consumption_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->rejectConsumptionReq($id, $user_db_conn_name);
        return redirect('/verified_consumption')
            ->with('success', 'Material Consumption Rejected Successfully!');
    }


    public function reject_wastage_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->rejectWastageReq($id, $user_db_conn_name);
        return redirect('/verified_consumption')
            ->with('success', 'Material Wastage Rejected Successfully!');
    }

    public function approve_consumption_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->approveConsumptionReq($id, $user_db_conn_name);

        return redirect('/verified_consumption')
            ->with('success', 'Material Consumption Approved Successfully!');
    }
    public function approve_wastage_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $this->approveWastageReq($id, $user_db_conn_name);

        return redirect('/verified_consumption')
            ->with('success', 'Material Wastage Approved Successfully!');
    }
    public function edit_consumption_entry(Request $request)
    {
        $data = array();
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $consumption = DB::connection($user_db_conn_name)->table('material_consumption')->where('id', $id)->get()[0];

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $consumption->site_id) {
            return redirect('/pending_consumption')->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($consumption->site_id)->name . "!");
        }
        if ($consumption->date < $min_date) {
            return redirect('/pending_consumption')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }
        return  view('layouts.material.editMaterialConsumption', compact(['materials', 'units', 'material_stock_record', 'sites', 'consumption']));
    }
    public function edit_wastage_entry(Request $request)
    {
        $data = array();
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $wastage = DB::connection($user_db_conn_name)->table('material_wastage')->where('id', $id)->get()[0];

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $wastage->site_id) {
            return redirect('/pending_consumption')->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($wastage->site_id)->name . "!");
        }
        if ($wastage->date < $min_date) {
            return redirect('/pending_consumption')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }
        return  view('layouts.material.editMaterialWastage', compact(['materials', 'units', 'material_stock_record', 'sites', 'wastage']));
    }

    public function updateconsumptionEntry(Request $request)
    {
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $data['id'];
        $material_consumption = DB::connection($user_db_conn_name)->table('material_consumption')->where('id', $id)->get()[0];

        if (isset($request->image)) {
            if (File::exists($material_consumption->image) && $material_consumption->image != 'images/expense.png') {
                File::delete($material_consumption->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/consumption'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/consumption/" . $imageName;
        } else {
            $imagePath = $material_consumption->image;
        }
        $rawd = [
            'id' => $id,
            'material_id' => $data['material_id'],
            'unit' => $data['unit'],
            'qty' => $data['qty'],
            'image' => $imagePath,
            'remark' => $data['remark'],
            'site_id' => $data['site_id'],
            'status' => 'Pending',
            'user_id' => $user_id,
            'date' => $data['date'],
        ];
        try {
            DB::connection($user_db_conn_name)->table('material_consumption')->upsert($rawd, 'id');
            addActivity($id, 'material_consumption', "Material Consumption Data Updated ", 3);
            if ($status == 'Approved') {
                $this->approveConsumptionReq($id, $user_db_conn_name);
            }
            return redirect('/verified_consumption')
                ->with('success', 'Material Consumption Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_consumption')
                ->with('error', 'Error While Updating Material Consumption. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function updatewastageEntry(Request $request)
    {
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $data['id'];
        $material_wastage = DB::connection($user_db_conn_name)->table('material_wastage')->where('id', $id)->get()[0];

        if (isset($request->image)) {
            if (File::exists($material_wastage->image) && $material_wastage->image != 'images/expense.png') {
                File::delete($material_wastage->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/consumption'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/consumption/" . $imageName;
        } else {
            $imagePath = $material_wastage->image;
        }
        $rawd = [
            'id' => $id,
            'material_id' => $data['material_id'],
            'unit' => $data['unit'],
            'qty' => $data['qty'],
            'image' => $imagePath,
            'remark' => $data['remark'],
            'reason' => $data['reason'],
            'site_id' => $data['site_id'],
            'status' => 'Pending',
            'user_id' => $user_id,
            'date' => $data['date'],
        ];
        try {
            DB::connection($user_db_conn_name)->table('material_wastage')->upsert($rawd, 'id');
            addActivity($id, 'material_wastage', "Material Consumption Data Updated ", 3);
            if ($status == 'Approved') {
                $this->approveWastageReq($id, $user_db_conn_name);
            }
            return redirect('/verified_consumption')
                ->with('success', 'Material Wastage Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/verified_consumption')
                ->with('error', 'Error While Updating Material Wastage. Please Try Again After Reconciling The Statement.!');
        }
    }

    public function update_consumption(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');

        if ($ids != null) {
            if ($request->input('approve_consumption') !== null) {
                foreach ($ids as $id) {
                    $this->approveConsumptionReq($id, $user_db_conn_name);
                }
                return redirect('/pending_consumption')
                    ->with('success', 'Material Consumption Approved successfully!');
            } else if ($request->input('reject_consumption') !== null) {
                foreach ($ids as $id) {
                    $this->rejectConsumptionReq($id, $user_db_conn_name);
                }
                return redirect('/pending_consumption')
                    ->with('success', 'Material Consumption Rejected successfully!');
            }
        } else {
            return redirect('/pending_consumption')
                ->with('error', 'Please Choose Atleast One Material Consumption!');
        }
    }
    public function update_wastage(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            if ($request->input('approve_wastage') !== null) {
                foreach ($ids as $id) {
                    $this->approveWastageReq($id, $user_db_conn_name);
                }
                return redirect('/pending_consumption')
                    ->with('success', 'Material Wastage Approved successfully!');
            } else if ($request->input('reject_wastage') !== null) {
                foreach ($ids as $id) {
                    $this->rejectWastageReq($id, $user_db_conn_name);
                }
                return redirect('/pending_consumption')
                    ->with('success', 'Material Wastage Rejected successfully!');
            }
        } else {
            return redirect('/pending_consumption')
                ->with('error', 'Please Choose Atleast One Material Wastage!');
        }
    }

    // Material Site Transfer


    public function stock_site_transfer(Request $request)
    {

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;
        $entry_at_site = $role_details->entry_at_site;
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        if ($visiblity_at_site == 'current') {
            $material_transfer = DB::connection($user_db_conn_name)->table('material_site_transfers')->leftjoin('materials', 'materials.id', '=', 'material_site_transfers.material_id')->leftjoin('sites as f_site', 'f_site.id', '=', 'material_site_transfers.from_site')->leftjoin('sites as t_site', 't_site.id', '=', 'material_site_transfers.to_site')->leftjoin('units', 'units.id', '=', 'material_site_transfers.unit')->leftjoin('users', 'users.id', '=', 'material_site_transfers.user_id')->select('material_site_transfers.*', 'materials.name as material', 'units.name as unitname', 'f_site.name as f_site', 't_site.name as t_site', 'users.name as user')->where(['material_site_transfers.from_site', '=', $site_id])->orWhere(['material_site_transfers.to_site', '=', $site_id])->whereBetween('material_site_transfers.date', [$min_date, $max_date])->orderBy('material_site_transfers.id', 'DESC')->get();
        } else {
            $material_transfer = DB::connection($user_db_conn_name)->table('material_site_transfers')->leftjoin('materials', 'materials.id', '=', 'material_site_transfers.material_id')->leftjoin('sites as f_site', 'f_site.id', '=', 'material_site_transfers.from_site')->leftjoin('sites as t_site', 't_site.id', '=', 'material_site_transfers.to_site')->leftjoin('units', 'units.id', '=', 'material_site_transfers.unit')->leftjoin('users', 'users.id', '=', 'material_site_transfers.user_id')->select('material_site_transfers.*', 'materials.name as material', 'units.name as unitname', 'f_site.name as f_site', 't_site.name as t_site', 'users.name as user')->whereBetween('material_site_transfers.date', [$min_date, $max_date])->orderBy('material_site_transfers.id', 'DESC')->get();
        }

        return  view('layouts.material.materialSiteTransfers', compact(['material_transfer', 'entry_at_site']));
    }

    public function newMaterialSiteTransfer(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        return  view('layouts.material.transferMaterialSites', compact(['materials', 'units', 'material_stock_record', 'sites']));
    }
    public function newMaterialTransferForm(Request $request)
    {
        $data = $request->input();
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $rawd = [
                'material_id' => $data['material_id'],
                'from_site' => $data['from_site'],
                'to_site' => $data['to_site'],
                'unit' => $data['unit'],
                'qty' => $data['qty'],
                'user_id' => $user_id,
                'remark' => $data['remark'],
                'vehicle_no' => $data['vehicle_no'],
                'date' => $data['date'],
            ];
            $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $data['from_site'])->where('material_id', '=', $data['material_id'])->where('unit', '=', $data['unit'])->get();
            if (count($check_current_stock) > 0) {
                $current_qty = $check_current_stock[0]->qty;
                $new_qty = $current_qty - $data['qty'];
                if ($new_qty > 0) {
                    $id =  DB::connection($user_db_conn_name)->table('material_site_transfers')->insertGetId($rawd);
                    $from_stock_data = ['site_id' => $data['from_site'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['unit'], 'type' => 'OUT', 'refrence' => 'Site Transferred Debit', 'refrence_id' => $id];
                    $to_stock_data = ['site_id' => $data['to_site'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['unit'], 'type' => 'IN', 'refrence' => 'Site Transferred Credit', 'refrence_id' => $id];
                    DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($from_stock_data);
                    DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($to_stock_data);
                    DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
                    $to_site_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $data['to_site'])->where('material_id', '=', $data['material_id'])->where('unit', '=', $data['unit'])->get();
                    if (count($to_site_current_stock) > 0) {
                        $to_current_qty = $to_site_current_stock[0]->qty;
                        $to_new_qty = $to_current_qty + $data['qty'];
                        DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $to_site_current_stock[0]->id)->update(['qty' => $to_new_qty]);
                    } else {
                        $to_new_stock_data = ['material_id' => $data['material_id'], 'site_id' => $data['to_site'], 'qty' => $data['qty'], 'unit' => $data['unit']];
                        DB::connection($user_db_conn_name)->table('material_stock_record')->insert($to_new_stock_data);
                    }

                    addActivity($id, 'material_site_transfers', "New Material Transfer Completed ", 3);
                    return redirect('/stock_site_transfer')
                        ->with('success', 'Material Site Transferred successfully!');
                } else {
                    return redirect('/stock_site_transfer')
                        ->with('error', 'This Site Does Not Have Enough Stock For This Transfer. Stock Can Not Be Negative!');
                }
            } else {
                return redirect('/stock_site_transfer')
                    ->with('error', 'This Site Does Not Have Enough Stock For This Transfer. Stock Can Not Be Negative!');
            }
        } catch (\Exception $e) {
            return redirect('/stock_site_transfer')
                ->with('error', 'Error While Transferring Material Site. Please Try Again After Reconciling The Statement.!');
        }
    }


    public function deleteMaterialTransferForm(Request $request)
    {

        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $transfer = DB::connection($user_db_conn_name)->table('material_site_transfers')->where('id', '=', $id)->first();

        $check_to_site_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $transfer->to_site)->where('material_id', '=', $transfer->material_id)->where('unit', '=', $transfer->unit)->get();
        if (count($check_to_site_stock) > 0) {
            $current_qty = $check_to_site_stock[0]->qty;
            $new_qty = $current_qty - $transfer->qty;
            if ($new_qty >= 0) {

                $from_site_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $transfer->from_site)->where('material_id', '=', $transfer->material_id)->where('unit', '=', $transfer->unit)->get();
                $from_current_qty = $from_site_current_stock[0]->qty;
                $from_new_qty = $from_current_qty + $transfer->qty;
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $from_site_current_stock[0]->id)->update(['qty' => $from_new_qty]);
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_to_site_stock[0]->id)->update(['qty' => $new_qty]);

                DB::connection($user_db_conn_name)->table('material_site_transfers')->where('id', '=', $id)->delete();
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('type', '=', 'OUT')->where('refrence', '=', 'Site Transferred Debit')->where('refrence_id', '=', $id)->delete();
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('type', '=', 'IN')->where('refrence', '=', 'Site Transferred Credit')->where('refrence_id', '=', $id)->delete();


                addActivity(0, 'material_site_transfers', "Material Transfer Deleted ", 3);
                return redirect('/stock_site_transfer')
                    ->with('success', 'Material Site Transfer Deleted successfully!');
            } else {
                return redirect('/stock_site_transfer')
                    ->with('error', 'Target Site Already Used That Material. It Does Not Have Enough Stock To Delete This Entry.!');
            }
        } else {
            return redirect('/stock_site_transfer')
                ->with('error', 'Error While Deleting Material Transfer. Please Try Again After Reconciling The Statement.!');
        }
    }


    // Stock Unit Conversion

    public function stock_unit_conversion(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;
        $entry_at_site = $role_details->entry_at_site;
        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        if ($visiblity_at_site == 'current') {
            $filters = ['material_units_conversion_record.site_id', '=', $site_id];
        } else {
            $filters = [];
        }

        $material_conversion = DB::connection($user_db_conn_name)->table('material_units_conversion_record')->leftjoin('materials', 'materials.id', '=', 'material_units_conversion_record.material_id')->leftjoin('sites', 'sites.id', '=', 'material_units_conversion_record.site_id')->leftjoin('units as f_unit', 'f_unit.id', '=', 'material_units_conversion_record.from_unit')->leftjoin('units as t_unit', 't_unit.id', '=', 'material_units_conversion_record.to_unit')->leftjoin('users', 'users.id', '=', 'material_units_conversion_record.user_id')->select('material_units_conversion_record.*', 'materials.name as material', 'f_unit.name as f_unit', 't_unit.name as t_unit', 'sites.name as site', 'users.name as user')->where($filters)->whereBetween('material_units_conversion_record.date', [$min_date, $max_date])->orderBy('material_units_conversion_record.id', 'DESC')->get();
        return  view('layouts.material.materialConversions', compact(['material_conversion', 'entry_at_site']));
    }
    public function newStockUnitConversion(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $conversion_format = DB::connection($user_db_conn_name)->table('material_conversion_rules')->join('units', 'units.id', '=', 'material_conversion_rules.to_unit')->select('material_conversion_rules.*', 'units.name as to_unit_name')->get();
        return  view('layouts.material.newMaterialConversion', compact(['materials', 'units', 'material_stock_record', 'sites', 'conversion_format']));
    }
    public function newStockUnitConversionForm(Request $request)
    {

        $data = $request->input();
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $rawd = [
                'material_id' => $data['material_id'],
                'site_id' => $data['site_id'],
                'from_unit' => $data['from_unit'],
                'to_unit' => $data['to_unit'],
                'qty' => $data['qty'],
                'user_id' => $user_id,
                'remark' => $data['remark'],
                'updated_qty' => $data['updated_qty'],
                'date' => $data['date'],
            ];
            if ($data['from_unit'] == $data['to_unit']) {
                return redirect('/stock_unit_conversion')
                    ->with('error', 'You Can Not Convert In Same Units!');
            }
            $check_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $data['site_id'])->where('material_id', '=', $data['material_id'])->where('unit', '=', $data['from_unit'])->get();
            if (count($check_current_stock) > 0) {
                $current_qty = $check_current_stock[0]->qty;
                $new_qty = $current_qty - $data['qty'];
                if ($new_qty >= 0) {
                    $id =  DB::connection($user_db_conn_name)->table('material_units_conversion_record')->insertGetId($rawd);
                    $from_stock_data = ['site_id' => $data['site_id'], 'material_id' => $data['material_id'], 'qty' => $data['qty'], 'unit' => $data['from_unit'], 'type' => 'OUT', 'refrence' => 'Unit Conversion Debit', 'refrence_id' => $id];
                    $to_stock_data = ['site_id' => $data['site_id'], 'material_id' => $data['material_id'], 'qty' => $data['updated_qty'], 'unit' => $data['to_unit'], 'type' => 'IN', 'refrence' => 'Unit Conversion Credit', 'refrence_id' => $id];
                    DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($from_stock_data);
                    DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($to_stock_data);
                    DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_current_stock[0]->id)->update(['qty' => $new_qty]);
                    $to_unit_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $data['site_id'])->where('material_id', '=', $data['material_id'])->where('unit', '=', $data['to_unit'])->get();
                    if (count($to_unit_current_stock) > 0) {
                        $to_current_qty = $to_unit_current_stock[0]->qty;
                        $to_new_qty = $to_current_qty + $data['updated_qty'];
                        DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $to_unit_current_stock[0]->id)->update(['qty' => $to_new_qty]);
                    } else {
                        $to_new_stock_data = ['material_id' => $data['material_id'], 'site_id' => $data['site_id'], 'qty' => $data['updated_qty'], 'unit' => $data['to_unit']];
                        DB::connection($user_db_conn_name)->table('material_stock_record')->insert($to_new_stock_data);
                    }

                    addActivity($id, 'material_units_conversion_record', "Material Unit Conversion Completed ", 3);
                    return redirect('/stock_unit_conversion')
                        ->with('success', 'Material Unit Conversion Successfull!');
                } else {
                    return redirect('/stock_unit_conversion')
                        ->with('error', 'This Site Does Not Have Enough Stock Of Selected Unit To Convert. Stock Can Not Be Negative!');
                }
            } else {
                return redirect('/stock_unit_conversion')
                    ->with('error', 'This Site Does Not Have Enough Stock Of Selected Unit To Convert. Stock Can Not Be Negative!');
            }
        } catch (\Exception $e) {
            return redirect('/stock_unit_conversion')
                ->with('error', 'Error While Converting Material Unit. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function deleteStockUnitConversion(Request $request)
    {


        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $conversion = DB::connection($user_db_conn_name)->table('material_units_conversion_record')->where('id', '=', $id)->first();

        $check_to_unit_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $conversion->site_id)->where('material_id', '=', $conversion->material_id)->where('unit', '=', $conversion->to_unit)->get();
        if (count($check_to_unit_stock) > 0) {
            $current_qty = $check_to_unit_stock[0]->qty;
            $new_qty = $current_qty - $conversion->updated_qty;
            if ($new_qty >= 0) {

                $from_unit_current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $conversion->site_id)->where('material_id', '=', $conversion->material_id)->where('unit', '=', $conversion->from_unit)->get();
                $from_current_qty = $from_unit_current_stock[0]->qty;
                $from_new_qty = $from_current_qty + $conversion->qty;
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $from_unit_current_stock[0]->id)->update(['qty' => $from_new_qty]);
                DB::connection($user_db_conn_name)->table('material_stock_record')->where('id', '=', $check_to_unit_stock[0]->id)->update(['qty' => $new_qty]);

                DB::connection($user_db_conn_name)->table('material_units_conversion_record')->where('id', '=', $id)->delete();
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('type', '=', 'OUT')->where('refrence', '=', 'Unit Conversion Debit')->where('refrence_id', '=', $id)->delete();
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('type', '=', 'IN')->where('refrence', '=', 'Unit Conversion Credit')->where('refrence_id', '=', $id)->delete();

                addActivity(0, 'material_units_conversion_record', "Material Unit Conversion Deleted ", 3);
                return redirect('/stock_unit_conversion')
                    ->with('success', 'Material Unit Conversion Deleted successfully!');
            } else {
                return redirect('/stock_unit_conversion')
                    ->with('error', 'Target Site Already Used That Material In Converted Unit. It Does Not Have Enough Stock To Delete This Entry.!');
            }
        } else {
            return redirect('/stock_unit_conversion')
                ->with('error', 'Error While Deleting Material Unit Conversion. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function stock_dashboard(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $site_wise_data = array();
        $whole_comp_data =  DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->join('sites', 'sites.id', '=', 'material_stock_record.site_id')->where('sites.status', '=', 'Active')->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name', 'sites.name as site_name')->orderBy('material_stock_record.site_id')->orderBy('material_stock_record.material_id')->get();
        foreach ($sites as $site) {
            $siteData = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->where('material_stock_record.site_id', '=', $site->id)->select('material_stock_record.*', 'materials.name as material_name', 'units.name as unit_name')->orderBy('material_stock_record.material_id')->get();
            $rawd = ['site_id' => $site->id, 'site_name' => $site->name, 'stock' => $siteData];
            array_push($site_wise_data, $rawd);
        }
        $material_wise_data = array();
        $materials = DB::connection($user_db_conn_name)->table('materials')->get();
        foreach ($materials as $mat) {
            $matData = DB::connection($user_db_conn_name)->table('material_stock_record as msr')
                ->select(
                    'msr.*',
                    'sites.name as site_name',
                    'units.name as unit_name'
                )
                ->join('sites', 'msr.site_id', '=', 'sites.id')
                ->join('units', 'msr.unit', '=', 'units.id')
                ->where('msr.material_id', '=', $mat->id)
                ->where('sites.status', '=', 'Active')
                ->orderBy('msr.site_id')
                ->orderBy('msr.unit')
                ->get();
            $rawd = ['material_id' => $mat->id, 'material_name' => $mat->name, 'stock' => $matData];
            array_push($material_wise_data, $rawd);
        }
        return  view('layouts.material.stockDashboard', compact(['site_wise_data', 'material_wise_data', 'sites', 'materials', 'whole_comp_data']));
    }
public function view_mat_transaction(Request $request){
    $user_db_conn_name = $request->session()->get('comp_db_conn_name');
$mat_id = $request->get('mat_id');
$site_id = $request->get('site_id');
$unit = $request->get('unit');
$current_stock = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials','materials.id','=','material_stock_record.material_id')->join('sites','sites.id','=','material_stock_record.site_id')->join('units','units.id','=','material_stock_record.unit')->where('site_id', '=', $site_id)->where('material_id', '=', $mat_id)->where('unit', '=', $unit)->select('material_stock_record.*','units.name as unit_name','materials.name as material_name','sites.name as site_name')->first();
$transactions = DB::connection($user_db_conn_name)->table('material_stock_transactions')->where('site_id', '=', $site_id)->where('material_id', '=', $mat_id)->where('unit', '=', $unit)->orderBy('id','desc')->get();
return  view('layouts.material.materialTransaction', compact(['transactions', 'current_stock']));

}
    public function reconsilation_list(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $pending_list = DB::connection($user_db_conn_name)->table('material_reconsilation_record as msr')
            ->select(
                'msr.*',
                'sites.name as site_name',
                'r_user.name as requested_by_name',
                'u_user.name as upload_by_name',
                'a_user.name as approved_by_name'
            )
            ->join('sites', 'sites.id', '=', 'msr.site_id')
            ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
            ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
            ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by')
            ->whereIn('msr.status', ['Pending', 'Draft']) // Optimized condition for multiple values
            ->get();
        $submitted_list = DB::connection($user_db_conn_name)->table('material_reconsilation_record as msr')
            ->select(
                'msr.*',
                'sites.name as site_name',
                'r_user.name as requested_by_name',
                'u_user.name as upload_by_name',
                'a_user.name as approved_by_name'
            )
            ->join('sites', 'sites.id', '=', 'msr.site_id')
            ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
            ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
            ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by')
            ->where('msr.status', '=', 'Submitted') // Optimized condition for multiple values
            ->get();
        $verified_list = DB::connection($user_db_conn_name)->table('material_reconsilation_record as msr')->select(
            'msr.*',
            'sites.name as site_name',
            'r_user.name as requested_by_name',
            'u_user.name as upload_by_name',
            'a_user.name as approved_by_name'
        )
            ->join('sites', 'sites.id', '=', 'msr.site_id')
            ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
            ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
            ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by')
            ->whereIn('msr.status', ['Rejected', 'Approved', 'Converted']) // Optimized condition for multiple values
            ->get();

        $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        return  view('layouts.material.reconsilationList', compact(['pending_list', 'submitted_list', 'verified_list', 'sites']));
    }
    public function request_reconsilation(Request $request)
    {
        $site_id = $request->get('site_id');
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $date = Carbon::now()->format('d-m-Y');
        $data = ['site_id' => $site_id, 'requested_by' => $user_id, 'date' => $date];
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->insert($data);
        return redirect('/reconsilation_list')
            ->with('success', 'Stock Reconciliation Requested Successfully!');
    }
    public function view_reconsilation_detail(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $reconsile_record = DB::connection($user_db_conn_name)->table('material_reconsilation_record as msr')->select(
            'msr.*',
            'sites.name as site_name',
            'r_user.name as requested_by_name',
            'u_user.name as upload_by_name',
            'a_user.name as approved_by_name'
        )
            ->join('sites', 'sites.id', '=', 'msr.site_id')
            ->leftJoin('users as r_user', 'r_user.id', '=', 'msr.requested_by')
            ->leftJoin('users as u_user', 'u_user.id', '=', 'msr.upload_by')
            ->leftJoin('users as a_user', 'a_user.id', '=', 'msr.approved_by')
            ->where('msr.id', '=', $id) // Optimized condition for multiple values
            ->first();


        if ($reconsile_record->status == "Pending" || $reconsile_record->status == "Draft") {
            $reconsile_data = DB::connection($user_db_conn_name)->table('material_reconsilation_data')->where('reconsilation_id', '=', $id)->get()->keyBy(function ($item) {
                return $item->material_id . '_' . $item->unit;
            });

            $material_stock_record = DB::connection($user_db_conn_name)->table('material_stock_record')->join('materials', 'materials.id', '=', 'material_stock_record.material_id')->join('units', 'units.id', '=', 'material_stock_record.unit')->select('material_stock_record.*', 'units.name as unit_name', 'materials.name as material_name')->where('material_stock_record.site_id', '=', $reconsile_record->site_id)->get();

            $data = array();
            foreach ($material_stock_record as $stock) {
                $key = $stock->material_id . '_' . $stock->unit;
                $stock->system_qty = $stock->qty;
                $stock->reconsiled_qty = isset($reconsile_data[$key]) ? $reconsile_data[$key]->reconsiled_qty : null;
                if ($stock->reconsiled_qty != null) {

                    $stock->difference = $stock->qty - $stock->reconsiled_qty;
                } else {
                    $stock->difference = null;
                }
                array_push($data, $stock);
            }
        } else {
            $data = DB::connection($user_db_conn_name)->table('material_reconsilation_data')->join('materials', 'materials.id', '=', 'material_reconsilation_data.material_id')->join('units', 'units.id', '=', 'material_reconsilation_data.unit')->select('material_reconsilation_data.*', 'units.name as unit_name', 'materials.name as material_name')->where('material_reconsilation_data.reconsilation_id', '=', $id)->get();
        }
        return  view('layouts.material.viewReconsilitation', compact(['data', 'reconsile_record']));
    }

    public function approve_reconsilation_detail(Request $request) {
        $id = $request->get('id');
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $id)->update(['status' => 'Approved', 'approved_by' => $user_id]);
        return redirect('/reconsilation_list')
        ->with('success', 'Reconciliation Data Approved Successfully!');
    }

    public function reject_reconsilation_detail(Request $request) {
        $id = $request->get('id');
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $id)->update(['status' => 'Rejected', 'approved_by' => $user_id]);
        return redirect('/reconsilation_list')
        ->with('success', 'Reconciliation Data Rejected Successfully!');
    }
    public function update_stock_reconsilation(Request $request)
    {
        $id = $request->get('id');
        $user_id = session()->get('uid');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $reconsilation_data = DB::connection($user_db_conn_name)->table('material_reconsilation_data')->where('reconsilation_id', '=', $id)->get();
        $reconsilation_record = DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $id)->first();

        foreach ($reconsilation_data as $rec) {
            if ($rec->difference > 0) {
                $trans_data = ['site_id' => $reconsilation_record->site_id, 'material_id' => $rec->material_id, 'qty' => $rec->difference, 'unit' => $rec->unit, 'type' => 'OUT', 'refrence' => 'Reconcile Stock Debit', 'refrence_id' => $id];
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($trans_data);
            } else if ($rec->difference < 0) {
                $positive_qty = $rec->difference * -1;
                $trans_data = ['site_id' => $reconsilation_record->site_id, 'material_id' => $rec->material_id, 'qty' => $positive_qty, 'unit' => $rec->unit, 'type' => 'IN', 'refrence' => 'Reconcile Stock Credit', 'refrence_id' => $id];
                DB::connection($user_db_conn_name)->table('material_stock_transactions')->insert($trans_data);
            }
            DB::connection($user_db_conn_name)->table('material_stock_record')->where('site_id', '=', $reconsilation_record->site_id)->where('material_id', '=', $rec->material_id)->where('unit', '=', $rec->unit)->update(['qty' => $rec->reconsiled_qty]);
           
        }
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $id)->update(['status' => 'Converted', 'approved_by' => $user_id,'stock_updated'=>'Yes']);

        return redirect('/reconsilation_list')
        ->with('success', 'Stock Updated Successfully!');

    }
    public function upload_reconsilation(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $reconsilation_id = $request->get('reconsilation_id');
        $user_id = session()->get('uid');
        $data = $request->input();
        if(isset($data['reconsiled_qty'])){
        $length = count($data['reconsiled_qty']);
        $new_data = array();
       
        for ($i = 0; $i < $length; $i++) {
            $rawd = [
                'reconsilation_id' => $reconsilation_id,
                'material_id' => $data['material_id'][$i],
                'system_qty' => $data['system_qty'][$i],
                'reconsiled_qty' => $data['reconsiled_qty'][$i],
                'unit' => $data['unit'][$i],
                'difference' => $data['difference'][$i]
            ];
            array_push($new_data, $rawd);
        }
        DB::connection($user_db_conn_name)->table('material_reconsilation_data')->where('reconsilation_id', '=', $reconsilation_id)->delete();
        DB::connection($user_db_conn_name)->table('material_reconsilation_data')->insert($new_data);
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $reconsilation_id)->update(['status' => 'Submitted', 'upload_by' => $user_id]);
        return redirect('/reconsilation_list')
            ->with('success', 'Stock Reconciliation Data Uploaded Successfully!');
    }else{
        return redirect('/reconsilation_list')
        ->with('error', 'This Site Has No Material Stock.');

    }
    }
    public function delete_reconsilation(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('material_reconsilation_record')->where('id', '=', $id)->delete();
        DB::connection($user_db_conn_name)->table('material_reconsilation_data')->where('reconsilation_id', '=', $id)->delete();
        return redirect('/reconsilation_list')
            ->with('success', 'Stock Reconciliation Request Deleted Successfully!');
    }




}
