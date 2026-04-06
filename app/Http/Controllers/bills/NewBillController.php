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
        if ($visiblity_at_site == 'current') {
            $filters = [['new_bill_entry.status', '!=', 'Pending'], ['new_bill_entry.site_id', '=', $site_id]];
        } else {
            $filters = [['new_bill_entry.status', '!=', 'Pending']];
        }
        $data = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')->select('new_bill_entry.*', 'sites.name as site', 'users.name as user', 'bills_party.name as party')->where($filters)->whereBetween('new_bill_entry.create_datetime', [$min_date, $max_date])->orderBy('new_bill_entry.create_datetime', 'desc')->get();

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
        if ($visiblity_at_site == 'current') {
            $filters = [['new_bill_entry.status', '=', 'Pending'], ['new_bill_entry.site_id', '=', $site_id]];
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['new_bill_entry.status', '=', 'Pending'], ['new_bill_entry.site_id', '=', $req_site_id]];
            } else {
                $filters = [['new_bill_entry.status', '=', 'Pending']];
            }
        }

        $data = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftjoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->leftjoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->leftjoin('users', 'users.id', '=', 'new_bill_entry.user_id')->select('new_bill_entry.*', 'sites.name as site', 'users.name as user', 'bills_party.name as party')->where($filters)->whereBetween('new_bill_entry.billdate', [$min_date, $max_date])->orderBy('new_bill_entry.create_datetime', 'desc')->get();

        return  view('layouts.bills.pending')->with('data', json_encode($data));
    }
    public function new_bill(Request $request)
    {

        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        return  view('layouts.bills.newbill')->with('data', json_encode($data));
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
        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill_party_id'])->get()[0];
        if ($party_status->status == 'Active') {
            if (isset($data['item'])) {
                $length = count($data['item']);
                $amount = 0;
                if ($length > 0) {
                    for ($i = 0; $i < $length; $i++) {
                        $amount += ($data['rate'][$i] * $data['qty'][$i]);
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

                    $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->select('bills_party.status as status')->where('new_bill_entry.id', '=', $id)->get()[0];

                    if ($bill->status == 'Active') {
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
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('bills_party', 'bills_party.id', '=', 'new_bill_entry.party_id')->select('bills_party.status as status')->where('new_bill_entry.id', '=', $id)->get()[0];

        if ($bill->status == 'Active') {
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
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->get()[0];
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
        $bill = DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->get()[0];
        DB::connection($conn)->table('new_bill_entry')->where('id', '=', $id)->update(['status' => 'Rejected']);
        sendAlertNotification($bill->user_id,'Your bill of amount '.$bill->amount.' with bill no. '. $bill->bill_no .' has been rejected. Check Application For More Information.','Bill Rejected');
        DB::connection($conn)->table('bill_party_statement')->where('bill_no', '=', $id)->delete();
        addActivity($id, 'new_bill_entry', "Bill Status Rejected", 4);
    }
    public function edit_bill(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $data['bill'] = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('id', '=', $id)->get()[0];
        $data['bill_items'] = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();

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

        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill_party_id'])->get()[0];
        if ($party_status->status == 'Active') {
            $length = count($data['item']);
            $amount = 0;
            if ($length > 0) {
                for ($i = 0; $i < $length; $i++) {
                    $amount += ($data['rate'][$i] * $data['qty'][$i]);
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
        $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->where('new_bill_entry.id', '=', $id)->select('new_bill_entry.*', 'users.name as user', 'sites.name as site')->get()[0];
        $bill_items = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $bill_party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $bill->party_id)->get()[0];
        $balance = getBillPartyBalance($bill->party_id,$user_db_conn_name);
        $file_name = $bill->bill_no.".pdf";
        $pdf = Pdf::loadView('layouts.bills.pdfs.bill_pdf',compact(['bill','bill_items','bill_party','balance']));
        return $pdf->download($file_name);
       
    }
    public function view_bill(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $data['bill'] = DB::connection($user_db_conn_name)->table('new_bill_entry')->leftJoin('users', 'users.id', '=', 'new_bill_entry.user_id')->leftJoin('sites', 'sites.id', '=', 'new_bill_entry.site_id')->where('new_bill_entry.id', '=', $id)->select('new_bill_entry.*', 'users.name as user', 'sites.name as site')->get()[0];
        $data['bill_items'] = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->leftJoin('bills_work', 'bills_work.id', '=', 'new_bills_item_entry.work_id')->where('new_bills_item_entry.bill_id', '=', $id)->get();
        $data['bill_party'] = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $data['bill']->party_id)->get()[0];
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



    public function bill_report(Request $request)
    {
        return view('layouts.bills.bills_report');
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
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code);
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
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code);
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
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $headname, "", "");
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
                $headname = DB::connection($user_db_conn_name)->table('bills_work')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToItem', compact('bills', 'start_date', 'end_date', 'headname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 4) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $headname, $sitename, "");
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
                $headname = DB::connection($user_db_conn_name)->table('bills_work')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToItemAtSite', compact('bills', 'start_date', 'end_date', 'headname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 5) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "", $partyname);
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
                $partyname = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToParty', compact('bills', 'start_date', 'end_date', 'partyname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 6) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "", $partyname);
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
                $partyname = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyDetailed', compact('bills', 'start_date', 'end_date', 'partyname'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 7) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, $partyname);
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
                $partyname = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyAtSite', compact('bills', 'start_date', 'end_date', 'partyname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 8) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, $partyname);
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
                $partyname = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.bills.pdfs.accToPartyAtSiteDetailed', compact('bills', 'start_date', 'end_date', 'partyname', 'sitename'));
                return $pdf->download($file_name);
            }
        } else if ($report_code == 9) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
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
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
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
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $sitename, "");
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
                return $this->exportExcel($user_db_conn_name, "","", $report_code, "", "", $partyname);
            } else {
                $party_name = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;

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
                            $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', $statem->expense_id)->get()[0];
                            $amount = $expense->amount;
                            $site = getSiteDetailsById($expense->site_id)->name;
                            $user = getUserDetailsById($expense->user_id)->name;
                            $total_credit += $amount;
                            $dat = ['date' => $expense->date, 'ref' => 'Expense', 'ref_no' => '', 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $expense->image];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->get()[0];
                            $amount = $payment->amount;
                            $total_credit += $amount;
                            $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => ''];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_voucher_id)) {
                            $pv = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->get()[0];
                            $amount = $pv->amount;
                            $site = getSiteDetailsById($pv->site_id)->name;
                            $user = getUserDetailsById($pv->created_by)->name;
                            $total_credit += $amount;
                            $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $statem->particular, 'image' => $pv->image];
                            array_push($data,$dat);
                        }
                    } else {
                        if (!is_null($statem->bill_no)) {
                            $bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('id', $statem->bill_no)->get()[0];
                            $amount = $bill->amount;
                            $site = getSiteDetailsById($bill->site_id)->name;
                            $user = getUserDetailsById($bill->user_id)->name;
                            $total_debit += $amount;
                            $dat = ['date' => $bill->billdate, 'ref' => 'Site Bill', 'ref_no' => $bill->bill_no, 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular,'image'=>''];
                            array_push($data,$dat);
                        } else if (!is_null($statem->payment_id)) {
                            $payment = DB::connection($user_db_conn_name)->table('bill_party_payments')->where('id', $statem->payment_id)->get()[0];
                            $amount = $payment->amount;
                            $total_debit += $amount;
                            $dat = ['date' => $payment->date, 'ref' => 'Payment', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => '', 'debit' => $amount, 'particular' => $statem->particular, 'image' => ''];
                            array_push($data,$dat);
                        }
                    }
                }
                usort($data, function($a, $b) {
                    $dateA = strtotime($a['date']);
                    $dateB = strtotime($b['date']);
                    return $dateA - $dateB;
                });

                $partybalance = getBillPartyBalance($partyname);
                
                $pdf = Pdf::loadView('layouts.bills.pdfs.partyStatement', compact('data', 'party_name','total_credit','total_debit','partybalance'));
                return $pdf->download($file_name);
            }
        }
    }
   
    
    // Sort the array using usort and the custom comparison function
   
    public function exportExcel($user_db_conn_name, $start_date=null, $end_date=null, $report_code, $headname = null, $sitename = null, $partyname = null)
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
        $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";
        
        if ($report_code == 11) {
            $party_name = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $partyname)->get()[0]->name;

            $file_name = "Bill Party Statement - ".$party_name.".xlsx";
        }

        return Excel::download(new SiteBillExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename,  $partyname, $headname), $file_name);
    }
}
