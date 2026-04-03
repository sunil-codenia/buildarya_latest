<?php

namespace App\Http\Controllers\expense;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;
use File;
use Response;




class ExpensePartyController extends Controller
{
    public function expense_party(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $query = DB::connection($user_db_conn_name)->table('expense_party');

        if ($request->get('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->get('site_id') && $request->get('site_id') != 'all') {
            $query->where('site_id', $request->get('site_id'));
        }

        if ($request->get('from_date') && $request->get('to_date')) {
            $from = date('Y-m-d 00:00:00', strtotime($request->get('from_date')));
            $to = date('Y-m-d 23:59:59', strtotime($request->get('to_date')));
            $query->whereBetween('create_datetime', [$from, $to]);
        }

        $data = $query->get();

        return  view('layouts.expense.party')->with('data', json_encode($data));
    }
    public function addexpenseparty(Request $request)
    {
        $name = $request->input('name');
        $pan = $request->input('pan_no');
        $address = $request->input('address');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $site_id = session()->get('site_id');
        $data = [
            'name' => $name,
            'address' => $address,
            'pan_no' => $pan,
            'site_id' => $site_id,
            'status' => $status
        ];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
           $id = DB::connection($user_db_conn_name)->table('expense_party')->insertGetId($data);
           addActivity($id,'expense_party',"New Expense Party Created",2);
            return redirect('/expense_party')
                ->with('success', 'Expense Party Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/expense_party')
                    ->with('error', 'Expense Party Already Exists!');
            } else {
                return redirect('/expense_party')
                    ->with('error', 'Error While Creating Expense Party!');
            }
        }
    }
    public function updateexpenseparty(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $address = $request->input('address');
        $pan_no = $request->input('pan_no');

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('expense_party')->where('id', $id)->update(['name' => $name, 'address' => $address, 'pan_no' => $pan_no]);
        addActivity($id,'expense_party',"Expense Party Updated",2);
        return redirect('/expense_party');
    }
    public function edit_expense_party(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data['data'] = DB::connection($user_db_conn_name)->table('expense_party')->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->get();
        return  view('layouts.expense.party')->with('data', json_encode($data));
    }
    public function delete_expense_party(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('expenses')->where('party_type','=','expense')->where('party_id', '=', $id)->get();
        $expense_party = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->get()[0]->name;

        if (Count($check) > 0) {
            return redirect('/expense_party')
                ->with('error', 'Expense party Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->delete();
            addActivity(0,'expense_party',"Expense Party Deleted-".$expense_party,2);
            return redirect('/expense_party')
                ->with('success', 'Expense party Deleted Successfully!');
        }
    }
    public function update_expense_party_status(Request $request)
    {
        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $party = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->first(); 
        DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->update(['status' => $status]);
        addActivity($id,'expense_party',"Expense Party Status Changed To ".$status,2);

        if ($status == 'Active') {
            if($party->status == 'Pending'){
                DB::connection($user_db_conn_name)->table('contact_profile')->insert(['comp_name'=>$party->name,'contact_name'=>$party->name,'category'=>'Expense Party']);
            }
            return redirect('/expense_party')
                ->with('success', 'Expense Party Activated!');
        } else {
            return redirect('/expense_party')
                ->with('success', 'Expense Party Deactivated!');
        }
    }

    public function bulk_edit_party(Request $request)
    {
        $ids = $request->input('check_list');
        if (empty($ids)) {
            return redirect('/expense_party')->with('error', 'Please select at least one party to edit!');
        }

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('expense_party')->whereIn('id', $ids)->get();

        return view('layouts.expense.bulk_edit_party')->with('data', json_encode($data));
    }

    public function update_bulk_party(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('id');
        $names = $request->input('name');
        $addresses = $request->input('address');
        $pan_nos = $request->input('pan_no');

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $key => $id) {
                DB::connection($user_db_conn_name)->table('expense_party')
                    ->where('id', $id)
                    ->update([
                        'name' => $names[$key],
                        'address' => $addresses[$key],
                        'pan_no' => $pan_nos[$key]
                    ]);
                addActivity($id, 'expense_party', "Expense Party Updated via Bulk Edit", 2);
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/expense_party')->with('success', 'Expense Parties Updated Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/expense_party')->with('error', 'Error while updating bulk parties!');
        }
    }

    public function update_bulk_party_status(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $ids = $request->input('check_list');
        $status = $request->input('status');

        if (empty($ids)) {
            return redirect('/expense_party')->with('error', 'Please select at least one party!');
        }

        try {
            DB::connection($user_db_conn_name)->beginTransaction();
            foreach ($ids as $id) {
                $party = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->first();
                DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->update(['status' => $status]);
                addActivity($id, 'expense_party', "Expense Party Status Changed To " . $status . " via Bulk Action", 2);

                if ($status == 'Active' && $party->status == 'Pending') {
                    DB::connection($user_db_conn_name)->table('contact_profile')->insert([
                        'comp_name' => $party->name,
                        'contact_name' => $party->name,
                        'category' => 'Expense Party'
                    ]);
                }
            }
            DB::connection($user_db_conn_name)->commit();
            return redirect('/expense_party')->with('success', 'Selected Parties ' . $status . 'd Successfully!');
        } catch (\Exception $e) {
            DB::connection($user_db_conn_name)->rollBack();
            return redirect('/expense_party')->with('error', 'Error while updating bulk status!');
        }
    }
}
