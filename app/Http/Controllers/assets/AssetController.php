<?php

namespace App\Http\Controllers\assets;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;

// use Illuminate\Support\Facades\DB;
// use Illuminate\Http\Request;


use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SiteBillExport;
// use App\Exports\BillpartylazerExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Psy\Command\WhereamiCommand;
use Illuminate\Support\Facades\File;
use App\Exports\AssetsExport;
class AssetController extends Controller
{
    //   
    // public function asset(Request $request)
    // {
    //     $data = array();
    //     $id = $request->get('asset_id');
    //     $get_site_id = $request->get('site_id');

    //     $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    //     $role_id = $request->session()->get('role');
    //     $site_id = $request->session()->get('site_id');
    //     $role_details = getRoleDetailsById($role_id);
    //     $visiblity_at_site = $role_details->visiblity_at_site;

    //     if ($visiblity_at_site == 'current') {
    //         $filters = [['assets.head_id', '=', $id], ['assets.site_id', '=', $site_id]];
    //     } else {
    //         $filters = [['assets.head_id', '=', $id], ['assets.site_id', '=', $get_site_id]];
    //     }
    //     $data = DB::connection($user_db_conn_name)->table('assets')->leftjoin('sites', 'sites.id', '=', 'assets.site_id')->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')->select('assets.*', 'sites.name as site', 'asset_head.name as head')->where($filters)->get();
    //     return  view('layouts.asset.asset')->with('data', json_encode($data));
    // }

    public function asset(Request $request)
    {
        $data = array();
        $id = $request->get('asset_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;

        $query = DB::connection($user_db_conn_name)->table('assets');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'assets.site_id');
            $filters = [['assets.head_id', '=', $id]];
        } else {
            $filters = [['assets.head_id', '=', $id]];
        }
        $data = $query->leftjoin('sites', 'sites.id', '=', 'assets.site_id')->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')->select('assets.*', 'sites.name as site', 'asset_head.name as head')->where($filters)->get();
        return  view('layouts.asset.asset')->with('data', json_encode($data));
    }
   
    public function add_newassets(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $site_id = $request->input('site_id');
        $assetsname = $request->input('assetsname');
        $head_id = $request->input('head_id');
        $costprice = $request->input('costprice');
        try {

            $data = ['name' => $assetsname, 'head_id' => $head_id, 'cost_price' => $costprice, 'site_id' => $site_id];
            $id = DB::connection($user_db_conn_name)->table('assets')->insertGetId($data);
            addActivity($id,'assets',"New Asset Purchased",5);

            $asset_trans = [
                'asset_id' => $id,
                'to_site' => $site_id,
                'transaction_type' => 'Purchase',
                'remark' => 'Asset added manually',
            ];
            DB::connection($user_db_conn_name)->table('asset_transaction')->insert($asset_trans);
            return redirect('/asset_head')
                ->with('success', 'Asset Created successfully!');
        } catch (\Exception $e) {

            return redirect('/asset_head')
                ->with('error', 'Error While Creating Asset!');
        }
    }





    // public function asset_head(Request $request)
    // {
    //     $data = array();
    //     $user_db_conn_name = $request->session()->get('comp_db_conn_name');

    //     $role_id = session()->get('role');
    //     $site_id = session()->get('site_id');
    //     $role_details = getRoleDetailsById($role_id);
    //     $visiblity_at_site = $role_details->visiblity_at_site;
    
    //     if ($visiblity_at_site == 'current') {
    //         $data = DB::connection($user_db_conn_name)->table('assets')
    //         ->select('assets.site_id', 'assets.head_id', DB::raw('COUNT(assets.id) as count'))
    //         ->where('assets.status','=','Working')->where('assets.site_id','=',$site_id)
    //         ->groupBy('assets.head_id', 'assets.site_id')
    //         ->get();
    //     } else {
    //         $data = DB::connection($user_db_conn_name)->table('assets')
    //         ->select('assets.site_id', 'assets.head_id', DB::raw('COUNT(assets.id) as count'))
    //         ->where('assets.status','=','Working')
    //         ->groupBy('assets.head_id', 'assets.site_id')
    //         ->get();
    //     }
    //       return  view('layouts.asset.head')->with('data', json_encode($data));
    // }
    public function asset_head(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $data = DB::connection($user_db_conn_name)->table('asset_head')->get();

        return  view('layouts.asset.head')->with('data', json_encode($data))->with('showing_data','all');
    }
    public function search_asset_head_sites(Request $request){
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $site_id = $request->input('display_site');
        $data = DB::connection($user_db_conn_name)->table('assets')->leftjoin('sites', 'sites.id', '=', 'assets.site_id')->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')->select('assets.*', 'sites.name as site', 'asset_head.name as head')->where('assets.site_id','=',$site_id)->get();

        return  view('layouts.asset.head')->with('data', json_encode($data))->with('showing_data',$site_id);
    }
    public function addassethead(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
           $id = DB::connection($user_db_conn_name)->table('asset_head')->insertGetId($data);
            addActivity($id,'asset_head',"New Asset Head Created",5);
            return redirect('/asset_head')
                ->with('success', 'Asset Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/asset_head')
                    ->with('error', 'Asset Head Already Exists!');
            } else {
                return redirect('/asset_head')
                    ->with('error', 'Error While Creating Asset Head!');
            }
        }
    }
    public function updateassethead(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('asset_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id,'asset_head',"Asset Head Updated",5);

            return redirect('/asset_head')
                ->with('success', 'Asset Head Updated Successfully!');;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/asset_head')
                    ->with('error', 'Asset Head Already Exists!');
            } else {
                return redirect('/asset_head')
                    ->with('error', 'Error While Updating Asset Head!');
            }
        }
    }

    public function edit_asset_head(Request $request)
    {
        $id = $request->get('id');
        // $data = array();
        // $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        // $role_id = session()->get('role');
        // $site_id = session()->get('site_id');
        // $role_details = getRoleDetailsById($role_id);
        // $visiblity_at_site = $role_details->visiblity_at_site;
    
        // if ($visiblity_at_site == 'current') {
            
        // $data['data'] = DB::connection($user_db_conn_name)->table('assets')
        //     ->select('assets.site_id', 'assets.head_id', DB::raw('COUNT(assets.id) as count'))
        //     ->where('assets.status','=','Working')->where('assets.site_id','=',$site_id)
        //     ->groupBy('assets.head_id', 'assets.site_id')
        //     ->get();
        // } else {
            
        // $data['data'] = DB::connection($user_db_conn_name)->table('assets')
        //     ->select('assets.site_id', 'assets.head_id', DB::raw('COUNT(assets.id) as count'))
        //     ->where('assets.status','=','Working')
        //     ->groupBy('assets.head_id', 'assets.site_id')
        //     ->get();
        // }


        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('asset_head')->get();
        
      
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('asset_head')->where('id', '=', $id)->get();
        return  view('layouts.asset.head')->with('data', json_encode($data));
    }
    public function delete_asset_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('assets')->where('head_id', '=', $id)->get();
        $delete_asset_head = DB::connection($user_db_conn_name)->table('asset_head')->where('id', '=', $id)->get()[0]->name;
        if (Count($check) > 0) {
            return redirect('/asset_head')
                ->with('error', 'Asset Head Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('asset_head')->where('id', '=', $id)->delete();
            addActivity(0,'asset_head',"Asset Head Deleted - ".$delete_asset_head,5);

            
            return redirect('/asset_head')
                ->with('success', 'Asset Head Deleted Successfully!');
        }
    }

    public function asset_expense_head(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('assets_expense_head')->leftjoin('expense_head', 'expense_head.id', '=', 'assets_expense_head.head_id')->select('assets_expense_head.*',  'expense_head.name as head')->get();
        $data['heads'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        return  view('layouts.asset.expenseHead')->with('data', json_encode($data));
    }
    public function addassetExpensehead(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $data = ['head_id' => $head_id];
        try {
            $id = DB::connection($user_db_conn_name)->table('assets_expense_head')->insertGetId($data);
            addActivity($id,'assets_expense_head',"Expense Head Allocate To Asset",5);

            return redirect('/asset_expense_head')
                ->with('success', 'Asset\'s Expense Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/asset_expense_head')
                    ->with('error', 'Asset\'s Expense Head Already Exists!');
            } else {
                return redirect('/asset_expense_head')
                    ->with('error', 'Error While Creating Asset\'s Expense Head!');
            }
        }
    }
    public function delete_assetExpense_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        DB::connection($user_db_conn_name)->table('assets_expense_head')->where('id', '=', $id)->delete();
        addActivity(0,'assets_expense_head',"Expense Head Dellocate From Asset",5);

        return redirect('/asset_expense_head')
            ->with('success', 'Asset\'s Expense Head Deleted Successfully!');
    }

    public function transferasset(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $from_site = $request->get('from_site');
        $to_site = $request->get('to_site');
        $remark = $request->get('remark');
        if ($from_site == $to_site) {
            return redirect('/asset?asset_id=' . $head_id)
                ->with('error', 'Asset Already On Same Site!');
        } else {

            try {
                DB::connection($user_db_conn_name)->table('assets')->where('id', $id)->update(['site_id' => $to_site]);
                addActivity($id,'assets',"Asset Transfered From - ". getSiteDetailsById($from_site)->name." To - ". getSiteDetailsById($to_site)->name ,5);
                $asset_trans = [
                    'asset_id' => $id,
                    'from_site' => $from_site,
                    'to_site' => $to_site,
                    'transaction_type' => 'Transfer',
                    'remark' => $remark
                ];
                DB::connection($user_db_conn_name)->table('asset_transaction')->insert($asset_trans);
                return redirect('/asset?asset_id=' . $head_id)
                    ->with('success', 'Asset\'s Transfered successfully!');
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    return redirect('/asset?asset_id=' . $head_id)
                        ->with('error', 'Asset\'s Transfer Failed!');
                }
            }
        }
    }
    public function soldasset(Request $request){
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $from_site = $request->get('from_site');
        $sold_value = $request->get('sold_value');
        $remark = $request->get('remark');
                $date = $request->get('date');
       

            try {
                DB::connection($user_db_conn_name)->table('assets')->where('id', $id)->update(['status' => 'Sold','sale_price'=> $sold_value]);
                addActivity($id,'assets',"Asset Sold For Amount - ".$sold_value." At Site - ".getSiteDetailsById($from_site)->name,5);
                $asset_trans = [
                    'asset_id' => $id,
                    'from_site' => $from_site,
                    'transaction_type' => 'Sold',
                    'remark' => $remark
                ];
                DB::connection($user_db_conn_name)->table('asset_transaction')->insert($asset_trans);
                $this->addsitesBalance($from_site,$sold_value, "Asset Sold - ".$remark,$date,$user_db_conn_name);
                 return redirect('/asset?asset_id=' . $head_id)
                    ->with('success', 'Asset\'s Sold successfully!');
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    return redirect('/asset?asset_id=' . $head_id)
                        ->with('error', 'Asset\'s Sold Failed!');
                }
         
        }
    }
    public function addsitesBalance($id,$amount, $remark,$date,$user_db_conn_name){
      
        $data=['site_id'=>$id,
        'amount'=>$amount,
        'remark'=>$remark,
        'date'=>$date
    ];
     $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);
     addActivity($pay_id,'site_payments',"Payment Created At Site By Selling Asset.",1);
         
            $tdata = [
                'site_id'=>$id,
                'type'=>'Credit',
                'payment_id'=>$pay_id
            ];
            DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

            DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
        
    }
    public function assetTransferHistory(Request $request){
        $id = $request->get('asset_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['asset'] =   DB::connection($user_db_conn_name)->table('assets')->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')->select('assets.*', 'asset_head.name as head')->where('assets.id', '=', $id)->get();
       
     
        $data['history'] = DB::connection($user_db_conn_name)->table('asset_transaction')->where('asset_id', '=', $id)->get();
        return  view('layouts.asset.assetTransferHistory')->with('data', json_encode($data));
    }
   
    public function searchasset(Request $request){
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('assets')
        ->join('asset_head', 'asset_head.id', '=', 'assets.head_id')
        ->join('sites', 'sites.id', '=', 'assets.site_id')
        ->select('sites.name as site_name', 'asset_head.name', DB::raw('COUNT(assets.id) as count'))
        ->where('assets.status', 'Working')
        ->groupBy('assets.head_id', 'assets.site_id')
        ->get();

        dd($data);

        return  view('layouts.asset.head')->with('data', json_encode($data));
    }

  
    public function assets_report(Request $request)
    
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $asset_heads =  DB::connection($user_db_conn_name)->table('asset_head')->get();
        $sites =  DB::connection($user_db_conn_name)->table('sites')->get();
        return view('layouts.asset.asset_report',compact(['asset_heads','sites']));


    }


public function asset_of_site_report(Request $request){
    $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    $type = $request->get('Report_Type');
    $sitename = $request->get('site_id');
    $site = DB::connection($user_db_conn_name)->table('sites')->where('id',$sitename)->first();
    addActivity(0, 'assets', "Asset Report Generated Of Site - ".$site->name, 5);

    if ($type == 1) {
        $file_name = "Assets Report Of Site - ".$site->name.".xlsx";

        return Excel::download(new AssetsExport($user_db_conn_name, null, null, 9, $sitename, null), $file_name);

    } else {
        $file_name = "Assets Report Of Site - ".$site->name.".pdf";
         $data =  DB::connection($user_db_conn_name)->table('assets')->leftjoin('sites', 'sites.id', '=', 'assets.site_id')->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')->select('assets.*', 'sites.name as site', 'asset_head.name as head')->where('assets.site_id','=',$sitename)->get();         ;
         $site_name = $site->name;
        $pdf = Pdf::loadView('layouts.asset.pdfs.siteAssetReport', compact('data', 'site_name'));

        return $pdf->download($file_name);
    }
}
    public function assetreport(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $report_code = $request->get('type');


        $start_date = $request->get('start_date');
        $sitename = $request->get('site_id');
        $headname = $request->get('head_id');
        $end_date = $request->get('end_date');
        addActivity(0, 'assets', "Asset Report Generated Of Data (" . $start_date . " - " . $end_date . ")", 5);
        if ($report_code == 1) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $headname);
            } else {
                $file_name = "Asset Purchase Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'assets.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'assets.expense_id')
                    ->leftjoin('sites as ps', 'ps.id', '=', 'expenses.site_id')
                    ->leftjoin('users as u', 'u.id', '=', 'expenses.user_id')

                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->selectRaw('assets.*, ws.name as working_site, ps.name as purchase_site,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->where('assets.head_id', $headname)
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                $headname = DB::connection($user_db_conn_name)->table('asset_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.asset.pdfs.purrAccToHead', compact('data', 'start_date', 'end_date', 'headname'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 2) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, "");
            } else {
                $file_name = "Asset Purchase Report By Site (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftJoin('asset_head', 'asset_head.id', 'assets.head_id')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'assets.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'assets.expense_id')
                    ->leftjoin('users as u', 'u.id', '=', 'expenses.user_id')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->selectRaw('assets.*, ws.name as working_site, asset_head.name as head_name,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->where('expenses.site_id', $sitename)
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                $sitename = DB::connection($user_db_conn_name)->table('sites')->where('id', $sitename)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.asset.pdfs.purrAccToSite', compact('data', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 3) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "");
            } else {
                $file_name = "Asset Purchase Complete Report By Date (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftJoin('asset_head', 'asset_head.id', 'assets.head_id')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'assets.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'assets.expense_id')
                    ->leftjoin('users as u', 'u.id', '=', 'expenses.user_id')
                    ->leftjoin('sites as ps', 'ps.id', '=', 'expenses.site_id')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->selectRaw('assets.*, ws.name as working_site, ps.name as purchase_site, asset_head.name as head_name,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.asset.pdfs.compPurr', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 4) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $headname);
            } else {
                $file_name = "Asset Sale Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftjoin('sites as ss', 'ss.id', '=', 'assets.site_id')
                    ->leftjoin('asset_transaction as at', 'at.asset_id', '=', 'assets.id')
                    
                    ->selectRaw('assets.*, ss.name as sale_site,  CASE WHEN at.transaction_type = "Sold" THEN at.create_datetime END AS sale_date')
                    ->where('assets.head_id', $headname)
                    ->where('at.transaction_type', 'Sold')
                    ->whereBetween('at.create_datetime', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('asset_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.asset.pdfs.saleAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        }else  if ($report_code == 5) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  $sitename,"");
            } else {
                $file_name = "Asset Sale Report By Site (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftJoin('asset_head', 'asset_head.id', 'assets.head_id')
                    ->leftjoin('asset_transaction as at', 'at.asset_id', '=', 'assets.id')                
                    ->selectRaw('assets.*, asset_head.name as head_name,  CASE WHEN at.transaction_type = "Sold" THEN at.create_datetime END AS sale_date')
                    ->where('assets.site_id', $sitename)
                    ->where('at.transaction_type', 'Sold')
                    ->whereBetween('at.create_datetime', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                    $sitename = DB::connection($user_db_conn_name)->table('sites')->where('id', $sitename)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.asset.pdfs.saleAccToSite', compact('data', 'start_date', 'end_date','sitename'));
                return $pdf->download($file_name);
            }
        }else  if ($report_code ==6) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Asset Sale Complete Report By Date (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('assets')
                    ->leftjoin('sites as ss', 'ss.id', '=', 'assets.site_id')

                    ->leftJoin('asset_head', 'asset_head.id', 'assets.head_id')
                    ->leftjoin('asset_transaction as at', 'at.asset_id', '=', 'assets.id')                
                    ->selectRaw('assets.*, asset_head.name as head_name,  ss.name as sale_site,  CASE WHEN at.transaction_type = "Sold" THEN at.create_datetime END AS sale_date')                  
                    ->where('at.transaction_type', 'Sold')
                    ->whereBetween('at.create_datetime', [$start_date, $end_date])
                    ->orderBy('assets.id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.asset.pdfs.compSale', compact('data', 'start_date', 'end_date',));
                return $pdf->download($file_name);
            }
        }
        else  if ($report_code ==7) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "",$headname);
            } else {
                $file_name = "Asset Transfer Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('asset_transaction')
                    ->leftjoin('sites as fs', 'fs.id', '=', 'asset_transaction.from_site')
                    ->leftjoin('sites as ts', 'ts.id', '=', 'asset_transaction.to_site')
                    ->leftJoin('assets', 'assets.id', 'asset_transaction.asset_id')
                    ->selectRaw('asset_transaction.*, assets.name as asset_name,  fs.name as from_site_name, ts.name as to_site_name')                                  
                    ->where('assets.head_id',$headname)
                    ->whereBetween('asset_transaction.create_datetime', [$start_date, $end_date])
                    ->orderBy('asset_transaction.asset_id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('asset_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.asset.pdfs.transAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code ==8) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Asset Complete Transfer Report (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('asset_transaction')
                    ->leftjoin('sites as fs', 'fs.id', '=', 'asset_transaction.from_site')
                    ->leftjoin('sites as ts', 'ts.id', '=', 'asset_transaction.to_site')
                    ->leftJoin('assets', 'assets.id', 'asset_transaction.asset_id')
                    ->leftJoin('asset_head', 'asset_head.id', 'assets.head_id')
                    ->selectRaw('asset_transaction.*, assets.name as asset_name,asset_head.name as head_name,  fs.name as from_site_name, ts.name as to_site_name')                                  
                    ->whereBetween('asset_transaction.create_datetime', [$start_date, $end_date])
                    ->orderBy('asset_transaction.asset_id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.asset.pdfs.compTrans', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } 
    }

    public function exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename = null, $headname = null)
    {
        $file_name = "Asset ";

        if ($report_code == 1) {
            $file_name .= "Purchase Report By Head ";
        } else if ($report_code == 2) {
            $file_name .= "Purchase Report By Site ";
        } else if ($report_code == 3) {
            $file_name .= "Purchase Complete Report By Date ";
        }
        else if ($report_code == 4) {
            $file_name .= "Sale Report By Head ";
        } else if ($report_code == 5) {
            $file_name .= "Sale Report By Site ";
        }else if ($report_code == 6) {
            $file_name .= "Sale Complete Report By Date ";
        }
        else if ($report_code == 7) {
            $file_name .= "Transfer Report By Head ";
        }else if ($report_code == 8) {
            $file_name .= "Complete Transfer Report ";
        }

        $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";
        return Excel::download(new AssetsExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, $headname), $file_name);
    }
}
