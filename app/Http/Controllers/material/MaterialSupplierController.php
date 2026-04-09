<?php

namespace App\Http\Controllers\material;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use PDF;
use File;
use Response;

class MaterialSupplierController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = [];
        $data['cost_categories'] = getallCostCategories();

        return  view('layouts.material.materialsupplier')->with('data', json_encode($data));
    }

    public function get_material_supplier_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $query = DB::connection($user_db_conn_name)->table('material_supplier')
            ->leftJoin('expense_head', 'expense_head.id', '=', 'material_supplier.cost_category_id')
            ->select('material_supplier.*', 'expense_head.name as category_name');

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%")
                  ->orWhere('gstin', 'LIKE', "%{$search}%")
                  ->orWhere('bank_ac', 'LIKE', "%{$search}%")
                  ->orWhere('bank_ifsc', 'LIKE', "%{$search}%")
                  ->orWhere('bank_name', 'LIKE', "%{$search}%")
                  ->orWhere('bank_ac_holder', 'LIKE', "%{$search}%")
                  ->orWhere('expense_head.name', 'LIKE', "%{$search}%")
                  ->orWhere('material_supplier.status', 'LIKE', "%{$search}%");
            });
        }

        $filteredRecords = $query->count();

        $orderColumnIndex = $request->input('order.0.column');
        $orderDir = $request->input('order.0.dir', 'asc');
        
        $columns = [
            2 => 'name',
            3 => 'address',
            4 => 'gstin',
            5 => 'bank_ac',
            6 => 'bank_ifsc',
            7 => 'bank_name',
            8 => 'bank_ac_holder',
            9 => 'expense_head.name',
            10 => 'status'
        ];
        
        if (isset($columns[$orderColumnIndex])) {
            $query->orderBy($columns[$orderColumnIndex], $orderDir);
        } else {
            $query->orderBy('id', 'desc');
        }

        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        
        if ($length != -1) {
            $query->skip($start)->take($length);
        }

        $data = $query->get();

        $formattedData = [];
        $i = $start + 1;
        
        $can_certify = checkmodulepermission(3, 'can_certify') == 1;
        $can_edit = checkmodulepermission(3, 'can_edit') == 1;
        $can_delete = checkmodulepermission(3, 'can_delete') == 1;

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="item_checkbox check_item" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            
            $name = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->name).'</a>';
            $address = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->address).'</a>';
            $gstin = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->gstin).'</a>';
            $bank_ac = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->bank_ac).'</a>';
            $bank_ifsc = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->bank_ifsc).'</a>';
            $bank_name = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->bank_name).'</a>';
            $bank_ac_holder = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->bank_ac_holder).'</a>';
            $category = '<a class="single-user-name" href="#">'.htmlspecialchars((string)$row->category_name).'</a>';
            
            $statusHtml = '';
            if ($row->status == 'Active') {
                if ($can_certify) {
                    $statusHtml = '<span onclick="updateuserstatus(\''.$ddid.'\',\'Deactive\')" class="badge badge-success">'.$row->status.'</span>';
                } else {
                    $statusHtml = '<span class="badge badge-success">'.$row->status.'</span>';
                }
            } else {
                if ($can_certify) {
                    $statusHtml = '<span onclick="updateuserstatus(\''.$ddid.'\',\'Active\')" class="badge badge-danger">'.$row->status.'</span>';
                } else {
                    $statusHtml = '<span class="badge badge-danger">'.$row->status.'</span>';
                }
            }
            
            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" type="button" onclick="editdata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
            }
            if ($can_delete && isMaterialSupplierDeletable($ddid)) {
                $actionHtml .= '<button title="Delete" type="button" onclick="deletedata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-delete"></i></button>';
            }

            $formattedData[] = [
                $checkbox,
                $i++,
                $name,
                $address,
                $gstin,
                $bank_ac,
                $bank_ifsc,
                $bank_name,
                $bank_ac_holder,
                $category,
                $statusHtml,
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
    public function update_material_supplier_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id, 'material_supplier', "Material Supplier Status Update - " . $status, 3);
        if ($status == 'Active') {
            return redirect('/materialsupplier')
                ->with('success', 'Material Supplier Activated!');
        } else {
            return redirect('/materialsupplier')
                ->with('success', 'Material Supplier Deactivated!');
        }
    }
    public function addmaterialsupplier(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $gstin = $request->input('gstin');
        $bank_ac = $request->input('bank_ac');
        $bank_ifsc = $request->input('bank_ifsc');
        $bank_name = $request->input('bank_name');
        $bank_ac_holder = $request->input('bank_ac_holder');
        $cost_category_id = $request->input('cost_category_id');
        $data = [
            'name' => $name,
            'address' => $address,
            'gstin' => $gstin,
            'bank_ac' => $bank_ac,
            'bank_ifsc' => $bank_ifsc,
            'bank_name' => $bank_name,
            'bank_ac_holder' => $bank_ac_holder,
            'cost_category_id' => $cost_category_id
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('material_supplier')->insertGetId($data);
            addActivity($id, 'material_supplier', "New Material Supplier Created", 3);
            DB::connection($user_db_conn_name)->table('contact_profile')->insert(['comp_name' => $name, 'contact_name' => $name, 'category' => 'Material Supplier']);
            return redirect('/materialsupplier')
                ->with('success', 'Material Supplier Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/materialsupplier')
                    ->with('error', 'Material Supplier Already Exists!');
            } else {
                return redirect('/material')
                    ->with('error', 'Error While Creating Material Supplier!');
            }
        }
    }
    public function updatematerialsupplier(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $address = $request->input('address');
        $gstin = $request->input('gstin');
        $bank_ac = $request->input('bank_ac');
        $bank_ifsc = $request->input('bank_ifsc');
        $bank_name = $request->input('bank_name');
        $bank_ac_holder = $request->input('bank_ac_holder');
        $cost_category_id = $request->input('cost_category_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $id)->update([
            'name' => $name, 
            'address' => $address, 
            'gstin' => $gstin, 
            'bank_ac' => $bank_ac, 
            'bank_ifsc' => $bank_ifsc, 
            'bank_name' => $bank_name, 
            'bank_ac_holder' => $bank_ac_holder,
            'cost_category_id' => $cost_category_id
        ]);
        addActivity($id, 'material_supplier', "Material Supplier Updated", 3);
        return redirect('/materialsupplier');
    }
    public function edit_materialsupplier(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['cost_categories'] = getallCostCategories();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->get();
        return  view('layouts.material.materialsupplier')->with('data', json_encode($data));
    }

    public function bulk_action(Request $request)
    {
        $ids = $request->input('check_list');
        $action = $request->input('bulk_action');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        if (empty($ids)) {
            return redirect('/materialsupplier')->with('error', 'Please select at least one record.');
        }

        try {
            DB::connection($user_db_conn_name)->beginTransaction();

            if ($action == 'delete') {
                foreach ($ids as $id) {
                    if (isMaterialSupplierDeletable($id)) {
                        DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->delete();
                        addActivity(0, 'material_supplier', "Bulk Deleted Material Supplier", 3);
                    }
                }
                DB::connection($user_db_conn_name)->commit();
                return redirect('/materialsupplier')->with('success', 'Selected Deletable Suppliers Deleted Successfully!');
            } elseif ($action == 'active') {
                DB::connection($user_db_conn_name)->table('material_supplier')->whereIn('id', $ids)->update(['status' => 'Active']);
                addActivity(0, 'material_supplier', "Bulk Activated Material Suppliers", 3);
                DB::connection($user_db_conn_name)->commit();
                return redirect('/materialsupplier')->with('success', 'Selected Material Suppliers Activated!');
            } elseif ($action == 'deactive') {
                DB::connection($user_db_conn_name)->table('material_supplier')->whereIn('id', $ids)->update(['status' => 'Deactive']);
                addActivity(0, 'material_supplier', "Bulk Deactivated Material Suppliers", 3);
                DB::connection($user_db_conn_name)->commit();
                return redirect('/materialsupplier')->with('success', 'Selected Material Suppliers Deactivated!');
            }

            DB::connection($user_db_conn_name)->commit();
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/materialsupplier')->with('error', 'Error processing bulk action!');
        }

        return redirect('/materialsupplier');
    }
    public function delete_materialsupplier(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('material_entry')->where('supplier', '=', $id)->get();
        $material_supplier = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->get()[0]->name;
        if (Count($check) > 0) {
            return redirect('/materialsupplier')
                ->with('error', 'Material Supplier Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->delete();
            addActivity(0, 'material_supplier', "Material Supplier Deleted - " . $material_supplier, 3);
            return redirect('/materialsupplier')
                ->with('success', 'Material Supplier Deleted Successfully!');
        }
    }
    public function bulk_edit_supplier(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/materialsupplier')->with('error', 'Please select at least one supplier to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('material_supplier')->whereIn('id', $ids)->get();

        return view('layouts.material.bulk_edit_supplier')->with('data', json_encode($data));
    }

    public function update_bulk_supplier(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('id');
        $names = $request->input('name');
        $addresses = $request->input('address');
        $gstins = $request->input('gstin');

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $key => $id) {
                DB::connection($user_db_conn_name)->table('material_supplier')
                    ->where('id', $id)
                    ->update([
                        'name' => $names[$key],
                        'address' => $addresses[$key],
                        'gstin' => $gstins[$key]
                    ]);
                addActivity($id, 'material_supplier', "Material Supplier Updated via Bulk Edit", 3);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/materialsupplier')->with('success', 'Material Suppliers Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/materialsupplier')->with('error', 'Error while updating bulk suppliers!');
        }
    }
}
