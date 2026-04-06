<?php

namespace App\Http\Controllers\users;

use App\Exports\PaymentExport;
use App\Http\Controllers\Controller;
use Facade\FlareClient\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use DateTime;


class SiteController extends Controller
{
    //
    public function site(Request $request)
    {

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('sites')->get();
        return  view('layouts.users.sites')->with('data', json_encode($data));
    }

    public function addsites(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $open_balance = $request->input('open_balance');
        $sitestype = $request->input('sitestype');
        $project_id = $request->input('project_id');
        $data = ['name' => $name, 'address' => $address, 'sites_type' => $sitestype, 'project_id' => $project_id];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {

            // DB::connection($user_db_conn_name)->table('sites')->insert($data);

            $id = DB::connection($user_db_conn_name)->table('sites')->insertGetId($data);
            addActivity($id, 'sites', "Site Created", 1);

            $data2 = [
                'site_id' => $id,
                'amount' => $open_balance,
                'remark' => "Opening Balance"
            ];
            $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data2);
            addActivity($pay_id, 'site_payments', "Opening Balance Transfer To Site. Amount - " . $open_balance, 1);
            $tdata = [
                'site_id' => $id,
                'type' => 'Credit',
                'payment_id' => $pay_id
            ];
            DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

            DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
            return redirect('/sites')
                ->with('success', 'Site Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/sites')
                    ->with('error', 'Site Already Exists!');
            } else {
                return redirect('/sites')
                    ->with('error', 'Error While Creating Site!');
            }
        }
    }
    public function edit_site(Request $request)
    {
        $data = array();
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data']  = DB::connection($user_db_conn_name)->table('sites')->get();

        $data['edit_data'] = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->get();
        return  view('layouts.users.sites')->with('data', json_encode($data));
    }
    // public function addsitesBalance(Request $request)
    // {
    //     $id = $request->input('site_id');
    //     $amount = $request->input('amount');
    //     $remark = $request->input('remark');
    //     $date = $request->input('date');
    //     $data = [
    //         'site_id' => $id,
    //         'amount' => $amount,
    //         'remark' => $remark,
    //         'date' => $date
    //     ];

    //     $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    //     try {
    //         $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);

    //         $tdata = [
    //             'site_id' => $id,
    //             'type' => 'Credit',
    //             'payment_id' => $pay_id
    //         ];
    //         DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);

    //         return redirect('/sites')
    //             ->with('success', 'Site Balance Credit successfully!');
    //     } catch (\Exception $e) {
    //         return redirect('/sites')
    //             ->with('error', 'Error While Site Balance Credit!');
    //     }
    // }
    public function updatesites(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $address = $request->input('address');
        $sitestype = $request->input('sitestype');
        $project_id = $request->input('project_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        addActivity($id, 'sites', "Site Data Updated", 1);

        DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->update(['name' => $name, 'address' => $address, 'sites_type' => $sitestype, 'project_id' => $project_id]);
        return redirect('/sites')->with('success', 'Site Updated successfully!');;
    }
    public function delete_sites(Request $request)
    {
        $id = $request->input('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $sitename = DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->get()[0]->name;
        DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->delete();
        addActivity(0, 'sites', "Site Deleted - " . $sitename, 1);

        return redirect('/sites')
            ->with('success', 'Site Deleted Successfully!');
    }
    public function update_site_status(Request $request)
    {

        $id = $request->get('id');
        $status = $request->get('status');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->update(['status' => $status]);
        addActivity($id, 'sites', "Site Status Updated - " . $status, 1);

        return redirect('/sites')->with('success', 'Site Status Updated successfully!');
    }

    public function bulk_action(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $action = $request->input('bulk_action_type');
        $check_list = $request->input('check_list');

        if (empty($check_list) || !is_array($check_list)) {
            return redirect('/sites')->with('error', 'Select at least one site!');
        }

        if ($action == 'status_Active' || $action == 'status_Deactive') {
            $status = ($action == 'status_Active') ? 'Active' : 'Deactive';
            DB::connection($user_db_conn_name)->table('sites')->whereIn('id', $check_list)->update(['status' => $status]);
            addActivity(0, 'sites', "Bulk Update Status to $status", 1);
            return redirect('/sites')->with('success', 'Sites Status Updated successfully!');
        } elseif ($action == 'delete') {
            foreach ($check_list as $id) {
                if (!isSiteDeletable($id)) continue;
                $site = DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->first();
                if ($site) {
                    addActivity(0, 'sites', "Site Deleted - " . $site->name, 1);
                }
                DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->delete();
            }
            return redirect('/sites')->with('success', 'Deletable Sites Removed Successfully!');
        }

        return redirect('/sites')->with('error', 'Invalid Action!');
    }

    public function siteToSiteBalanceTransfer(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $from_site = $request->from_site_id;
        $to_site = $request->to_site_id;
        $amount = $request->amount;
        $date = $request->date;
        $remark = "Balance Transfer - " . $request->remark;

        $data = [
            'site_id' => $from_site,
            'amount' => $amount,
            'remark' => $remark,
            'date' => $date
        ];
        $data2 = [
            'site_id' => $to_site,
            'amount' => $amount,
            'remark' => $remark,
            'date' => $date
        ];
        $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);
        $pay_id2 =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data2);
        addActivity($pay_id, 'sites', "Site To Site Balance Transfer. Amount - " . $amount, 1);
        addActivity($pay_id2, 'sites', "Site To Site Balance Transfer. Amount - " . $amount, 1);



        $tdata = [
            'site_id' => $from_site,
            'type' => 'Debit',
            'payment_id' => $pay_id
        ];
        $tdata2 = [
            'site_id' => $to_site,
            'type' => 'Credit',
            'payment_id' => $pay_id2
        ];
        DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

        DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id2)->delete();

        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata2);
        return redirect('/sites')
            ->with('success', 'Balance Transfered Successfully!');
    }



public function view_site_payments(Request $request){
    $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    $id=$request->id;
    $site_name = DB::connection($user_db_conn_name)->table('sites')->where('id',$id)->first()->name;
    $data = DB::connection($user_db_conn_name)->table('site_payments')->where('site_id',$id)->get();
    return  view('layouts.users.site_payments',compact(['site_name','data']));
}



    public function siteStatement(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $sitename = $request->get('site_id');
        $type = $request->get('type');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');

        addActivity(0, 'sites', "Site Statement Report Generted", 1);
        $site_name = DB::connection($user_db_conn_name)->table('sites')->where('id', $sitename)->get()[0]->name;

        if ($type == 1) {
            $file_name = "Site Statement - " . $site_name . ".xlsx";
            return Excel::download(new PaymentExport($user_db_conn_name, "", "", 5, $sitename,  "", ""), $file_name);
        } else {
            $file_name = "Site Statement - " . $site_name . ".pdf";
            $statement = DB::connection($user_db_conn_name)
                ->table('sites_transaction')
                ->where('sites_transaction.site_id', $sitename)
                ->orderBy('sites_transaction.id', 'asc')->get();
            $data = array();
            $total_credit = 0;
            $total_debit = 0;
            foreach ($statement as $statem) {
                if ($statem->type == 'Credit') {
                    if (!is_null($statem->payment_id)) {
                        $payment = DB::connection($user_db_conn_name)->table('site_payments')->where('id', $statem->payment_id)->get()[0];
                        $amount = $payment->amount;
                        $total_credit += $amount;
                        $dat = ['date' => $payment->date, 'ref' => 'Payment Credit', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => $amount, 'debit' => '', 'particular' => $payment->remark, 'image' => ''];
                        array_push($data, $dat);
                    } else if (!is_null($statem->payment_voucher_id)) {
                        $pv = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->get()[0];
                        $amount = $pv->amount;
                        $site = getSiteDetailsById($pv->site_id)->name;
                        $user = getUserDetailsById($pv->created_by)->name;
                        $total_credit += $amount;
                        $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $pv->remark, 'image' => $pv->image];
                        array_push($data, $dat);
                    }
                } else {
                    if (!is_null($statem->expense_id)) {
                        $expense = DB::connection($user_db_conn_name)->table('expenses')->where('id', $statem->expense_id)->get()[0];
                        $amount = $expense->amount;
                        $site = getSiteDetailsById($expense->site_id)->name;
                        $user = getUserDetailsById($expense->user_id)->name;
                        $total_debit += $amount;
                        $dat = ['date' => $expense->date, 'ref' => 'Expense', 'ref_no' => '', 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $expense->particular, 'image' => $expense->image];
                        array_push($data, $dat);
                    } else  if (!is_null($statem->payment_id)) {
                        $payment = DB::connection($user_db_conn_name)->table('site_payments')->where('id', $statem->payment_id)->get()[0];
                        $amount = $payment->amount;
                        $total_debit += $amount;
                        $dat = ['date' => $payment->date, 'ref' => 'Payment Debit', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => '', 'debit' => $amount, 'particular' => $payment->remark, 'image' => ''];
                        array_push($data, $dat);
                    }
                }
            }
            usort($data, function ($a, $b) {
                $dateA = strtotime($a['date']);
                $dateB = strtotime($b['date']);
                return $dateA - $dateB;
            });
            

            $start = new DateTime($start_date);
            $end = new DateTime($end_date);
            $end->modify('+1 day'); // To include the end date
            $filteredData = array_filter($data, function($transaction) use ($start, $end) {
                $transactionDate = new DateTime($transaction['date']);
                return $transactionDate >= $start && $transactionDate < $end;
            });
            $openingBalance = array_reduce($data, function($carry, $transaction) use ($start) {
                if (new DateTime($transaction['date']) < $start) {
                    $carry += floatval($transaction['credit']) - floatval($transaction['debit']);
                }
                return $carry;
            }, 0);                       
            $sitebalance = getSiteBalance($sitename);
            $pdf = Pdf::loadView('layouts.users.pdfs.siteStatement', compact('filteredData', 'site_name', 'openingBalance', 'sitebalance','start_date','end_date'));
            return $pdf->download($file_name);
        }
    }
}
