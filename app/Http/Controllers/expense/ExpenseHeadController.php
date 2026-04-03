<?php

namespace App\Http\Controllers\expense;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Response;
use File;
use PDF;

class ExpenseHeadController extends Controller
{
    //
    public function index(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = DB::connection($user_db_conn_name)->table('expense_head')->get();

        return  view('layouts.expense.head')->with('data', json_encode($data));
    }
    public function addexpensehead(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('expense_head')->insertGetId($data);
            addActivity($id, 'expense_head', "New Expense Head Created", 2);
            return redirect('/expense_head')
                ->with('success', 'Expense Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/expense_head')
                    ->with('error', 'Expense Head Already Exists!');
            } else {
                return redirect('/expense_head')
                    ->with('error', 'Error While Creating Expense Head!');
            }
        }
    }
    public function updateexpensehead(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('expense_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'expense_head', "Expense Head Updated", 2);
            return redirect('/expense_head')
                ->with('success', 'Expense Head Updated Successfully!');;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/expense_head')
                    ->with('error', 'Expense Head Already Exists!');
            } else {
                return redirect('/expense_head')
                    ->with('error', 'Error While Updating Expense Head!');
            }
        }
    }
    public function edit_expense_head(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->get();
        return  view('layouts.expense.head')->with('data', json_encode($data));
    }
    public function delete_expense_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('expenses')->where('head_id', '=', $id)->get();

        $expense_head = DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->get()[0]->name;

        if (Count($check) > 0) {
            return redirect('/expense_head')
                ->with('error', 'Expense Head Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('expense_head')->where('id', '=', $id)->delete();
            addActivity(0, 'expense_head', "Expense Head Deleted - " . $expense_head, 2);
            return redirect('/expense_head')
                ->with('success', 'Expense Head Deleted Successfully!');
        }
    }

    public function bulk_edit_head(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/expense_head')->with('error', 'Please select at least one head to edit!');
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
                addActivity($id, 'expense_head', "Expense Head Updated via Bulk Edit", 2);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/expense_head')->with('success', 'Expense Heads Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/expense_head')->with('error', 'Error while updating bulk heads!');
        }
    }
}
