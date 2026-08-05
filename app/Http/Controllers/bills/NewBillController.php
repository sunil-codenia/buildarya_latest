<?php

namespace App\Http\Controllers\bills;

use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Response;
use App\Exports\SiteBillExport;
use File;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MaterialExport;

class NewBillController extends Controller
{
    //
    public function verified_bill(Request $request)
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
        $query = DB::connection($user_db_conn_name)->table('new_bill_entry');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'new_bill_entry.site_id');
            $filters = [['new_bill_entry.status', '!=', 'Pending']];
        } else {
            $filters = [['new_bill_entry.status', '!=', 'Pending']];
        }
        $data = $query->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')->select('new_bill_entry.*', 'sites.name as site', 'users.name as user', 'bills_party.name as party')->where($filters)->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])->orderBy('new_bill_entry.create_datetime', 'desc')->get();

        return  view('layouts.bills.verified')->with('data', json_encode($data));
    }
    public function pending_bill(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        if ($from_date && $to_date) {
            $min_date = date('Y-m-d', strtotime($from_date));
            $max_date = date('Y-m-d', strtotime($to_date));
        } else {
            $dates = getdurationdates($view_duration);
            $min_date = date('Y-m-d', strtotime($dates['min']));
            $max_date = date('Y-m-d', strtotime($dates['max']));
        }

        $req_site_id = $request->get('site_id');
        $query = DB::connection($user_db_conn_name)->table('new_bill_entry');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'new_bill_entry.site_id');
            $filters = [['new_bill_entry.status', '=', 'Pending']];
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['new_bill_entry.status', '=', 'Pending'], ['new_bill_entry.site_id', '=', $req_site_id]];
            } else {
                $filters = [['new_bill_entry.status', '=', 'Pending']];
            }
        }

        $data = $query->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')->select('new_bill_entry.*', 'sites.name as site', 'users.name as user', 'bills_party.name as party')->where($filters)->whereBetween('new_bill_entry.billdate', [$min_date, $max_date])->orderBy('new_bill_entry.create_datetime', 'desc')->get();

        return  view('layouts.bills.pending')->with('data', json_encode($data));
    }
    public function new_bill(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->whereIn('status', ['Active', 'Pending'])->get();
        $data['sites'] = getallActivesites();

        // Always re-read add_duration fresh from DB (user-level first, role-level fallback)
        // This ensures the date picker is correct even after admin changes the user's setting post-login
        $uid = $request->session()->get('uid');
        $userRow = DB::connection($user_db_conn_name)->table('users')->where('id', $uid)->first();
        if ($userRow && !empty($userRow->add_duration)) {
            $fresh_add_duration = $userRow->add_duration;
        } else {
            $role_id  = $request->session()->get('role');
            $roleRow  = DB::connection($user_db_conn_name)->table('roles')->where('id', $role_id)->first();
            $fresh_add_duration = ($roleRow && !empty($roleRow->add_duration)) ? $roleRow->add_duration : 'anytime';
        }
        // Refresh session so subsequent requests also see the updated value
        $request->session()->put('add_duration', $fresh_add_duration);
        $data['add_duration'] = $fresh_add_duration;

        return view('layouts.bills.newbill')->with('data', json_encode($data));
    }


    public function bill_report(Request $request)
    {
        $sites = getallsites();
        $parties = getallbillparties();
        $works = getallworkslist();

        return view('layouts.bills.bills_report', compact('sites', 'parties', 'works'));
    }

    public function addnewbill(Request $request)
    {
        $bill_items = array();
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $bill_period = $data['bill_from_date'] . " to " . $data['bill_to_date'];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        // Always re-read add_duration fresh from DB for server-side validation
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $uid_chk = session()->get('uid');
        $userRowChk = DB::connection($user_db_conn_name)->table('users')->where('id', $uid_chk)->first();
        if ($userRowChk && !empty($userRowChk->add_duration)) {
            $add_duration = $userRowChk->add_duration;
        } else {
            $role_id_chk = session()->get('role');
            $roleRowChk  = DB::connection($user_db_conn_name)->table('roles')->where('id', $role_id_chk)->first();
            $add_duration = ($roleRowChk && !empty($roleRowChk->add_duration)) ? $roleRowChk->add_duration : 'anytime';
        }
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        if (strtotime($data['bill_date']) < strtotime($min_date) || strtotime($data['bill_date']) > strtotime($max_date)) {
            return redirect()->back()->with('error', "You don't have permission to add entry for date: " . $data['bill_date']);
        }

        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill_party_id'])->first();
        if ($party_status && $party_status->status == 'Active') {
            if (isset($data['item'])) {
                $length = count($data['item']);
                $amount = 0;
                if ($length > 0) {
                    for ($i = 0; $i < $length; $i++) {
                        $amount += ($data['rate'][$i] * $data['qty'][$i]);
                    }
                    $attachments = [];
                    if ($request->hasFile('attachments')) {
                        foreach ($request->file('attachments') as $file) {
                            $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                            $file->move(public_path('images/app_images/' . $user_db_conn_name . '/bill'), $fileName);
                            $attachments[] = 'images/app_images/' . $user_db_conn_name . '/bill/' . $fileName;
                        }
                    }
                    $billdata = [
                        'party_id' => $data['bill_party_id'],
                        'bill_no' => $data['bill_no'],
                        'site_id' => $data['bill_site_id'],
                        'billdate' => $data['bill_date'],
                        'bill_period' => $bill_period,
                        'user_id' => $user_id,
                        'status' => $status,
                        'amount' => $amount,
                        'remark' => $data['remark'],
                        'attachments' => count($attachments) > 0 ? json_encode($attachments) : null,
                    ];
                    $bill_id = DB::connection($user_db_conn_name)->table('new_bill_entry')->insertGetId($billdata);
                    addActivity($bill_id, 'new_bill_entry', "New Bill Created - " . $data['bill_no'], 4);
                    for ($i = 0; $i < $length; $i++) {
                        $rawd = [
                            'work_id' => $data['item'][$i],
                            'unit' => $data['unit'][$i],
                            'rate' => $data['rate'][$i],
                            'qty' => $data['qty'][$i],
                            'amount' => $data['rate'][$i] * $data['qty'][$i],
                            'bill_id' => $bill_id
                        ];
                        array_push($bill_items, $rawd);
                    }
                    try {
                        DB::connection($user_db_conn_name)->table('new_bills_item_entry')->insert($bill_items);
                        if ($status == 'Approved') {
                            $this->approve_bill($bill_id, $user_db_conn_name);
                            return redirect('/verified_bill')
                                ->with('success', 'Bill Created successfully!');
                        } else {
                            return redirect('/pending_bill')
                                ->with('success', 'Bill Created successfully!');
                        }
                    } catch (\Exception $e) {
                        return redirect('/new_bill')
                            ->with('error', 'Error While Creating Bill. Please Try Again After Reconciling The Statement.!');
                    }
                } else {
                    return redirect('/new_bill')
                        ->with('error', 'Please Add Atleast One Item To Bill!');
                }
            } else {
                return redirect('/new_bill')
                    ->with('error', 'Please Add Atleast One Item To Bill!');
            }
        } else {
            return redirect('/new_bill')
                ->with('error', 'Bill Party Is Not Active!');
        }
    }


    public function updateBill(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            if ($request->input('approve_bill') !== null) {
                foreach ($ids as $id) {

                    $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->select('bills_party.status as status')->where('new_bill_entry.id', '=', $id)->first();

                    if ($bill && $bill->status == 'Active') {
                        $this->approve_bill($id, $user_db_conn_name);
                    } else {
                        return redirect('/pending_bill')
                            ->with('error', 'Party Is Not Active!');
                    }
                }
                return redirect('/pending_bill')
                    ->with('success', 'Bill/s Approved successfully!');
            } else if ($request->input('reject_bill') !== null) {
                foreach ($ids as $id) {
                    $this->reject_bill($id, $user_db_conn_name);
                }
                return redirect('/pending_bill')
                    ->with('success', 'Bill/s Rejected successfully!');
            }
        } else {
            return redirect('/pending_bill')
                ->with('error', 'Please Choose Atleast One Bill!');
        }
    }
    public function approve_bill_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->select('bills_party.status as status')->where('new_bill_entry.id', '=', $id)->first();

        if ($bill && $bill->status == 'Active') {
            $this->approve_bill($id, $user_db_conn_name);
            return redirect('/verified_bill')
                ->with('success', 'Bill Approved successfully!');
        } else {
            return redirect('/verified_bill')
                ->with('error', 'Party Is Not Active!');
        }
    }
    public function approve_bill($id, $conn)
    {
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->first();
        if (!$bill || $bill->status === 'Approved') {
            return;
        }
        DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->update(['status' => 'Approved']);
        sendAlertNotification($bill->user_id,'Your bill of amount '.$bill->amount.' with bill no. '. $bill->bill_no .' has been approved. Check Application For More Information.','Bill Approved');

        addActivity($id, 'new_bill_entry', "Bill Status Approved", 4);

        $party_statement = [
            'party_id' => $bill->party_id,
            'type' => 'Debit',
            'particular' => $bill->bill_no,
            'bill_no' => $id,
            'create_datetime' => $bill->create_datetime
        ];
        DB::connection($conn)->table('bill_party_statement')->where('bill_no', $id)->delete();

        DB::connection($conn)->table('bill_party_statement')->insert($party_statement);
    }
    public function reject_bill_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $this->reject_bill($id, $user_db_conn_name);
        return redirect('/verified_bill')
            ->with('success', 'Bill Rejected successfully!');
    }
    public function reject_bill($id, $conn)
    {
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->first();
        if (!$bill || $bill->status === 'Rejected') {
            return;
        }
        DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->update(['status' => 'Rejected']);
        sendAlertNotification($bill->user_id,'Your bill of amount '.$bill->amount.' with bill no. '. $bill->bill_no .' has been rejected. Check Application For More Information.','Bill Rejected');
        DB::connection($conn)->table('bill_party_statement')->where('bill_no', '=', $id)->delete();
        addActivity($id, 'new_bill_entry', "Bill Status Rejected", 4);
    }
    public function edit_bill(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('id', '=', $id)->first();
        if (!$bill) {
            return redirect('/verified_bill')->with('error', 'Bill not found!');
        }
        $data['bill'] = $bill;
        $data['bill_items'] = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->whereIn('status', ['Active', 'Pending'])->get();
        $data['sites'] = getallActivesites();

        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $data['bill']->site_id) {
            return redirect('/pending_bill')->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($data['bill']->site_id)->name . "!");
        }
        if ($data['bill']->billdate < $min_date) {
            return redirect('/pending_bill')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }

        return  view('layouts.bills.editbill')->with('data', json_encode($data));
    }

    public function updateEditBill(Request $request)
    {
        $bill_items = array();
        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $bill_id = $data['id'];
        $bill_period = $data['bill_from_date'] . " to " . $data['bill_to_date'];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        $max_date = $duration['max'];

        if (strtotime($data['bill_date']) < strtotime($min_date) || strtotime($data['bill_date']) > strtotime($max_date)) {
            return redirect()->back()->with('error', "You don't have permission to update entry for date: " . $data['bill_date']);
        }

        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill_party_id'])->first();
        if ($party_status && $party_status->status == 'Active') {
            $length = count($data['item']);
            $amount = 0;
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $amount += ($data['rate'][$i] * $data['qty'][$i]);
                }
                $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('id', '=', $bill_id)->first();
                $existing = [];
                if ($bill && !empty($bill->attachments)) {
                    $existing = json_decode($bill->attachments, true) ?: [];
                }
                $remaining = $request->input('existing_attachments', []);
                $to_delete = array_diff($existing, $remaining);
                foreach ($to_delete as $file_path) {
                    if (\File::exists(public_path($file_path))) {
                        \File::delete(public_path($file_path));
                    }
                }
                $attachments = $remaining;
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $fileName = time() . '_' . rand(10000, 99999) . '.' . $file->getClientOriginalExtension();
                        $file->move(public_path('images/app_images/' . $user_db_conn_name . '/bill'), $fileName);
                        $attachments[] = 'images/app_images/' . $user_db_conn_name . '/bill/' . $fileName;
                    }
                }
                $billdata = [
                    'id' => $bill_id,
                    'party_id' => $data['bill_party_id'],
                    'bill_no' => $data['bill_no'],
                    'site_id' => $data['bill_site_id'],
                    'billdate' => $data['bill_date'],
                    'bill_period' => $bill_period,
                    'status' => $status,
                    'amount' => $amount,
                    'remark' => $data['remark'],
                    'attachments' => count($attachments) > 0 ? json_encode($attachments) : null,
                ];
                DB::connection($user_db_conn_name)->table('new_bill_entry')->upsert($billdata, 'id');
                addActivity($bill_id, 'new_bill_entry', "Bill Entry Updated", 4);
                DB::connection($user_db_conn_name)->table('new_bills_item_entry')->where('bill_id', '=', $bill_id)->delete();

                for ($i = 0; $i < $length; $i++) {
                    $rawd = [
                        'work_id' => $data['item'][$i],
                        'unit' => $data['unit'][$i],
                        'rate' => $data['rate'][$i],
                        'qty' => $data['qty'][$i],
                        'amount' => $data['rate'][$i] * $data['qty'][$i],
                        'bill_id' => $bill_id
                    ];
                    array_push($bill_items, $rawd);
                }
                try {
                    DB::connection($user_db_conn_name)->table('new_bills_item_entry')->insert($bill_items);
                    if ($status == 'Approved') {
                        $this->approve_bill($bill_id, $user_db_conn_name);
                        return redirect('/verified_bill')
                            ->with('success', 'Bill Updated successfully!');
                    } else {
                        return redirect('/pending_bill')
                            ->with('success', 'Bill Updated successfully!');
                    }
                } catch (\Exception $e) {
                    return redirect('/edit_bill/?id=' . $bill_id)
                        ->with('error', 'Error While Updating Bill. Please Try Again After Reconciling The Statement.!');
                }
            } else {
                return redirect('/edit_bill/?id=' . $bill_id)
                    ->with('error', 'Please Add Atleast One Item To Bill!');
            }
        } else {
            return redirect('/edit_bill/?id=' . $bill_id)
                ->with('error', 'Bill Party Is Not Active!');
        }
    }






    public function bill_pdf(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->where('new_bill_entry.id', '=', $id)->select('new_bill_entry.*', 'users.name as user', 'sites.name as site')->first();
        if (!$bill) {
            abort(404, 'Bill not found');
        }
        $bill_items = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $bill_party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $bill->party_id)->first();
        $balance = getBillPartyBalance($bill->party_id,$user_db_conn_name);
        $file_name = $bill->bill_no.".pdf";
        $pdf = Pdf::loadView('layouts.bills.pdfs.bill_pdf',compact(['bill','bill_items','bill_party','balance']));
        return $pdf->download($file_name);
       
    }
    public function view_bill(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->where('new_bill_entry.id', '=', $id)->select('new_bill_entry.*', 'users.name as user', 'sites.name as site')->first();
        if (!$bill) {
            return redirect()->back()->with('error', 'Bill not found!');
        }
        $data['bill'] = $bill;
        $data['bill_items'] = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $data['bill_party'] = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill']->party_id)->first();
        $data['balance'] = $this->getpartybalance($data['bill']->party_id);
        return  view('layouts.bills.viewbill')->with('data', json_encode($data));
    }
    public function getpartybalance($id)
    {
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $credit = DB::connection($user_db_conn_name)->table('bill_party_statement')->leftJoin('expenses', 'expenses.id', '=', 'bill_party_statement.expense_id')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Credit')->sum('expenses.amount');
        $debit = DB::connection($user_db_conn_name)->table('bill_party_statement')->leftJoin('new_bill_entry', 'new_bill_entry.id', '=', 'bill_party_statement.bill_no')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Debit')->sum('new_bill_entry.amount');
        return $debit - $credit;
    }




    public function sitebillreport(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $report_code = $request->get('type');
        $start_date = $request->get('start_date');
        $sitename = $request->get('site_id');
        $partyname = $request->get('party_id');
        $headname = $request->get('work_id');
        $end_date = $request->get('end_date');
        addActivity(0, 'new_bill_entry', "Bill Report Generted", 4);

        if ($report_code == 1) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code);
            } else {
                $file_name = "Bill Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToDate', compact('bills', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 2) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code);
            } else {
                $file_name = "Bill Detailed Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $count = 0;
                foreach ($bills as $bill) {
                    $items = DB::connection($user_db_conn_name)
                        ->table('new_bills_item_entry')
                        ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                        ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                        ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                        ->get();
                    $bills[$count++]->items = $items;
                }

                $pdf = Pdf::loadView('layouts.bills.pdfs.accToDateDetailed', compact('bills', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        }else if ($report_code == 3) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, $headname, "", "");
            } else {
                $file_name = "Bill Item Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->leftjoin('new_bills_item_entry', 'new_bill_entry.id', '=', 'new_bills_item_entry.bill_id')

                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name', 'new_bills_item_entry.unit', 'new_bills_item_entry.rate', 'new_bills_item_entry.qty', 'new_bills_item_entry.amount as item_amount')
                    ->where('new_bills_item_entry.work_id', $headname)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $headname = optional(DB::connection($user_db_conn_name)->table('bills_work')->where('id', $headname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToItem', compact('bills', 'start_date', 'end_date', 'headname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 4) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, $headname, $sitename, "");
            } else {
                $file_name = "Bill Item Report At Particular Site (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->leftjoin('new_bills_item_entry', 'new_bill_entry.id', '=', 'new_bills_item_entry.bill_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name', 'new_bills_item_entry.unit', 'new_bills_item_entry.rate', 'new_bills_item_entry.qty', 'new_bills_item_entry.amount as item_amount')
                    ->where('new_bills_item_entry.work_id', $headname)
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $sitename = getSiteDetailsById($sitename)->name;
                $headname = optional(DB::connection($user_db_conn_name)->table('bills_work')->where('id', $headname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToItemAtSite', compact('bills', 'start_date', 'end_date', 'headname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 5) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "", $partyname);
            } else {
                $file_name = "Bill Party Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.party_id', $partyname)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $partyname = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToParty', compact('bills', 'start_date', 'end_date', 'partyname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 6) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "", $partyname);
            } else {
                $file_name = "Bill Party Detailed Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.party_id', $partyname)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $count = 0;
                foreach ($bills as $bill) {
                    $items = DB::connection($user_db_conn_name)
                        ->table('new_bills_item_entry')
                        ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                        ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                        ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                        ->get();
                    $bills[$count++]->items = $items;
                }
                $partyname = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyDetailed', compact('bills', 'start_date', 'end_date', 'partyname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 7) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, $partyname);
            } else {
                $file_name = "Bill Party Report At Particular Site (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.party_id', $partyname)
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $sitename = getSiteDetailsById($sitename)->name;
                $partyname = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyAtSite', compact('bills', 'start_date', 'end_date', 'partyname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 8) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, $partyname);
            } else {
                $file_name = "Bill Party Deatiled Report At Particular Site (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.party_id', $partyname)
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $count = 0;
                foreach ($bills as $bill) {
                    $items = DB::connection($user_db_conn_name)
                        ->table('new_bills_item_entry')
                        ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                        ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                        ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                        ->get();
                    $bills[$count++]->items = $items;
                }
                $sitename = getSiteDetailsById($sitename)->name;
                $partyname = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyAtSiteDetailed', compact('bills', 'start_date', 'end_date', 'partyname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 9) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
            } else {
                $file_name = "Bill Site Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $sitename = getSiteDetailsById($sitename)->name;

                $pdf = Pdf::loadView('layouts.bills.pdfs.accToSite', compact('bills', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 10) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
            } else {
                $file_name = "Bill Site Detailed Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $count = 0;
                foreach ($bills as $bill) {
                    $items = DB::connection($user_db_conn_name)
                        ->table('new_bills_item_entry')
                        ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                        ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                        ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                        ->get();
                    $bills[$count++]->items = $items;
                }
                $sitename = getSiteDetailsById($sitename)->name;

                $pdf = Pdf::loadView('layouts.bills.pdfs.accToSiteDetailed', compact('bills', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        }else if ($report_code == 12) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
            } else {
                $file_name = "Bill Site Detailed With Work Report (" . $start_date . " - " . $end_date . ").pdf";
                $bills = DB::connection($user_db_conn_name)
                    ->table('new_bill_entry')
                    ->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')
                    ->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')
                    ->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')
                    ->select('new_bill_entry.*', 'users.name as user_name', 'sites.name as site_name', 'bills_party.name as party_name')
                    ->where('new_bill_entry.site_id', $sitename)
                    ->whereBetween('new_bill_entry.billdate', [$start_date, $end_date])
                    ->orderBy('new_bill_entry.billdate', 'desc')->get();
                $count = 0;
                foreach ($bills as $bill) {
                    $items = DB::connection($user_db_conn_name)
                        ->table('new_bills_item_entry')
                        ->leftjoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')
                        ->select('new_bills_item_entry.*', 'bills_work.name as work_name')
                        ->where('new_bills_item_entry.bill_id', '=', $bill->id)
                        ->get();
                    $bills[$count++]->items = $items;
                }
                $sitename = getSiteDetailsById($sitename)->name;

                $pdf = Pdf::loadView('layouts.bills.pdfs.accToSiteDetailedWithWork', compact('bills', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 11) {
            if ($type == 1) {
                return $this->exportSiteBillExcel($user_db_conn_name, "","", $report_code, "", "", $partyname);
            } else {
                $party_name = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';

                $file_name = "Bill Party Statement - ".$party_name." .pdf";
                $statement = DB::connection($user_db_conn_name)
                    ->table('bill_party_statement')
                    ->where('bill_party_statement.party_id', $partyname)                  
                    ->orderBy('bill_party_statement.id', 'asc')->get();
                $data = array();
                $total_credit = 0;
                $total_debit = 0;
                foreach ($statement as $statem) {
                    if ($statem->type == 'Credit') {
                        if (!is_null($statem->expense_id)) {
                            $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', $statem->expense_id)->first();
                            if ($expense) {
                                $amount = $expense->amount;
                                $site = optional(getSiteDetailsById($expense->site_id))->name ?? '';
                                $user = optional(getUserDetailsById($expense->user_id))->name ?? '';
                                $total_credit += $amount;
                                $dat = ['date' => $expense->date, 'ref' => 'Expense', 'ref_no' => '', 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $expense->image];
                                array_push($data,$dat);
                            }
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->first();
                            if ($payment) {
                                $amount = $payment->amount;
                                $total_credit += $amount;
                                $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => ''];
                                array_push($data,$dat);
                            }
                        } else if (!is_null($statem->payment_voucher_id)) {
                            $pv = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->first();
                            if ($pv) {
                                $amount = $pv->amount;
                                $site = optional(getSiteDetailsById($pv->site_id))->name ?? '';
                                $user = optional(getUserDetailsById($pv->created_by))->name ?? '';
                                $total_credit += $amount;
                                $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $pv->image];
                                array_push($data,$dat);
                            }
                        }
                    } else {
                        if (!is_null($statem->bill_no)) {
                            $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('id', $statem->bill_no)->first();
                            if ($bill) {
                                $amount = $bill->amount;
                                $site = optional(getSiteDetailsById($bill->site_id))->name ?? '';
                                $user = optional(getUserDetailsById($bill->user_id))->name ?? '';
                                $total_debit += $amount;
                                $dat = ['date' => $bill->billdate, 'ref' => 'Site Bill', 'ref_no' => $bill->bill_no, 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular,'image'=>''];
                                array_push($data,$dat);
                            }
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->first();
                            if ($payment) {
                                $amount = $payment->amount;
                                $total_debit += $amount;
                                $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular, 'image' => ''];
                                array_push($data,$dat);
                            }
                        }
                    }
                }
                usort($data, function($a, $b) {
                    $dateA = strtotime($a['date']);
                    $dateB = strtotime($b['date']);
                    return $dateA - $dateB;
                });

                $partybalance = getBillPartyBalance($partyname);
                
                $pdf = Pdf::loadView('layouts.bills.pdfs.partyStatement', compact('party_name', 'data', 'total_credit', 'total_debit', 'partybalance'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 13) {
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 1);
            } else {
                $file_name = "Material Date Report (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $pdf = Pdf::loadView('layouts.material.pdfs.accToDate', compact('material', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 14) {
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 2, $sitename, "", "");
            } else {
                $file_name = "Material Site Report (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->where('material_entry.site_id', $sitename)
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $sitename = getSiteDetailsById($sitename)->name;
                $pdf = Pdf::loadView('layouts.material.pdfs.accToSite', compact('material', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 15) {
            $supplier_id = $request->get('supplier_id');
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 3, "", $supplier_id, "");
            } else {
                $file_name = "Material Supplier Report (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->where('material_entry.supplier', $supplier_id)
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $partyname = optional(DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $supplier_id)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.material.pdfs.accToSupp', compact('material', 'start_date', 'end_date', 'partyname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 16) {
            $supplier_id = $request->get('supplier_id');
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 4, $sitename, $supplier_id, "");
            } else {
                $file_name = "Material Supplier Report At Particular Site (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->where('material_entry.site_id', $sitename)
                    ->where('material_entry.supplier', $supplier_id)
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $partyname = optional(DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $supplier_id)->first())->name ?? '';
                $sitename = getSiteDetailsById($sitename)->name;
                $pdf = Pdf::loadView('layouts.material.pdfs.accToSuppAtSite', compact('material', 'start_date', 'end_date', 'partyname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 17) {
            $material_id = $request->get('material_id');
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 5, "", "", $material_id);
            } else {
                $file_name = "Material Item Report (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->where('material_entry.material_id', $material_id)
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $headname = optional(DB::connection($user_db_conn_name)->table('materials')->where('id', $material_id)->first())->name ?? '';
                $pdf = Pdf::loadView('layouts.material.pdfs.accToMat', compact('material', 'start_date', 'end_date', 'headname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 18) {
            $material_id = $request->get('material_id');
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, $start_date, $end_date, 6, $sitename, "", $material_id);
            } else {
                $file_name = "Material Item Report At Particular Site (" . $start_date . " To " . $end_date . ").pdf";
                $material = DB::connection($user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->where('material_entry.site_id', $sitename)
                    ->where('material_entry.material_id', $material_id)
                    ->whereBetween('material_entry.date', [$start_date, $end_date])
                    ->orderBy('material_entry.date', 'desc')->get();
                $headname = optional(DB::connection($user_db_conn_name)->table('materials')->where('id', $material_id)->first())->name ?? '';
                $sitename = getSiteDetailsById($sitename)->name;
                $pdf = Pdf::loadView('layouts.material.pdfs.accToMatAtSite', compact('material', 'start_date', 'end_date', 'headname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 19) {
            $supplier_id = $request->get('supplier_id');
            if ($type == 1) {
                return $this->exportMaterialExcel($user_db_conn_name, "", "", 7, "", $supplier_id, "");
            } else {
                $party_name = optional(DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $supplier_id)->first())->name ?? '';
                $file_name = "Material Supplier Statement - " . $party_name . " .pdf";
                $statement = DB::connection($user_db_conn_name)
                    ->table('material_supplier_statement')
                    ->where('material_supplier_statement.supplier_id', $supplier_id)
                    ->orderBy('material_supplier_statement.id', 'asc')->get();
                $data = array();
                $total_credit = 0;
                $total_debit = 0;
                foreach ($statement as $statem) {
                    if ($statem->type == 'Credit') {
                        $pv = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->first();
                        if ($pv) {
                            $amount = $pv->amount;
                            $site = optional(getSiteDetailsById($pv->site_id))->name ?? '';
                            $user = optional(getUserDetailsById($pv->created_by))->name ?? '';
                            $total_credit += $amount;
                            $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $pv->remark, 'image' => $pv->image];
                            array_push($data, $dat);
                        }
                    } else {
                        $mat = DB::connection($user_db_conn_name)->table('material_entry')->join('materials', 'materials.id', '=', 'material_entry.material_id')->join('units', 'units.id', '=', 'material_entry.unit')->select('material_entry.*', 'units.name as unit_name', 'materials.name as mat_name')->where('material_entry.id', $statem->entry_id)->first();
                        if ($mat) {
                            $amount = $mat->amount;
                            $site = optional(getSiteDetailsById($mat->site_id))->name ?? '';
                            $user = optional(getUserDetailsById($mat->user_id))->name ?? '';
                            $total_debit += $amount;
                            $dat = ['date' => $mat->date, 'ref' => 'Material Entry', 'ref_no' => $mat->bill_no, 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $mat->mat_name . " - " . $mat->qty . " " . $mat->unit_name, 'image' => $mat->image];
                            array_push($data, $dat);
                        }
                    }
                }
                usort($data, function ($a, $b) {
                    $dateA = strtotime($a['date']);
                    $dateB = strtotime($b['date']);
                    return $dateA - $dateB;
                });
                $partybalance = getMaterialsSupplierBalance($supplier_id);
                $pdf = Pdf::loadView('layouts.material.pdfs.supplierStatement', compact('data', 'party_name', 'total_credit', 'total_debit', 'partybalance'));
                return $pdf->download($file_name);
            }
        }
    }
   
    
    // Sort the array using usort and the custom comparison function
   
    public function exportSiteBillExcel($user_db_conn_name, $start_date = null, $end_date = null, $report_code, $headname = null, $sitename = null, $partyname = null)
    {
        $file_name = "Bill ";
        if ($report_code == 1) {
            $file_name .= "Date Report";
        } else if ($report_code == 2) {
            $file_name .= "Detailed Date Report ";
        } else if ($report_code == 3) {
            $file_name .= "Item Report ";
        } else if ($report_code == 4) {
            $file_name .= "Item Report At Particular Site ";
        } else if ($report_code == 5) {
            $file_name .= "Party Report ";
        } else if ($report_code == 6) {
            $file_name .= "Party Detailed Report ";
        } else if ($report_code == 7) {
            $file_name .= "Party Report At Particular Site ";
        } else if ($report_code == 8) {
            $file_name .= "Party Detailed Report At Particular Site ";
        } else if ($report_code == 9) {
            $file_name .= "Site Report ";
        } else if ($report_code == 10) {
            $file_name .= "Site Detailed Report ";
        } else if ($report_code == 12) {
            $file_name .= "Site Detailed With Work Report ";
        }

        if ($report_code == 11) {
            $party_name = optional(DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->first())->name ?? '';
            $file_name = "Bill Party Statement-" . $party_name . ".xlsx";
        } else {
            $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";
        }

        return Excel::download(new SiteBillExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, $partyname, $headname), $file_name);
    }

    public function exportMaterialExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename = null, $partyname = null, $headname = null)
    {
        $file_name = "Material ";
        if ($report_code == 1) {
            $file_name .= "Date Report";
        } else if ($report_code == 2) {
            $file_name .= "Site Report ";
        } else if ($report_code == 3) {
            $file_name .= "Supplier Report ";
        } else if ($report_code == 4) {
            $file_name .= "Supplier Report At Particular Site ";
        } else if ($report_code == 5) {
            $file_name .= "Item Report ";
        } else if ($report_code == 6) {
            $file_name .= "Item Report At Particular Site ";
        }

        if ($report_code == 7) {
            $party_name = optional(DB::connection($user_db_conn_name)->table('material_supplier')->where('id', $partyname)->first())->name ?? '';
            $file_name = "Material Supplier Statement-" . $party_name . ".xlsx";
        } else {
            $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";
        }

        return Excel::download(new MaterialExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, $partyname, $headname), $file_name);
    }
}
