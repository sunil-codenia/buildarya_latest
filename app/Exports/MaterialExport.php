<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;

class MaterialExport implements FromView
{

    use Exportable;

    protected $user_db_conn_name;
    protected $start_date;
    protected $end_date;
    protected $report_code;
    protected $sitename;
    protected $partyname;
    protected $headname;

    public function __construct($user_db_conn_name, $start_date = null, $end_date = null, $report_code = null, $sitename = null, $partyname = null, $headname = null)

    {
        $this->user_db_conn_name = $user_db_conn_name;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->partyname = $partyname;
        $this->report_code = $report_code;
        $this->sitename = $sitename;
        $this->headname = $headname;
    }

    public function view(): View
    {
        if ($this->report_code == 1) {

            return view('layouts.material.exports.accToDate', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->orderBy('material_entry.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        } elseif ($this->report_code == 2) {
            return view('layouts.material.exports.accToSite', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->where('material_entry.site_id', '=', $this->sitename)
                    ->orderBy('material_entry.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'sitename' => getSiteDetailsById($this->sitename)->name,
                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        } elseif ($this->report_code == 3) {

            return view('layouts.material.exports.accToSupp', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')

                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->where('material_entry.supplier', '=', $this->partyname)
                    ->orderBy('material_entry.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'partyname' => DB::connection($this->user_db_conn_name)->table('material_supplier')->where('id',$this->partyname)->get()[0]->name,               

                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        } elseif ($this->report_code == 4) {

            return view('layouts.material.exports.accToSuppAtSite', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->orderBy('material_entry.date', 'desc')
                    ->where('material_entry.supplier', '=', $this->partyname)->where('material_entry.site_id', '=', $this->sitename)
                    ->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
              'sitename' => getSiteDetailsById($this->sitename)->name,
              'partyname' => DB::connection($this->user_db_conn_name)->table('material_supplier')->where('id',$this->partyname)->get()[0]->name,  
                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        } elseif ($this->report_code == 5) {
            return view('layouts.material.exports.accToMat', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->where('material_entry.material_id', '=', $this->headname,)
                    ->orderBy('material_entry.date', 'desc')->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'headname' => DB::connection($this->user_db_conn_name)->table('materials')->where('id',$this->headname)->get()[0]->name,  
                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        } else  if ($this->report_code == 6) {

            return view('layouts.material.exports.accToMatAtSite', [
                'material' => DB::connection($this->user_db_conn_name)
                    ->table('material_entry')
                    ->leftjoin('materials', 'materials.id', '=', 'material_entry.material_id')
                    ->leftjoin('material_supplier', 'material_supplier.id', '=', 'material_entry.supplier')
                    ->leftjoin('sites', 'sites.id', '=', 'material_entry.site_id')
                    ->leftjoin('units', 'units.id', '=', 'material_entry.unit')
                    ->leftjoin('users', 'users.id', '=', 'material_entry.user_id')
                    ->select('material_entry.*', 'materials.name as material', 'materials.is_royalty', 'units.name as unit', 'sites.name as site', 'users.name as user', 'material_supplier.name as supplier')
                    ->whereBetween('material_entry.date', [$this->start_date, $this->end_date])
                    ->orderBy('material_entry.date', 'desc')
                    ->where('material_entry.material_id', '=', $this->headname)
                    ->where('material_entry.site_id', '=', $this->sitename)->get(),
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'headname' => DB::connection($this->user_db_conn_name)->table('materials')->where('id',$this->headname)->get()[0]->name,  
               'sitename' => getSiteDetailsById($this->sitename)->name,
                'color'=>session()->get('primary_color')[0],
                'sec_color'=>session()->get('secondry_color')[0],
            ]);
        }
        else  if ($this->report_code == 7) {

            $statement = DB::connection($this->user_db_conn_name)
            ->table('material_supplier_statement')
            ->where('material_supplier_statement.supplier_id', $this->partyname)
            ->orderBy('material_supplier_statement.id', 'asc')->get();
            $data = array();
            $total_credit = 0;
            $total_debit = 0;
            foreach ($statement as $statem) {
                if ($statem->type == 'Credit') {
                      
                    $pv = DB::connection($this->user_db_conn_name)->table('payment_vouchers')->where('id', $statem->payment_voucher_id)->get()[0];
                    $amount = $pv->amount;
                    $site = getSiteDetailsById($pv->site_id)->name;
                    $user = getUserDetailsById($pv->created_by)->name;
                    $total_credit += $amount;
                    $dat = ['date' => $pv->date, 'ref' => 'Payment Vouchers', 'ref_no' => $pv->voucher_no, 'user_name' => $user, 'site_name' => $site, 'credit' => $amount, 'debit' => '', 'particular' => $pv->remark, 'image' => $pv->image];
                    array_push($data, $dat);
                
            } else {
                    $mat = DB::connection($this->user_db_conn_name)->table('material_entry')->join('materials','materials.id','=','material_entry.material_id')->join('units','units.id','=','material_entry.unit')->select('material_entry.*','units.name as unit_name','materials.name as mat_name')->where('material_entry.id', $statem->entry_id)->get()[0];
                    $amount = $mat->amount;
                    $site = getSiteDetailsById($mat->site_id)->name;
                    $user = getUserDetailsById($mat->user_id)->name;
                    $total_debit += $amount;
                    $dat = ['date' => $mat->date, 'ref' => 'Material Entry', 'ref_no' => $mat->bill_no, 'user_name' => $user, 'site_name' => $site, 'credit' => '', 'debit' => $amount, 'particular' => $mat->mat_name. " - ".$mat->qty." ".$mat->unit_name, 'image' => $mat->image];
                    array_push($data, $dat);
                
            }
            }
            usort($data, function($a, $b) {
                $dateA = strtotime($a['date']);
                $dateB = strtotime($b['date']);
                return $dateA - $dateB;
            });

            $partybalance = getMaterialsSupplierBalance($this->partyname);
            $party_name = DB::connection($this->user_db_conn_name)->table('material_supplier')->where('id', $this->partyname)->get()[0]->name;


            return view('layouts.material.exports.supplierStatement', [
                'data'=>$data,                
                'party_name' => $party_name,                  
                'partybalance' => $partybalance,
                'total_debit' => $total_debit,
                'total_credit' => $total_credit,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        }

        return view('layouts.material.exports.accToDate', [
            'material' => collect(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'color' => session()->get('primary_color')[0],
            'sec_color' => session()->get('secondry_color')[0],
        ]);
    }
}
