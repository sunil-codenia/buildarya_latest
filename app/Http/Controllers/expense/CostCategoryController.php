<?php

namespace App\Http\Controllers\expense;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Response;
use File;
use PDF;

class CostCategoryController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = [];

        return  view('layouts.expense.cost_category')->with('data', json_encode($data));
    }

    public function get_cost_category_ajax(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        
        $query = DB::connection($user_db_conn_name)->table('expense_head');

        $totalRecords = $query->count();

        $search = $request->input('search.value');
        if (!empty($search)) {
            $query->where('name', 'LIKE', "%{$search}%");
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
        
        $can_edit = checkmodulepermission(12, 'can_edit') == 1; // Module 12: Cost Category
        $can_delete = checkmodulepermission(12, 'can_delete') == 1; // Module 12: Cost Category

        foreach ($data as $row) {
            $ddid = $row->id;
            
            $checkbox = '<div class="checkbox"><input id="check_'.$ddid.'" name="check_list[]" class="item_checkbox" type="checkbox" value="'.$ddid.'"><label for="check_'.$ddid.'">&nbsp;</label></div>';
            
            $name = '<a class="single-user-name" href="#">'.htmlspecialchars($row->name).'</a>';
            
            $actionHtml = '';
            if ($can_edit) {
                $actionHtml .= '<button title="Edit" onclick="editdata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-edit"></i></button>&nbsp;';
            }
            if ($can_delete && isExpenseHeadDeletable($ddid)) {
                $actionHtml .= '<button title="Delete" onclick="deletedata(\''.$ddid.'\')" style="all:unset"><i class="zmdi zmdi-delete"></i></button>';
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
    public function addcostcategory(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('expense_head')->insertGetId($data);
            addActivity($id, 'expense_head', "New Cost Category Created", 12);
            return redirect('/cost_category')
                ->with('success', 'Cost Category Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/cost_category')
                    ->with('error', 'Cost Category Already Exists!');
            } else {
                return redirect('/cost_category')
                    ->with('error', 'Error While Creating Cost Category!');
            }
        }
    }
    public function updatecostcategory(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('expense_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'expense_head', "Cost Category Updated", 12);
            return redirect('/cost_category')
                ->with('success', 'Cost Category Updated Successfully!');;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/cost_category')
                    ->with('error', 'Cost Category Already Exists!');
            } else {
                return redirect('/cost_category')
                    ->with('error', 'Error While Updating Cost Category!');
            }
        }
    }
    public function edit_cost_category(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = [];
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->get();
        return  view('layouts.expense.cost_category')->with('data', json_encode($data));
    }
    public function delete_cost_category(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('expenses')->where('head_id', '=', $id)->get();

        $expense_head = DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->get()[0]->name;

        if (Count($check) > 0) {
            return redirect('/cost_category')
                ->with('error', 'Cost Category Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->delete();
            addActivity(0, 'expense_head', "Cost Category Deleted - " . $expense_head, 12);
            return redirect('/cost_category')
                ->with('success', 'Cost Category Deleted Successfully!');
        }
    }

    public function bulk_edit_head(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/cost_category')->with('error', 'Please select at least one Cost Category to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('expense_head')->whereIn('id', $ids)->get();

        return view('layouts.expense.bulk_edit_head')->with('data', json_encode($data));
    }

    public function update_bulk_head(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('id');
        $names = $request->input('name');

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $key => $id) {
                DB::connection($user_db_conn_name)->table('expense_head')
                    ->where('id', $id)
                    ->update(['name' => $names[$key]]);
                addActivity($id, 'expense_head', "Cost Category Updated via Bulk Edit", 12);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/cost_category')->with('success', 'Cost Categories Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/cost_category')->with('error', 'Error while updating bulk Cost Categories!');
        }
    }

    public function expenseHeadReport(Request $request)
    {
        // Placeholder for the report method if needed
        return redirect('/cost_category');
    }
}
