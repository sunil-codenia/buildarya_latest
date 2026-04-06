<?php

namespace App\Http\Controllers\material;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class MaterialUnitController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = [];

        return  view('layouts.material.unit')->with('data', json_encode($data));
    }

    public function get_material_unit_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $query = DB::connection($user_db_conn_name)->table('units');

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');
        
        $columns = [
            2 => 'name'
        ];
        
        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('name', 'asc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        
        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();

        $formattedData = [];
        $i = $start + 1;
        
        $can_edit = checkmodulepermission(3, 'can_edit') == 1;
        $can_delete = checkmodulepermission(3, 'can_delete') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="item_checkbox check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            $name = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->name).'</a>';
            
            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editdata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
            }
            if ($can_delete && isMaterialUnitDeletable($ddid)) {
                $actionHtml .= '<button title="Delete" type="button" onclick="deletedata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-delete"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $name,
                $actionHtml
            ];
        }

        return response()->json([
            "draw" => intval($request->input('draw')),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $filteredRecords,
            "data" => $formattedData
        ]);
    }
    public function addmaterialunit(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('units')->insertGetId($data);
            addActivity($id, 'units', "New Material Unit Created ", 3);

            return redirect('/materialunit')
                ->with('success', 'Material Unit Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/materialunit')
                    ->with('error', 'Material Unit Already Exists!');
            } else {
                return redirect('/materialunit')
                    ->with('error', 'Error While Creating Material Unit!');
            }
        }
    }
    public function updatematerialunit(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('units')->where('id', $id)->update(['name' => $name]);
        addActivity($id, 'units', "Material Unit Updated ", 3);

        return redirect('/materialunit');
    }
    public function edit_material_unit(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = [];
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('units')->where('id', '=', $id)->get();
        return  view('layouts.material.unit')->with('data', json_encode($data));
    }

    public function bulk_action(Request $request)
    {
        $ids = $request->input('check_list');
        $action = $request->input('bulk_action');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (empty($ids)) {
            return redirect('/materialunit')->with('error', 'Please select at least one record.');
        }

        try {
            DB::connection($user_db_conn_name)->beginTransaction();

            if ($action == 'delete') {
                foreach ($ids as $id) {
                    if (isMaterialUnitDeletable($id)) {
                        DB::connection($user_db_conn_name)->table('units')->where('id', '=', $id)->delete();
                        addActivity(0, 'units', "Bulk Deleted Material Unit", 3);
                    }
                }
                DB::connection($user_db_conn_name)->commit();
                return redirect('/materialunit')->with('success', 'Selected Deletable Units Deleted Successfully!');
            }
            
            DB::connection($user_db_conn_name)->commit();
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/materialunit')->with('error', 'Error processing bulk action!');
        }

        return redirect('/materialunit');
    }
    public function delete_material_unit(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('material_entry')->where('unit', '=', $id)->get();
        $delete_material_unit = DB::connection($user_db_conn_name)->table('unit')->where('id', '=', $id)->get()[0]->name;
        if (Count($check) > 0) {
            return redirect('/materialunit')
                ->with('error', 'Material Unit Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('units')->where('id', '=', $id)->delete();
            addActivity(0, 'units', "Material Unit Deleted - " . $delete_material_unit, 3);
            return redirect('/materialunit')
                ->with('success', 'Material Unit Deleted Successfully!');
        }
    }

    public function manage_unit_conversion(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $unit_conversions = DB::connection($user_db_conn_name)
            ->table('material_conversion_rules')->join('units as f_unit', 'f_unit.id', '=', 'material_conversion_rules.from_unit')->join('units as t_unit', 't_unit.id', '=', 'material_conversion_rules.to_unit')->where('material_id', '=', $id)
            ->select('material_conversion_rules.id as id', 'material_conversion_rules.conversion_factor', 'f_unit.name as from_unit', 't_unit.name as to_unit')->get();
        $material =  DB::connection($user_db_conn_name)->table('materials')->where('id', '=', $id)->first();
        $units = DB::connection($user_db_conn_name)->table('units')->get();
        return  view('layouts.material.unit_conversion_rules', compact(['unit_conversions', 'material', 'units']));
    }
    public function add_unit_conversion(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $uid = $request->session()->get('uid');
        $material_id = $request->get('material_id');
        $from_unit = $request->get('from_unit');
        $to_unit = $request->get('to_unit');
        $conversion_factor = $request->get('conversion_factor');
        if ($from_unit != $to_unit) {
            $check = DB::connection($user_db_conn_name)->table('material_conversion_rules')->where('material_id', '=', $material_id)->where('from_unit', '=', $from_unit)->where('to_unit', '=', $to_unit)->count();
            if ($check == 0) {
                if ($conversion_factor > 0) {
                    $data = ['material_id' => $material_id, 'from_unit' => $from_unit, 'to_unit' => $to_unit, 'conversion_factor' => $conversion_factor, 'created_by' => $uid];
                    DB::connection($user_db_conn_name)->table('material_conversion_rules')->insert($data);
                    return redirect('/manage_unit_conversion?id=' . $material_id)
                        ->with('success', "Conversion Rule Created Successfully!");
                } else {
                    return redirect('/manage_unit_conversion?id=' . $material_id)
                        ->with('error', "Conversion Factor Can't Be Less Than Or Equal To 0!");
                }
            } else {
                return redirect('/manage_unit_conversion?id=' . $material_id)
                    ->with('error', "Unit Conversion With Same Units Already Available!");
            }
        } else {
            return redirect('/manage_unit_conversion?id=' . $material_id)
                ->with('error', "Both Unit Can't Be Same!");
        }
    }
    public function delete_unit_conversion(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $request->get('id');
        $material_id = DB::connection($user_db_conn_name)->table('material_conversion_rules')->where('id', '=', $id)->first()->material_id;
        DB::connection($user_db_conn_name)->table('material_conversion_rules')->where('id', '=', $id)->delete();

        return redirect('/manage_unit_conversion?id=' . $material_id)
            ->with('success', "Conversion Rule Deleted Successfully!");
    }
    public function bulk_edit_unit(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/materialunit')->with('error', 'Please select at least one unit to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('units')->whereIn('id', $ids)->get();

        return view('layouts.material.bulk_edit_unit')->with('data', json_encode($data));
    }

    public function update_bulk_unit(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('id');
        $names = $request->input('name');

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $key => $id) {
                DB::connection($user_db_conn_name)->table('units')
                    ->where('id', $id)
                    ->update(['name' => $names[$key]]);
                addActivity($id, 'units', "Material Unit Updated via Bulk Edit", 3);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/materialunit')->with('success', 'Material Units Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/materialunit')->with('error', 'Error while updating bulk units!');
        }
    }
}
