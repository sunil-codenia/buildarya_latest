<?php

namespace App\Http\Controllers\machinery;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\MachineryExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class MachineryController extends Controller
{
    //   
    // public function machinery(Request $request)
    // {
    //     $data = array();
    //     $id = $request->get('machinery_id');
    //     $get_site_id = $request->get('site_id');

    //     $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    //     $role_id = $request->session()->get('role');
    //     $site_id = $request->session()->get('site_id');
    //     $role_details = getRoleDetailsById($role_id);
    //     $visiblity_at_site = $role_details->visiblity_at_site;

    //     if ($visiblity_at_site == 'current') {
    //         $filters = [['machinery_details.head_id', '=', $id], ['machinery_details.site_id', '=', $site_id]];
    //     } else {
    //         $filters = [['machinery_details.head_id', '=', $id]];
    //     }

    //     $data = DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')->where($filters)->get();
    //     return  view('layouts.machinery.machinery')->with('data', json_encode($data));
    // }
    public function machinery(Request $request)
    {
        $data = array();
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;

        $query = DB::connection($user_db_conn_name)->table('machinery_details');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'machinery_details.site_id');
            $filters = [['machinery_details.head_id', '=', $id]];
        } else {
            $filters = [['machinery_details.head_id', '=', $id]];
        }

        $data = $query->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')->where($filters)->get();
        return  view('layouts.machinery.machinery')->with('data', json_encode($data));
    }
    // public function machinery_head(Request $request)
    // {
    //     $data = array();
    //     $user_db_conn_name = $request->session()->get('comp_db_conn_name');
    //     // $data = DB::connection($user_db_conn_name)->table('machinery_head')->get();
    //     $role_id = session()->get('role');
    //     $site_id = session()->get('site_id');
    //     $role_details = getRoleDetailsById($role_id);
    //     $visiblity_at_site = $role_details->visiblity_at_site;
    //     if ($visiblity_at_site == 'current') {
    //         $data = DB::connection($user_db_conn_name)->table('machinery_details')
    //             ->select('machinery_details.site_id', 'machinery_details.head_id', DB::raw('COUNT(machinery_details.id) as count'))
    //             ->where('machinery_details.status', '=', 'Working')->where('machinery_details.site_id', '=', $site_id)
    //             ->groupBy('machinery_details.head_id', 'machinery_details.site_id')
    //             ->get();
    //     } else {
    //         $data = DB::connection($user_db_conn_name)->table('machinery_details')
    //             ->select('machinery_details.site_id', 'machinery_details.head_id', DB::raw('COUNT(machinery_details.id) as count'))
    //             ->where('machinery_details.status', '=', 'Working')
    //             ->groupBy('machinery_details.head_id', 'machinery_details.site_id')
    //             ->get();
    //     }

    //     return  view('layouts.machinery.head')->with('data', json_encode($data));
    // }

    public function machinery_head(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = DB::connection($user_db_conn_name)->table('machinery_head')->get();

        return  view('layouts.machinery.head')->with('data', json_encode($data))->with('showing_data','all');
    }
    public function search_machinery_head_sites(Request $request){
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $site_id = $request->input('display_site');
        $data = DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')->where('machinery_details.site_id','=',$site_id)->get();

        return  view('layouts.machinery.head')->with('data', json_encode($data))->with('showing_data',$site_id);
    }
    public function add_newmechinery(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $site_id = $request->input('site_id');
        $machinery_name = $request->input('macname');
        $head_id = $request->input('head_id');
        $costprice = $request->input('costprice');
        try {

            $data = ['name' => $machinery_name, 'head_id' => $head_id, 'site_id' => $site_id,  'cost_price' => $costprice];
            $id = DB::connection($user_db_conn_name)->table('machinery_details')->insertGetId($data);
            addActivity($id, 'machinery_details', "New Machinery Created", 6);
            $machinery_trans = [
                'machinery_id' => $id,
                'to_site' => $site_id,
                'transaction_type' => 'Purchase',
                'remark' => 'Machinery added manually',
            ];
            DB::connection($user_db_conn_name)->table('machinery_transaction')->insert($machinery_trans);
            return redirect('/machinery_head')
                ->with('success', 'Machinery Created successfully!');
        } catch (\Exception $e) {
            return redirect('/machinery_head')
                ->with('error', 'Error While Creating Machinery!');
        }
    }


    public function addmachineryhead(Request $request)
    {
        $name = $request->input('name');
        $data = ['name' => $name];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $id = DB::connection($user_db_conn_name)->table('machinery_head')->insertGetId($data);
            addActivity($id, 'machinery_head', "New Machinery Head Created", 6);
            return redirect('/machinery_head')
                ->with('success', 'Machinery Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/machinery_head')
                    ->with('error', 'Machinery Head Already Exists!');
            } else {
                return redirect('/machinery_head')
                    ->with('error', 'Error While Creating Machinery Head!');
            }
        }
    }
    public function updatemachineryhead(Request $request)
    {
        $id = $request->input('id');
        $name = $request->input('name');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $id)->update(['name' => $name]);
            addActivity($id, 'machinery_head', "Machinery Head Updated", 6);
            return redirect('/machinery_head')
                ->with('success', 'Machinery Head Updated Successfully!');;
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/machinery_head')
                    ->with('error', 'Machinery Head Already Exists!');
            } else {
                return redirect('/machinery_head')
                    ->with('error', 'Error While Updating Machinery Head!');
            }
        }
    }
    public function edit_machinery_head(Request $request)
    {
        $id = $request->get('id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');



        $role_id = session()->get('role');
        $site_id = session()->get('site_id');
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;
        if ($visiblity_at_site == 'current') {
            $data['data'] = DB::connection($user_db_conn_name)->table('machinery_details')
                ->select('machinery_details.site_id', 'machinery_details.head_id', DB::raw('COUNT(machinery_details.id) as count'))
                ->where('machinery_details.status', '=', 'Working')->where('machinery_details.site_id', '=', $site_id)
                ->groupBy('machinery_details.head_id', 'machinery_details.site_id')
                ->get();
        } else {
            $data['data'] = DB::connection($user_db_conn_name)->table('machinery_details')
                ->select('machinery_details.site_id', 'machinery_details.head_id', DB::raw('COUNT(machinery_details.id) as count'))
                ->where('machinery_details.status', '=', 'Working')
                ->groupBy('machinery_details.head_id', 'machinery_details.site_id')
                ->get();
        }

        $data['edit_data'] = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', '=', $id)->get();
        return  view('layouts.machinery.head')->with('data', json_encode($data));
    }
    public function delete_machinery_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $check = DB::connection($user_db_conn_name)->table('machinery_details')->where('head_id', '=', $id)->get();
        $machinery_head = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', '=', $id)->get()[0]->name;

        if (Count($check) > 0) {
            return redirect('/machinery_head')
                ->with('error', 'Machinery Head Is In Use!');
        } else {
            DB::connection($user_db_conn_name)->table('machinery_head')->where('id', '=', $id)->delete();
            addActivity(0, 'machinery_head', "Machinery Head Deleted - " . $machinery_head, 6);
            return redirect('/machinery_head')
                ->with('success', 'Machinery Head Deleted Successfully!');
        }
    }

    public function machinery_expense_head(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['data'] = DB::connection($user_db_conn_name)->table('machinery_expense_head')->leftjoin('expense_head', 'expense_head.id', '=', 'machinery_expense_head.head_id')->select('machinery_expense_head.*',  'expense_head.name as head')->get();
        $data['heads'] = DB::connection($user_db_conn_name)->table('expense_head')->get();
        return  view('layouts.machinery.expenseHead')->with('data', json_encode($data));
    }
    public function addmachineryExpensehead(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $data = ['head_id' => $head_id];
        try {
            $id = DB::connection($user_db_conn_name)->table('machinery_expense_head')->insertGetId($data);
            addActivity($id, 'machinery_expense_head', "New Machinery Expense Head Added", 6);
            return redirect('/machinery_expense_head')
                ->with('success', 'Machinery\'s Expense Head Created successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/machinery_expense_head')
                    ->with('error', 'Machinery\'s Expense Head Already Exists!');
            } else {
                return redirect('/machinery_expense_head')
                    ->with('error', 'Error While Creating Machinery\'s Expense Head!');
            }
        }
    }
    public function delete_machineryExpense_head(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        DB::connection($user_db_conn_name)->table('machinery_expense_head')->where('id', '=', $id)->delete();
        addActivity(0, 'machinery_expense_head', "Machinery Expense Head Removed", 6);
        return redirect('/machinery_expense_head')
            ->with('success', 'Machinery\'s Expense Head Deleted Successfully!');
    }


    public function transfermachinery(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $from_site = $request->get('from_site');
        $to_site = $request->get('to_site');
        $remark = $request->get('remark');
        if ($from_site == $to_site) {
            return redirect('/machinery?machinery_id=' . $head_id)
                ->with('error', 'Machinery Already On Same Site!');
        } else {

            try {
                DB::connection($user_db_conn_name)->table('machinery_details')->where('id', $id)->update(['site_id' => $to_site]);
                addActivity($id, 'machinery_details', "Machinery Transferred From Site", 6);
                $machinery_trans = [
                    'machinery_id' => $id,
                    'from_site' => $from_site,
                    'to_site' => $to_site,
                    'transaction_type' => 'Transfer',
                    'remark' => $remark
                ];
                DB::connection($user_db_conn_name)->table('machinery_transaction')->insert($machinery_trans);
                return redirect('/machinery?machinery_id=' . $head_id)
                    ->with('success', 'Machinery\'s Transfered successfully!');
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    return redirect('/machinery?machinery_id=' . $head_id)
                        ->with('error', 'Machinery\'s Transfer Failed!');
                }
            }
        }
    }
    public function soldmachinery(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $head_id = $request->get('head_id');
        $from_site = $request->get('from_site');
        $sold_value = $request->get('sold_value');
        $remark = $request->get('remark');
        $date = $request->get('date');


        try {
            DB::connection($user_db_conn_name)->table('machinery_details')->where('id', $id)->update(['status' => 'Sold', 'sale_price' => $sold_value]);
            $machinery_trans = [
                'machinery_id' => $id,
                'from_site' => $from_site,
                'transaction_type' => 'Sold',
                'remark' => $remark
            ];
            DB::connection($user_db_conn_name)->table('machinery_transaction')->insert($machinery_trans);
            addActivity($id, 'machinery_details', "Machinery Sold ", 6);
            $this->addsitesBalance($from_site, $sold_value, "Machinery Sold - " . $remark, $date, $user_db_conn_name);
            return redirect('/machinery?machinery_id=' . $head_id)
                ->with('success', 'Machinery\'s Sold successfully!');
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                return redirect('/machinery?machinery_id=' . $head_id)
                    ->with('error', 'Machinery\'s Sold Failed!');
            }
        }
    }
    public function addsitesBalance($id, $amount, $remark, $date, $user_db_conn_name)
    {

        $data = [
            'site_id' => $id,
            'amount' => $amount,
            'remark' => $remark,
            'date' => $date
        ];
        $pay_id =  DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);
        addActivity($pay_id, 'site_payments', "Site Payments Created Against Machinery Sold ", 6);
        $tdata = [
            'site_id' => $id,
            'type' => 'Credit',
            'payment_id' => $pay_id
        ];
        DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

        DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
    }
    public function machineryTransferHistory(Request $request)
    {
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['machinery'] =   DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head')->where('machinery_details.id', '=', $id)->get();


        $data['history'] = DB::connection($user_db_conn_name)->table('machinery_transaction')->where('machinery_id', '=', $id)->get();
        return  view('layouts.machinery.machineryTransferHistory')->with('data', json_encode($data));
    }

    public function machineryDocuments(Request $request)
    {
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['machinery'] =   DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head')->where('machinery_details.id', '=', $id)->get();
        $data['machinery_documents'] =   DB::connection($user_db_conn_name)->table('machinery_documents')->where('machinery_id', '=', $id)->get();

        return  view('layouts.machinery.machineryDocument')->with('data', json_encode($data));
    }
    public function addmachinerydocument(Request $request)
    {
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $name = $request->get('name');
        $issue_date = $request->get('issue_date');
        $end_date = $request->get('end_date');
        $remark = $request->get('remark');
        if (isset($request->attachment)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->attachment->extension();
                $request->attachment->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_doc'), $imageName);
                $imagePath = "images/app_images/" . $user_db_conn_name . "/machinery_doc/" . $imageName;
                $rawd = [
                    'machinery_id' => $id,
                    'name' => $name,
                    'issue_date' => $issue_date,
                    'end_date' => $end_date,
                    'remark' => $remark,
                    'attachment' => $imagePath
                ];

                $doc_id = DB::connection($user_db_conn_name)->table('machinery_documents')->insertGetId($rawd);
                addActivity($doc_id, 'machinery_documents', "New Machinery Document Uploaded", 6);
                return redirect('/machineryDocuments?machinery_id=' . $id)
                    ->with('success', 'Document Uploaded successfully!');
            } catch (\Exception $e) {
                return redirect('/machineryDocuments?machinery_id=' . $id)
                    ->with('error', 'Document Upload Failed!');
            }
        } else {
            return redirect('/machineryDocuments?machinery_id=' . $id)
                ->with('error', 'Document Attchment Is Required!');
        }
    }
    public function delete_machinery_document(Request $request)
    {
        $id = $request->get('id');
        $machinery_id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $document = DB::connection($user_db_conn_name)->table('machinery_documents')->where('id', '=', $id)->get()[0];
        if (File::exists($document->attachment)) {
            File::delete($document->attachment);
        }
        DB::connection($user_db_conn_name)->table('machinery_documents')->where('id', '=', $id)->delete();
        addActivity(0, 'machinery_documents', "Machinery Document Deleted- " . $document->name, 6);
        return redirect('/machineryDocuments?machinery_id=' . $machinery_id)
            ->with('success', 'Machinery\'s Document Deleted Successfully!');
    }
    public function edit_machinery_document(Request $request)
    {
        $id = $request->get('id');
        $machinery_id = $request->get('machinery_id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['machinery'] =   DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head')->where('machinery_details.id', '=', $machinery_id)->get();
        $data['machinery_documents'] =   DB::connection($user_db_conn_name)->table('machinery_documents')->where('machinery_id', '=', $machinery_id)->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('machinery_documents')->where('id', '=', $id)->get();
        return  view('layouts.machinery.machineryDocument')->with('data', json_encode($data));
    }
    public function updatemachinerydocument(Request $request)
    {
        $machinery_id = $request->get('machinery_id');
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $name = $request->get('name');
        $issue_date = $request->get('issue_date');
        $end_date = $request->get('end_date');
        $remark = $request->get('remark');
        $document = DB::connection($user_db_conn_name)->table('machinery_documents')->where('id', '=', $id)->get()[0];

        if (isset($request->attachment) || !empty($document->attachment)) {
            try {

                if (isset($request->attachment)) {
                    if (File::exists($document->attachment)) {
                        File::delete($document->attachment);
                    }
                    $imageName = time() . rand(10000, 1000000) . '.' . $request->attachment->extension();
                    $request->attachment->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_doc'), $imageName);
                    $imagePath = "images/app_images/" . $user_db_conn_name . "/machinery_doc/" . $imageName;
                } else {
                    $imagePath = $document->attachment;
                }

                $rawd = [
                    'id' => $id,
                    'machinery_id' => $machinery_id,
                    'name' => $name,
                    'issue_date' => $issue_date,
                    'end_date' => $end_date,
                    'remark' => $remark,
                    'attachment' => $imagePath
                ];

                DB::connection($user_db_conn_name)->table('machinery_documents')->upsert($rawd, 'id');
                addActivity($id, 'machinery_documents', "Machinery Document Updated", 6);

                return redirect('/machineryDocuments?machinery_id=' . $machinery_id)
                    ->with('success', 'Document Updated successfully!');
            } catch (\Exception $e) {
                return redirect('/machineryDocuments?machinery_id=' . $machinery_id)
                    ->with('error', 'Document Updation Failed!');
            }
        } else {
            return redirect('/machineryDocuments?machinery_id=' . $machinery_id)
                ->with('error', 'Document Attchment Is Required!');
        }
    }

    public function machineryService(Request $request)
    {
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['machinery'] =   DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head')->where('machinery_details.id', '=', $id)->get();
        $data['machinery_services'] =   DB::connection($user_db_conn_name)->table('machinery_services')->where('machinery_id', '=', $id)->get();

        return  view('layouts.machinery.services')->with('data', json_encode($data));
    }
    public function addmachineryservice(Request $request)
    {
        $id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $create_date = $request->get('create_date');
        $next_service_on = $request->get('next_service_on');
        $maintainence_item = $request->get('maintainence_item');
        $user_id = session()->get('uid');
        $image1 = "";
        $image2 = "";
        $image3 = "";
        $image4 = "";
        $image5 = "";
        $remark = $request->get('remark');
        if (isset($request->image1)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image1->extension();
                $request->image1->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
                $image1 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
            } catch (\Exception $e) {
                return redirect('/machineryService?machinery_id=' . $id)
                    ->with('error', 'Attachment Upload Failed!');
            }
        } else {
            return redirect('/machineryService?machinery_id=' . $id)
                ->with('error', 'service Attchment Is Required!');
        }
        if (isset($request->image2)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image2->extension();
                $request->image2->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
                $image2 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
            } catch (\Exception $e) {
                return redirect('/machineryService?machinery_id=' . $id)
                    ->with('error', 'Attachment Upload Failed!');
            }
        } else {
            return redirect('/machineryService?machinery_id=' . $id)
                ->with('error', 'service Attchment Is Required!');
        }
        if (isset($request->image3)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image3->extension();
                $request->image3->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
                $image3 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
            } catch (\Exception $e) {
                return redirect('/machineryService?machinery_id=' . $id)
                    ->with('error', 'Attachment Upload Failed!');
            }
        }
        if (isset($request->image4)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image4->extension();
                $request->image4->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
                $image4 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
            } catch (\Exception $e) {
                return redirect('/machineryService?machinery_id=' . $id)
                    ->with('error', 'Attachment Upload Failed!');
            }
        }
        if (isset($request->image5)) {
            try {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image5->extension();
                $request->image5->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
                $image5 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
            } catch (\Exception $e) {
                return redirect('/machineryService?machinery_id=' . $id)
                    ->with('error', 'Attachment Upload Failed!');
            }
        }
        $rawd = [
            'machinery_id' => $id,
            'create_date' => $create_date,
            'next_service_on' => $next_service_on,
            'maintainence_item' => $maintainence_item,
            'remark' => $remark,
            'image1' => $image1,
            'image2' => $image2,
            'image3' => $image3,
            'image4' => $image4,
            'image5' => $image5,
            'user_id' => $user_id,
        ];
        try {
            $ser_id =  DB::connection($user_db_conn_name)->table('machinery_services')->insertGetId($rawd);
            addActivity($ser_id, 'machinery_services', "Machinery Service Uploaded ", 6);

            return redirect('/machineryService?machinery_id=' . $id)
                ->with('success', 'Service Uploaded successfully!');
        } catch (\Exception $e) {
            return redirect('/machineryService?machinery_id=' . $id)
                ->with('error', 'Service Uploaded Failed!');
        }
    }
    public function delete_machinery_service(Request $request)
    {
        $id = $request->get('id');
        $machinery_id = $request->get('machinery_id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $service = DB::connection($user_db_conn_name)->table('machinery_services')->where('id', '=', $id)->get();
        if (File::exists($service->image1)) {
            File::delete($service->image1);
        }
        if (File::exists($service->image2)) {
            File::delete($service->image2);
        }
        if (File::exists($service->image3)) {
            File::delete($service->image3);
        }
        if (File::exists($service->image4)) {
            File::delete($service->image4);
        }
        if (File::exists($service->image5)) {
            File::delete($service->image5);
        }
        DB::connection($user_db_conn_name)->table('machinery_services')->where('id', '=', $id)->delete();
        addActivity(0, 'machinery_services', "Machinery Service Deleted", 6);
        return redirect('/machineryService?machinery_id=' . $machinery_id)
            ->with('success', 'Machinery\'s service Deleted Successfully!');
    }
    public function edit_machinery_service(Request $request)
    {
        $id = $request->get('id');
        $machinery_id = $request->get('machinery_id');
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['machinery'] =   DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'machinery_head.name as head')->where('machinery_details.id', '=', $machinery_id)->get();
        $data['machinery_services'] =   DB::connection($user_db_conn_name)->table('machinery_services')->where('machinery_id', '=', $machinery_id)->get();
        $data['edit_data'] = DB::connection($user_db_conn_name)->table('machinery_services')->where('id', '=', $id)->get();
        return  view('layouts.machinery.services')->with('data', json_encode($data));
    }
    public function updatemachineryservice(Request $request)
    {
        $machinery_id = $request->get('machinery_id');
        $id = $request->get('id');
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');

        $create_date = $request->get('create_date');
        $next_service_on = $request->get('next_service_on');
        $maintainence_item = $request->get('maintainence_item');
        $user_id = session()->get('uid');

        $remark = $request->get('remark');
        $service = DB::connection($user_db_conn_name)->table('machinery_services')->where('id', '=', $id)->get()[0];

        if (isset($request->image1)) {
            if (File::exists($service->image1)) {
                File::delete($service->image1);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image1->extension();
            $request->image1->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
            $image1 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
        } else {
            $image1 = $service->image1;
        }
        if (isset($request->image2)) {
            if (File::exists($service->image2)) {
                File::delete($service->image2);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image2->extension();
            $request->image2->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
            $image2 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
        } else {
            $image2 = $service->image2;
        }



        if (isset($request->image3)) {
            if (File::exists($service->image3)) {
                File::delete($service->image3);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image3->extension();
            $request->image3->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
            $image3 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
        } else {
            $image3 = $service->image3;
        }
        if (isset($request->image4)) {
            if (File::exists($service->image4)) {
                File::delete($service->image4);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image4->extension();
            $request->image4->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
            $image4 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
        } else {
            $image4 = $service->image4;
        }

        if (isset($request->image5)) {
            if (File::exists($service->image5)) {
                File::delete($service->image5);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image5->extension();
            $request->image5->move(public_path('images/app_images/' . $user_db_conn_name . '/machinery_service'), $imageName);
            $image5 = "images/app_images/" . $user_db_conn_name . "/machinery_service/" . $imageName;
        } else {
            $image5 = $service->image5;
        }

        try {
            $rawd = [
                'id' => $id,
                'machinery_id' => $machinery_id,
                'create_date' => $create_date,
                'next_service_on' => $next_service_on,
                'maintainence_item' => $maintainence_item,
                'remark' => $remark,
                'image1' => $image1,
                'image2' => $image2,
                'image3' => $image3,
                'image4' => $image4,
                'image5' => $image5,
                'user_id' => $user_id,
            ];
            DB::connection($user_db_conn_name)->table('machinery_services')->upsert($rawd, 'id');
            addActivity($id, 'machinery_services', "Machinery Service Updated ", 6);
            return redirect('/machineryService?machinery_id=' . $machinery_id)
                ->with('success', 'Service Updated successfully!');
        } catch (\Exception $e) {
            return redirect('/machineryService?machinery_id=' . $machinery_id)
                ->with('error', 'Service Updation Failed!');
        }
    }
    public function machinery_report(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $machinery_heads =  DB::connection($user_db_conn_name)->table('machinery_head')->get();
        $sites =  DB::connection($user_db_conn_name)->table('sites')->get();
        return view('layouts.machinery.machinery_report', compact(['machinery_heads', 'sites']));
    }
    public function machinery_of_site_report(Request $request){
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $sitename = $request->get('site_id');
        $site = DB::connection($user_db_conn_name)->table('sites')->where('id',$sitename)->first();
        addActivity(0, 'machinery_details', "Machinery Report Generated Of Site - ".$site->name, 6);

        if ($type == 1) {
            $file_name = "Machinery Report Of Site - ".$site->name.".xlsx";

            return Excel::download(new MachineryExport($user_db_conn_name, null, null, 13, $sitename, null), $file_name);
    
        } else {
            $file_name = "Machinery Report Of Site - ".$site->name.".pdf";
             $data = DB::connection($user_db_conn_name)->table('machinery_details')->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')->where('machinery_details.site_id','=',$sitename)->get();
             $site_name = $site->name;
            $pdf = Pdf::loadView('layouts.machinery.pdfs.siteMachineryReport', compact('data', 'site_name'));

            return $pdf->download($file_name);
        }
    }
    public function machineryexport(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $report_code = $request->get('type');


        $start_date = $request->get('start_date');
        $sitename = $request->get('site_id');
        $headname = $request->get('head_id');
        $end_date = $request->get('end_date');
        addActivity(0, 'machinery_details', "Machinery Report Generated Of Data (" . $start_date . " - " . $end_date . ")", 6);
        if ($report_code == 1) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $headname);
            } else {
                $file_name = "Machinery Purchase Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'machinery_details.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'machinery_details.expense_id')
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
                    ->selectRaw('machinery_details.*, ws.name as working_site, ps.name as purchase_site,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->where('machinery_details.head_id', $headname)
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                $headname = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.purrAccToHead', compact('data', 'start_date', 'end_date', 'headname'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 2) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, "");
            } else {
                $file_name = "Machinery Purchase Report By Site (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'machinery_details.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'machinery_details.expense_id')
                    ->leftjoin('users as u', 'u.id', '=', 'expenses.user_id')
                    ->leftJoin('bills_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'bills_party.id')
                            ->where('expenses.party_type', '=', 'bill');
                    })
                    ->leftJoin('expense_party', function ($join) {
                        $join->on('expenses.party_id', '=', 'expense_party.id')
                            ->where('expenses.party_type', '=', 'expense');
                    })
                    ->selectRaw('machinery_details.*, ws.name as working_site, machinery_head.name as head_name,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->where('expenses.site_id', $sitename)
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                $sitename = DB::connection($user_db_conn_name)->table('sites')->where('id', $sitename)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.purrAccToSite', compact('data', 'start_date', 'end_date', 'sitename'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 3) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", "");
            } else {
                $file_name = "Machinery Purchase Complete Report By Date (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftjoin('sites as ws', 'ws.id', '=', 'machinery_details.site_id')
                    ->leftjoin('expenses', 'expenses.id', '=', 'machinery_details.expense_id')
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
                    ->selectRaw('machinery_details.*, ws.name as working_site, ps.name as purchase_site, machinery_head.name as head_name,expenses.date,u.name as user_name, CASE WHEN expenses.party_type = "bill" THEN bills_party.name WHEN expenses.party_type = "expense" THEN expense_party.name END AS party_name')
                    ->whereBetween('expenses.date', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.machinery.pdfs.compPurr', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 4) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $headname);
            } else {
                $file_name = "Machinery Sale Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftjoin('sites as ss', 'ss.id', '=', 'machinery_details.site_id')
                    ->leftjoin('machinery_transaction as mt', 'mt.machinery_id', '=', 'machinery_details.id')
                    
                    ->selectRaw('machinery_details.*, ss.name as sale_site,  CASE WHEN mt.transaction_type = "Sold" THEN mt.create_datetime END AS sale_date')
                    ->where('machinery_details.head_id', $headname)
                    ->where('mt.transaction_type', 'Sold')
                    ->whereBetween('mt.create_datetime', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.saleAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        }else  if ($report_code == 5) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  $sitename,"");
            } else {
                $file_name = "Machinery Sale Report By Site (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftjoin('machinery_transaction as mt', 'mt.machinery_id', '=', 'machinery_details.id')                
                    ->selectRaw('machinery_details.*, machinery_head.name as head_name,  CASE WHEN mt.transaction_type = "Sold" THEN mt.create_datetime END AS sale_date')
                    ->where('machinery_details.site_id', $sitename)
                    ->where('mt.transaction_type', 'Sold')
                    ->whereBetween('mt.create_datetime', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                    $sitename = DB::connection($user_db_conn_name)->table('sites')->where('id', $sitename)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.saleAccToSite', compact('data', 'start_date', 'end_date','sitename'));
                return $pdf->download($file_name);
            }
        }else  if ($report_code ==6) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Machinery Sale Complete Report By Date (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_details')
                    ->leftjoin('sites as ss', 'ss.id', '=', 'machinery_details.site_id')

                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftjoin('machinery_transaction as mt', 'mt.machinery_id', '=', 'machinery_details.id')                
                    ->selectRaw('machinery_details.*, machinery_head.name as head_name,  ss.name as sale_site,  CASE WHEN mt.transaction_type = "Sold" THEN mt.create_datetime END AS sale_date')                  
                    ->where('mt.transaction_type', 'Sold')
                    ->whereBetween('mt.create_datetime', [$start_date, $end_date])
                    ->orderBy('machinery_details.id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.machinery.pdfs.compSale', compact('data', 'start_date', 'end_date',));
                return $pdf->download($file_name);
            }
        }
        else  if ($report_code ==7) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "",$headname);
            } else {
                $file_name = "Machinery Transfer Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_transaction')
                    ->leftjoin('sites as fs', 'fs.id', '=', 'machinery_transaction.from_site')
                    ->leftjoin('sites as ts', 'ts.id', '=', 'machinery_transaction.to_site')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_transaction.machinery_id')
                    ->selectRaw('machinery_transaction.*, machinery_details.name as machine_name,  fs.name as from_site_name, ts.name as to_site_name')                                  
                    ->where('machinery_details.head_id',$headname)
                    ->whereBetween('machinery_transaction.create_datetime', [$start_date, $end_date])
                    ->orderBy('machinery_transaction.machinery_id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.transAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code ==8) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Machinery Complete Transfer Report (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_transaction')
                    ->leftjoin('sites as fs', 'fs.id', '=', 'machinery_transaction.from_site')
                    ->leftjoin('sites as ts', 'ts.id', '=', 'machinery_transaction.to_site')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_transaction.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->selectRaw('machinery_transaction.*, machinery_details.name as machine_name,machinery_head.name as head_name,  fs.name as from_site_name, ts.name as to_site_name')                                  
                    ->whereBetween('machinery_transaction.create_datetime', [$start_date, $end_date])
                    ->orderBy('machinery_transaction.machinery_id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.machinery.pdfs.compTrans', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 9) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "",$headname);
            } else {
                $file_name = "Machinery Document Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_documents')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_documents.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->selectRaw('machinery_documents.*, machinery_details.name as machine_name')
                    ->where('machinery_details.head_id',$headname)         
                    ->whereBetween('machinery_documents.create_date', [$start_date, $end_date])
                    ->orderBy('machinery_documents.machinery_id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.docAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        }
        else  if ($report_code == 10) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Machinery Complete Document Report (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_documents')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_documents.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->selectRaw('machinery_documents.*, machinery_details.name as machine_name,machinery_head.name as head_name')                                  
                    ->whereBetween('machinery_documents.create_date', [$start_date, $end_date])
                    ->orderBy('machinery_documents.machinery_id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.machinery.pdfs.compDoc', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        }else  if ($report_code == 11) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "",$headname);
            } else {
                $file_name = "Machinery Service Report By Head (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_services')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_services.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftJoin('users','users.id','machinery_services.user_id')
                    ->selectRaw('machinery_services.*, machinery_details.name as machine_name,users.name as user_name')
                    ->where('machinery_details.head_id',$headname)         
                    ->whereBetween('machinery_services.create_date', [$start_date, $end_date])
                    ->orderBy('machinery_services.machinery_id', 'desc')->get();
                    $headname = DB::connection($user_db_conn_name)->table('machinery_head')->where('id', $headname)->get()[0]->name;
                $pdf = Pdf::loadView('layouts.machinery.pdfs.servAccToHead', compact('data', 'start_date', 'end_date','headname'));
                return $pdf->download($file_name);
            }
        }
        else  if ($report_code == 12) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code,  "","");
            } else {
                $file_name = "Machinery Complete Service Report (" . $start_date . " To " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('machinery_services')
                    ->leftJoin('machinery_details', 'machinery_details.id', 'machinery_services.machinery_id')
                    ->leftJoin('machinery_head', 'machinery_head.id', 'machinery_details.head_id')
                    ->leftJoin('users','users.id','machinery_services.user_id')
                    ->selectRaw('machinery_services.*, machinery_details.name as machine_name,users.name as user_name,machinery_head.name as head_name')
                    ->whereBetween('machinery_services.create_date', [$start_date, $end_date])
                    ->orderBy('machinery_services.machinery_id', 'desc')->get();
                $pdf = Pdf::loadView('layouts.machinery.pdfs.compServ', compact('data', 'start_date', 'end_date'));
                return $pdf->download($file_name);
            }
        }
        
    }

    public function exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename = null, $headname = null)
    {
        $file_name = "Machinery ";

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
        }else if ($report_code == 9) {
            $file_name .= "Document Report By Head ";
        }else if ($report_code == 10) {
            $file_name .= "Complete Document Report ";
        }else if ($report_code == 11) {
            $file_name .= "Service Report By Head ";
        }else if ($report_code == 12) {
            $file_name .= "Complete Service Report ";
        }

        $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";
        // $filename = str_replace(['(', ')', '&',' ','/','\''], ['_', '_', 'and' , '_','_','_'], $file_name);
        return Excel::download(new MachineryExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename, $headname), $file_name);
    }
}
