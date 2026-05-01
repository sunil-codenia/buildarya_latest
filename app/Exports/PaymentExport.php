<?php

namespace App\Exports;

use App\Invoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Models\expense\ExpenseModel;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;

class PaymentExport implements Fromview
{

    use Exportable;
    protected $user_db_conn_name;
    protected $start_date;
    protected $end_date;
    protected $report_code;
    protected $sitename;
    protected $partyname;
    protected $partytype;


    public function __construct($user_db_conn_name, $start_date = null, $end_date = null, $report_code = null, $sitename = null, $partyname = null, $partytype = null)

    {
        $this->user_db_conn_name = $user_db_conn_name;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->partyname = $partyname;
        $this->report_code = $report_code;
        $this->sitename = $sitename;
        $this->partytype = $partytype;
    }

    public function view(): View
    {

        if ($this->report_code == 1) {


            return view('layouts.paymentvoucher.exports.accToDate', [
                'data' =>  DB::connection($this->user_db_conn_name)->table('payment_vouchers as pv')
                    ->leftjoin('sales_company as sc', 'sc.id', '=', 'pv.company_id')
                    ->leftjoin('sites as vs', 'vs.id', '=', 'pv.site_id')
                    ->leftjoin('users as cu', 'cu.id', '=', 'pv.created_by')
                    ->leftjoin('users as au', 'au.id', '=', 'pv.approved_by')
                    ->leftjoin('users as pu', 'pu.id', '=', 'pv.paid_by')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('pv.party_id', '=', 'bills_party.id')
                            ->where('pv.party_type', '=', 'bill');
                    })
                    ->leftJoin('material_supplier', function ($join) {
                        $join->on('pv.party_id', '=', 'material_supplier.id')
                            ->where('pv.party_type', '=', 'material');
                    })
                    ->leftJoin('sites as ps', function ($join) {
                        $join->on('pv.party_id', '=', 'ps.id')
                            ->where('pv.party_type', '=', 'site');
                    })
                    ->leftJoin('other_parties', function ($join) {
                        $join->on('pv.party_id', '=', 'other_parties.id')
                            ->where('pv.party_type', '=', 'other');
                    })

                    ->selectraw('pv.*, sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->whereBetween('pv.date', [$this->start_date, $this->end_date])
                    ->orderBy('pv.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'color' => session()->get('primary_color')[0] ?? '#000000',
                'sec_color' => session()->get('secondry_color')[0] ?? '#ffffff',
            ]);
        } else  if ($this->report_code == 2) {
            return view('layouts.paymentvoucher.exports.accToParty', [
                'data' =>  DB::connection($this->user_db_conn_name)->table('payment_vouchers as pv')
                    ->leftjoin('sales_company as sc', 'sc.id', '=', 'pv.company_id')
                    ->leftjoin('sites as vs', 'vs.id', '=', 'pv.site_id')
                    ->leftjoin('users as cu', 'cu.id', '=', 'pv.created_by')
                    ->leftjoin('users as au', 'au.id', '=', 'pv.approved_by')
                    ->leftjoin('users as pu', 'pu.id', '=', 'pv.paid_by')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('pv.party_id', '=', 'bills_party.id')
                            ->where('pv.party_type', '=', 'bill');
                    })
                    ->leftJoin('material_supplier', function ($join) {
                        $join->on('pv.party_id', '=', 'material_supplier.id')
                            ->where('pv.party_type', '=', 'material');
                    })
                    ->leftJoin('sites as ps', function ($join) {
                        $join->on('pv.party_id', '=', 'ps.id')
                            ->where('pv.party_type', '=', 'site');
                    })
                    ->leftJoin('other_parties', function ($join) {
                        $join->on('pv.party_id', '=', 'other_parties.id')
                            ->where('pv.party_type', '=', 'other');
                    })

                    ->selectraw('pv.*, sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.party_id', $this->partyname)
                    ->where('pv.party_type', $this->partytype)
                    ->whereBetween('pv.date', [$this->start_date, $this->end_date])
                    ->orderBy('pv.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'color' => session()->get('primary_color')[0] ?? '#000000',
                'sec_color' => session()->get('secondry_color')[0] ?? '#ffffff',
                'partyname' => getPaymentVoucherPartyInfo($this->partyname, $this->partytype)
            ]);
        }else  if ($this->report_code == 3) {
            return view('layouts.paymentvoucher.exports.accToSite', [
                'data' =>  DB::connection($this->user_db_conn_name)->table('payment_vouchers as pv')
                    ->leftjoin('sales_company as sc', 'sc.id', '=', 'pv.company_id')
                    ->leftjoin('sites as vs', 'vs.id', '=', 'pv.site_id')
                    ->leftjoin('users as cu', 'cu.id', '=', 'pv.created_by')
                    ->leftjoin('users as au', 'au.id', '=', 'pv.approved_by')
                    ->leftjoin('users as pu', 'pu.id', '=', 'pv.paid_by')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('pv.party_id', '=', 'bills_party.id')
                            ->where('pv.party_type', '=', 'bill');
                    })
                    ->leftJoin('material_supplier', function ($join) {
                        $join->on('pv.party_id', '=', 'material_supplier.id')
                            ->where('pv.party_type', '=', 'material');
                    })
                    ->leftJoin('sites as ps', function ($join) {
                        $join->on('pv.party_id', '=', 'ps.id')
                            ->where('pv.party_type', '=', 'site');
                    })
                    ->leftJoin('other_parties', function ($join) {
                        $join->on('pv.party_id', '=', 'other_parties.id')
                            ->where('pv.party_type', '=', 'other');
                    })

                    ->selectraw('pv.*, sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.site_id', $this->sitename)                
                    ->whereBetween('pv.date', [$this->start_date, $this->end_date])
                    ->orderBy('pv.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'color' => session()->get('primary_color')[0] ?? '#000000',
                'sec_color' => session()->get('secondry_color')[0] ?? '#ffffff',
                'sitename' => getSiteDetailsById($this->sitename)->name
            ]);
        }else  if ($this->report_code == 4) {
            return view('layouts.paymentvoucher.exports.accToPartyAtSite', [
                'data' =>  DB::connection($this->user_db_conn_name)->table('payment_vouchers as pv')
                    ->leftjoin('sales_company as sc', 'sc.id', '=', 'pv.company_id')
                    ->leftjoin('sites as vs', 'vs.id', '=', 'pv.site_id')
                    ->leftjoin('users as cu', 'cu.id', '=', 'pv.created_by')
                    ->leftjoin('users as au', 'au.id', '=', 'pv.approved_by')
                    ->leftjoin('users as pu', 'pu.id', '=', 'pv.paid_by')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('pv.party_id', '=', 'bills_party.id')
                            ->where('pv.party_type', '=', 'bill');
                    })
                    ->leftJoin('material_supplier', function ($join) {
                        $join->on('pv.party_id', '=', 'material_supplier.id')
                            ->where('pv.party_type', '=', 'material');
                    })
                    ->leftJoin('sites as ps', function ($join) {
                        $join->on('pv.party_id', '=', 'ps.id')
                            ->where('pv.party_type', '=', 'site');
                    })
                    ->leftJoin('other_parties', function ($join) {
                        $join->on('pv.party_id', '=', 'other_parties.id')
                            ->where('pv.party_type', '=', 'other');
                    })

                    ->selectraw('pv.*, sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.site_id', $this->sitename)     
                    ->where('pv.party_id', $this->partyname)
                    ->where('pv.party_type', $this->partytype)
                    ->whereBetween('pv.date', [$this->start_date, $this->end_date])
                    ->orderBy('pv.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'color' => session()->get('primary_color')[0] ?? '#000000',
                'sec_color' => session()->get('secondry_color')[0] ?? '#ffffff',
                'partyname' => getPaymentVoucherPartyInfo($this->partyname, $this->partytype),
                'sitename' => getSiteDetailsById($this->sitename)->name

            ]);
        }else  if ($this->report_code == 5) {
            $statement = DB::connection($this->user_db_conn_name)
            ->table('sites_transaction')
            ->where('sites_transaction.site_id', $this->sitename)                  
            ->orderBy('sites_transaction.id', 'asc')->get();
        $data = array();
        $total_credit = 0;
        $total_debit = 0;
        foreach ($statement as $statem) {
            if ($statem->type == 'Credit') {
               if (!is_null($statem->payment_id)) {
                    $payment = DB::connection($this->user_db_conn_name)->table('site_payments')->where('id', $statem->payment_id)->get()[0];
                    $amount = $payment->amount;
                    $total_credit += $amount;
                    $dat = ['date' => $payment->date, 'ref' => 'Payment Credit', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => $amount, 'debit' => '', 'particular' => $payment->remark, 'image' => ''];
                    array_push($data,$dat);
                } else if (!is_null($statem->payment_voucher_id)) {
                    $pv = DB::connection($this->user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->get()[0];
                    $amount = $pv->amount;
                    $site = getSiteDetailsById($pv->site_id)->name;
                    $user = getUserDetailsById($pv->created_by)->name;
                    $total_credit += $amount;
                    $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $pv->remark, 'image' => $pv->image];
                    array_push($data,$dat);
                }
            } else {
                if (!is_null($statem->expense_id)) {
                    $expense = DB::connection($this->user_db_conn_name)->table('expenses')->where('id', $statem->expense_id)->get()[0];
                    $amount = $expense->amount;
                    $site = getSiteDetailsById($expense->site_id)->name;
                    $user = getUserDetailsById($expense->user_id)->name;
                    $total_debit += $amount;
                    $dat = ['date' => $expense->date, 'ref' => 'Expense', 'ref_no' => '', 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $expense->particular, 'image' => $expense->image];
                    array_push($data,$dat);
                } else  if (!is_null($statem->payment_id)) {
                    $payment = DB::connection($this->user_db_conn_name)->table('site_payments')->where('id', $statem->payment_id)->get()[0];
                    $amount = $payment->amount;
                    $total_debit += $amount;
                    $dat = ['date' => $payment->date, 'ref' => 'Payment Debit', 'ref_no' => '', 'user_name' => '', 'site_name' => '', 'credit' => '', 'debit' => $amount, 'particular' => $payment->remark, 'image' => ''];
                    array_push($data,$dat);
                }
            }
        }
        usort($data, function($a, $b) {
            $dateA = strtotime($a['date']);
            $dateB = strtotime($b['date']);
            return $dateA - $dateB;
        });
        $site_name = DB::connection($this->user_db_conn_name)->table('sites')->where('id', $this->sitename)->get()[0]->name;
        $sitebalance = getSiteBalance($this->sitename);

            return view('layouts.users.exports.siteStatement', [
                'data' => $data,
                'color' => session()->get('primary_color')[0] ?? '#000000',
                'sec_color' => session()->get('secondry_color')[0] ?? '#ffffff',             
                'site_name' => $site_name,
                'total_credit'=>$total_credit,
                'total_debit'=>$total_debit,
                'sitebalance'=>$sitebalance

            ]);
        }
    }
}
