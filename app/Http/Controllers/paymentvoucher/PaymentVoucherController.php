<?php

namespace App\Http\Controllers\paymentvoucher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Response;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PaymentExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class PaymentVoucherController extends Controller
{
    //
    public function verified_paymentvoucher(Request $request)
    {
        $data = array();
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
        $query = DB::connection($user_db_conn_name)->table('payment_vouchers');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'payment_vouchers.site_id');
            $filters = array();
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['payment_vouchers.site_id', '=', $req_site_id]];
            } else {
                $filters = array();
            }
        }

        $data = $query->leftjoin('sites', 'sites.id', '=', 'payment_vouchers.site_id')->leftjoin('sales_company', 'sales_company.id', '=', 'payment_vouchers.company_id')->select('payment_vouchers.*', 'sites.name as site', 'sales_company.name as company')->whereIn('payment_vouchers.status', ['Rejected', 'Approved'])->where($filters)->whereBetween('payment_vouchers.date', [$min_date, $max_date])->orderBy('payment_vouchers.create_datetime', 'desc')->get();
        return  view('layouts.paymentvoucher.verified')->with('data', json_encode($data));
    }
    public function pending_paymentvoucher(Request $request)
    {
        $data = array();
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
        $query = DB::connection($user_db_conn_name)->table('payment_vouchers');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'payment_vouchers.site_id');
            $filters = [['payment_vouchers.status', '=', 'Pending']];
        } else {
            if ($req_site_id && $req_site_id != 'all') {
                $filters = [['payment_vouchers.status', '=', 'Pending'], ['payment_vouchers.site_id', '=', $req_site_id]];
            } else {
                $filters = [['payment_vouchers.status', '=', 'Pending']];
            }
        }

        $data = $query->leftjoin('sites', 'sites.id', '=', 'payment_vouchers.site_id')->leftjoin('sales_company', 'sales_company.id', '=', 'payment_vouchers.company_id')->select('payment_vouchers.*', 'sites.name as site', 'sales_company.name as company')->where($filters)->whereBetween('payment_vouchers.date', [$min_date, $max_date])->orderBy('payment_vouchers.create_datetime', 'desc')->get();
        return  view('layouts.paymentvoucher.pending')->with('data', json_encode($data));
    }


    public function paid_paymentvoucher(Request $request)
    {
        $data = array();
        $role_id = $request->session()->get('role');
        $site_id = $request->session()->get('site_id');

        $role_details = getRoleDetailsById($role_id);

        $view_duration = $request->session()->get('view_duration');
        $visiblity_at_site = $role_details->visiblity_at_site;

        $dates = getdurationdates($view_duration);
        $min_date = $dates['min'];
        $max_date = $dates['max'];
        $query = DB::connection($user_db_conn_name)->table('payment_vouchers');
        if ($visiblity_at_site == 'current') {
            apply_site_filter($query, $site_id, 'payment_vouchers.site_id');
            $filters = [['payment_vouchers.status', '=', 'Paid']];
        } else {
            $filters = [['payment_vouchers.status', '=', 'Paid']];
        }
        $data = $query->leftjoin('sites', 'sites.id', '=', 'payment_vouchers.site_id')->leftjoin('sales_company', 'sales_company.id', '=', 'payment_vouchers.company_id')->select('payment_vouchers.*', 'sites.name as site', 'sales_company.name as company')->where($filters)->whereBetween('payment_vouchers.create_datetime', [$min_date, $max_date])->orderBy('payment_vouchers.create_datetime', 'desc')->get();
        return  view('layouts.paymentvoucher.paid')->with('data', json_encode($data));
    }
    public function new_paymentvoucher(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['companies'] = DB::connection($user_db_conn_name)->table('sales_company')->where('status', '=', 'Active')->get();
        $data['material_suppliers'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $data['official_sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->where('sites_type', '=','Office Site')->get();
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['other_parties'] = DB::connection($user_db_conn_name)->table('other_parties')->where('status', '=', 'Active')->get();
        return  view('layouts.paymentvoucher.new')->with('data', json_encode($data));
    }
    public function edit_paymentvoucher(Request $request)
    {
        $data = array();
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data['paymentvoucher'] = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $request->get('id'))->get()[0];
        $data['companies'] = DB::connection($user_db_conn_name)->table('sales_company')->where('status', '=', 'Active')->get();
        $data['material_suppliers'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('status', '=', 'Active')->get();
        $data['sites'] = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
        $data['bill_parties'] = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Active')->get();
        $data['other_parties'] = DB::connection($user_db_conn_name)->table('other_parties')->where('status', '=', 'Active')->get();


        $site_id = session()->get("site_id");
        $role_details = getRoleDetailsById(session()->get('role'));
        $entry_at_site = $role_details->entry_at_site;
        $add_duration = $request->session()->get('add_duration');
        $duration = getdurationdates($add_duration);
        $min_date = $duration['min'];
        if ($entry_at_site == "current" && $site_id != $data['paymentvoucher']->site_id) {
            return redirect('/pending_paymentvoucher')->with('error', "You don't have permission to edit entries at site - " . getSiteDetailsById($data['paymentvoucher']->site_id)->name . "!");
        }
        if ($data['paymentvoucher']->date < $min_date) {
            return redirect('/pending_paymentvoucher')
                ->with('error', "You don't have permission to edit entries before " . $min_date . " !");
        }
        return  view('layouts.paymentvoucher.edit')->with('data', json_encode($data));
    }

    public function addnewpaymentvouchers(Request $request)
    {
        // print_r($request);

        $res = false;
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $data = $request->input();
        // print_r($data);
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $length = count($data['site_id']);

        for ($i = 0; $i < $length; $i++) {
            if (isset($request->image[$i])) {
                $imageName = time() . rand(10000, 1000000) . '.' . $request->image[$i]->extension();
                $request->image[$i]->move(public_path('images/app_images/' . $user_db_conn_name . '/paymentvoucher'), $imageName);
                $imagePath = "images/app_images/" . $user_db_conn_name . "/paymentvoucher/" . $imageName;
            } else {
                $imagePath = "images/expense.png";
            }
            $party = explode("||", $data['party_id'][$i]);
            $rawd = [
                'company_id' => $data['company_id'][$i],
                'site_id' => $data['site_id'][$i],
                'party_type' => $party[1],
                'party_id' => $party[0],
                'voucher_no' => $data['voucher_no'][$i],
                'amount' => $data['amount'][$i],
                'date' => $data['date'][$i],
                'payment_details' => $data['payment_details'][$i],
                'remark' => $data['remark'][$i],
                'created_by' => $user_id,
                'image' => $imagePath,
                'status' => $status,
            ];
            try {
                $id = DB::connection($user_db_conn_name)->table('payment_vouchers')->insertGetId($rawd);
                addActivity($id, 'payment_vouchers', "New Payment Vouchers Created", 8);

                if ($status == 'Approved') {
                    $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->get()[0];
                    if ($paymentvoucher->party_type == 'bill') {
                        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'material') {
                        $party_status = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'other') {
                        $party_status = DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'site') {
                        $party_status = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    }
                    if ($party_status->status == 'Active') {
                        $this->approve_paymentvoucher($id, $user_db_conn_name);
                        addActivity($id, 'payment_vouchers', "Payment Vouchers Approved", 8);
                    }
                }
                $res = true;
            } catch (\Exception $e) {
                if ($e->getCode() == 23000) {
                    $etext = "Voucher No Already Exist!";
                }
                $res = false;
            }
        }
        if ($res) {
            if ($status == 'Approved') {
                return redirect('/verified_paymentvoucher')
                    ->with('success', 'Payment Vouchers Created successfully!');
            } else {
                return redirect('/pending_paymentvoucher')
                    ->with('success', 'Payment Vouchers Created successfully!');
            }
        } else {
            return redirect('/pending_paymentvoucher')
                ->with('error', $etext . '. Error While Creating Payment Vouchers. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function addsitesBalance(Request $request)
    {
        $id = $request->input('site_id');
        $amount = $request->input('amount');
        $remark = $request->input('remark');
        $data = [
            'site_id' => $id,
            'amount' => $amount,
            'remark' => $remark
        ];

        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        try {
            $pay_id =   DB::connection($user_db_conn_name)->table('site_payments')->insertGetId($data);
            addActivity($pay_id, 'site_payments', "New Site Payment Of Amount -  " . $amount, 1);
            $tdata = [
                'site_id' => $id,
                'type' => 'Credit',
                'payment_id' => $pay_id
            ];
            DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_id', '=', $pay_id)->delete();

            DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
            return redirect('/sites')
                ->with('success', 'Site Balance Credit successfully!');
        } catch (\Exception $e) {
            return redirect('/sites')
                ->with('error', 'Error While Site Balance Credit!');
        }
    }
    public function updatepaymentvouchers(Request $request)
    {
        $ids = $request->input('check_list');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        if ($ids != null) {
            if ($request->input('approve_paymentvoucher') !== null) {
                foreach ($ids as $id) {


                    $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->get()[0];
                    if ($paymentvoucher->party_type == 'bill') {
                        $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'material') {
                        $party_status = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'other') {
                        $party_status = DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    } else if ($paymentvoucher->party_type == 'site') {
                        $party_status = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                    }
                    if ($party_status->status == 'Active') {
                        $this->approve_paymentvoucher($id, $user_db_conn_name);
                        addActivity($id, 'payment_vouchers', "Payment Vouchers Approved", 8);
                    } else {
                        return redirect('/pending_paymentvoucher')
                            ->with('error', $party_status->name . ' Party Is Not Active!');
                    }
                }
                return redirect('/verified_paymentvoucher')
                    ->with('success', 'Payment Voucher Approved successfully!');
            } else if ($request->input('reject_paymentvoucher') !== null) {
                foreach ($ids as $id) {
                    $this->reject_paymentvoucher($id, $user_db_conn_name);
                    addActivity($id, 'payment_vouchers', "Payment Vouchers Rejected", 8);
                }
                return redirect('/pending_paymentvoucher')
                    ->with('success', 'Payment Voucher Rejected successfully!');
            }
        } else {
            return redirect('/pending_paymentvoucher')
                ->with('error', 'Please Choose Atleast One paymentvoucher!');
        }
    }
    public function updateEditpaymentvouchers(Request $request)
    {

        $data = $request->input();
        $user_id = session()->get('uid');
        $role_id = session()->get('role');
        $status = getInitialEntryStatusByRole($role_id);
        $id = $data['id'];
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $id)->get()[0];

        if (isset($request->image)) {
            if (File::exists($paymentvoucher->image) && $paymentvoucher->image != 'images/expense.png') {
                File::delete($paymentvoucher->image);
            }
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/paymentvoucher'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/paymentvoucher/" . $imageName;
        } else {
            $imagePath = $paymentvoucher->image;
        }
        $party = explode("||", $data['party_id']);

        $rawd = [
            'id' => $id,
            'company_id' => $data['company_id'],
            'site_id' => $data['site_id'],
            'party_type' => $party[1],
            'party_id' => $party[0],
            'voucher_no' => $data['voucher_no'],
            'amount' => $data['amount'],
            'date' => $data['date'],
            'payment_details' => $data['payment_details'],
            'remark' => $data['remark'],
            'created_by' => $user_id,
            'image' => $imagePath,
            'status' => $status,
        ];


        try {
            DB::connection($user_db_conn_name)->table('payment_vouchers')->upsert($rawd, 'id');
            addActivity($id, 'payment_vouchers', "Payment Voucher Data Updated ", 8);

            if ($status == 'Approved') {
                $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->get()[0];
                if ($paymentvoucher->party_type == 'bill') {
                    $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                } else if ($paymentvoucher->party_type == 'material') {
                    $party_status = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                } else if ($paymentvoucher->party_type == 'other') {
                    $party_status = DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                } else if ($paymentvoucher->party_type == 'site') {
                    $party_status = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $paymentvoucher->party_id)->get()[0];
                }
                if ($party_status->status == 'Active') {
                    $this->approve_paymentvoucher($id, $user_db_conn_name);
                    addActivity($id, 'payment_vouchers', "Payment Vouchers Approved", 8);
                }
                return redirect('/verified_paymentvoucher')
                    ->with('success', 'Payment Voucher Updated successfully!');
            } else {
                return redirect('/pending_paymentvoucher')
                    ->with('success', 'Payment Voucher Updated successfully!');
            }
        } catch (\Exception $e) {
            if ($e->getCode() == 23000) {
                $etext = "Voucher No Already Exist!";
            }
            return redirect('/pending_paymentvoucher')
                ->with('error', $etext . 'Error While Updating Payment Voucher. Please Try Again After Reconciling The Statement.!');
        }
    }
    public function approve_paymentvoucher_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->get()[0];
        if ($paymentvoucher->party_type == 'bill') {
            $party_status = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $paymentvoucher->party_id)->get()[0];
        } else if ($paymentvoucher->party_type == 'material') {
            $party_status = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $paymentvoucher->party_id)->get()[0];
        } else if ($paymentvoucher->party_type == 'other') {
            $party_status = DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $paymentvoucher->party_id)->get()[0];
        }
        if ($party_status->status == 'Active') {

            $this->approve_paymentvoucher($id, $user_db_conn_name);
            addActivity($id, 'payment_vouchers', "Payment Voucher Approved.", 8);

            return redirect('/pending_paymentvoucher')
                ->with('success', 'Payment Voucher Approved successfully!');
        } else {
            return redirect('/pending_paymentvoucher')
                ->with('error', 'Party Is Not Active!');
        }
    }
    public function reject_paymentvoucher_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $this->reject_paymentvoucher($id, $user_db_conn_name);
        addActivity($id, 'payment_vouchers', "Payment Voucher Rejected.", 8);
        return redirect('/verified_paymentvoucher')
            ->with('success', 'Payment Voucher Rejected successfully!');
    }
    public function reject_Paidpaymentvoucher_by_id(Request $request)
    {
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $id)->get()[0];
        $party_type = $paymentvoucher->party_type;
        $user_id = session()->get('uid');
        try {
            $image_path = $paymentvoucher->payment_image;
            if (File::exists($image_path) && $image_path != 'images/expense.png') {
                File::delete($image_path);
            }
            if ($party_type == 'site') {
                DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_voucher_id', $id)->delete();
            } else if ($party_type == 'bill') {
                DB::connection($user_db_conn_name)->table('bill_party_statement')->where('payment_voucher_id', $id)->delete();
            } else if ($party_type == 'material') {
                DB::connection($user_db_conn_name)->table('material_supplier_statement')->where('payment_voucher_id', $id)->delete();
            } else if ($party_type == 'other') {
                DB::connection($user_db_conn_name)->table('other_party_statement')->where('payment_voucher_id', $id)->delete();
            }
            DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->update(['status' => 'Rejected', 'approved_by' => $user_id]);
            addActivity($id, 'payment_vouchers', "Already Paid Payment Vouchers Rejected", 8);
        } catch (\Exception $e) {
            return redirect('/paid_paymentvoucher')
                ->with('error',   'Error While Rejecting Payment Voucher. Please Try Again After Reconciling The Statement.!');
        }
        return redirect('/paid_paymentvoucher')
            ->with('success',   'Payment Voucher Rejected Successfully!');
    }
    public function approve_paymentvoucher($id, $user_db_conn_name)
    {
        $user_id = session()->get('uid');
        DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->update(['status' => 'Approved', 'approved_by' => $user_id]);
    }


    public function reject_paymentvoucher($id, $user_db_conn_name)
    {
        $user_id = session()->get('uid');
        DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->update(['status' => 'Rejected', 'approved_by' => $user_id]);
    }

    public function addpaymentvoucherpayment(Request $request)
    {
        $id = $request->get('id');
        $payment_details = $request->get('payment_details');
        $payment_date = $request->get('payment_date');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $user_id = session()->get('uid');
        $paymentvoucher = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', $id)->get()[0];
        $party_type = $paymentvoucher->party_type;
        $party_id = $paymentvoucher->party_id;

        if (isset($request->image)) {
            $imageName = time() . rand(10000, 1000000) . '.' . $request->image->extension();
            $request->image->move(public_path('images/app_images/' . $user_db_conn_name . '/paymentvoucher'), $imageName);
            $imagePath = "images/app_images/" . $user_db_conn_name . "/paymentvoucher/" . $imageName;
        } else {
            $imagePath = "images/expense.png";
        }
        try {
            DB::connection($user_db_conn_name)->table('payment_vouchers')->where('id', '=', $id)->update(['status' => 'Paid', 'paid_by' => $user_id, 'payment_details' => $payment_details, 'payment_date' => $payment_date, 'payment_image' => $imagePath]);
            addActivity($id, 'payment_vouchers', "Payment Vouchers Paid", 8);
            if ($party_type == 'site') {
                $tdata = [
                    'site_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($user_db_conn_name)->table('sites_transaction')->where('payment_voucher_id', $id)->delete();
                DB::connection($user_db_conn_name)->table('sites_transaction')->insert($tdata);
            } else if ($party_type == 'bill') {
                $tdata = [
                    'party_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'particular' => $payment_details,
                    'payment_voucher_id' => $id
                ];

                DB::connection($user_db_conn_name)->table('bill_party_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($user_db_conn_name)->table('bill_party_statement')->insert($tdata);
            } else if ($party_type == 'material') {
                $tdata = [
                    'supplier_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($user_db_conn_name)->table('material_supplier_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($user_db_conn_name)->table('material_supplier_statement')->insert($tdata);
            } else if ($party_type == 'other') {
                $tdata = [
                    'party_id' => $paymentvoucher->party_id,
                    'type' => 'Credit',
                    'payment_voucher_id' => $id
                ];
                DB::connection($user_db_conn_name)->table('other_party_statement')->where('payment_voucher_id', $id)->delete();
                DB::connection($user_db_conn_name)->table('other_party_statement')->insert($tdata);
            }
        } catch (\Exception $e) {
            return redirect('/verified_paymentvoucher')
                ->with('error',   'Error While Paying Payment Voucher. Please Try Again After Reconciling The Statement.!');
        }
        return redirect('/verified_paymentvoucher')
            ->with('success',   'Payment Voucher Paid Successfully!');
    }



    public function voucher_pdf(Request $request){
        $id = $request->get('id');
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $payment_voucher = DB::connection($user_db_conn_name)->select("SELECT `payment_vouchers`.*, `sales_company`.`name` as `company_name`, `sites`.`name` as `site_name` FROM `payment_vouchers` LEFT JOIN `sites` ON `payment_vouchers`.`site_id` = `sites`.`id` LEFT JOIN `sales_company` ON `payment_vouchers`.`company_id` = `sales_company`.`id` WHERE `payment_vouchers`.`id` = $id")[0];
        $company = DB::connection($user_db_conn_name)->table('sales_company')->where('id',$payment_voucher->company_id)->first();
        $file_name = $payment_voucher->voucher_no.".pdf";
// return view('layouts.paymentvoucher.pdfs.pv_pdf',compact(['payment_voucher','company']));

        $pdf = Pdf::loadView('layouts.paymentvoucher.pdfs.pv_pdf', compact(['payment_voucher','company']));
        return $pdf->download($file_name);
    }


    public function payment_report(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $material_suppliers = DB::connection($user_db_conn_name)->table('material_supplier')->get();
        $sites = DB::connection($user_db_conn_name)->table('sites')->get();
        $bill_parties = DB::connection($user_db_conn_name)->table('bills_party')->get();
        $other_parties = DB::connection($user_db_conn_name)->table('other_parties')->get();
        $parties = array();
        foreach ($material_suppliers as $ms) {
            $party = [
                'id' => $ms->id,
                'type' => "material",
                'name' => "Material S. - ".$ms->name
            ];
            array_push($parties, $party);
        }

        foreach ($bill_parties as $bp) {
            $party = [
                'id' => $bp->id,
                'type' => "bill",
                'name' => "Bill P. - ".$bp->name
            ];
            array_push($parties, $party);
        }

        foreach ($other_parties as $op) {
            $party = [
                'id' => $op->id,
                'type' => "other",
                'name' => "Other P. - ".$op->name
            ];
            array_push($parties, $party);
        }

        foreach ($sites as $site) {
            $party = [
                'id' => $site->id,
                'type' => "site",
                'name' => "Site - ".$site->name
            ];
            array_push($parties, $party);
        }

        return view('layouts.paymentvoucher.paymentvoucher_report', compact('parties'));
    }
    public function paymentvoucherreport(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $type = $request->get('Report_Type');
        $report_code = $request->get('type');
        if ($report_code == 2 || $report_code == 4) {
            $partydetail = explode('||', $request->get('party_id'));
            $partyname = $partydetail[0];
            $partytype = $partydetail[1];
        }

        $start_date = $request->get('start_date');
        $sitename = $request->get('site_id');
        $end_date = $request->get('end_date');
        addActivity(0, 'payment_vouchers', "Payment Voucher Report Generated Of Data (" . $start_date . " - " . $end_date . ")", 8);
        if ($report_code == 1) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code);
            } else {
                $file_name = "Payment Voucher Report (" . $start_date . " - " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('payment_vouchers as pv')
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

                    ->selectRaw('pv.*,sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->whereBetween('pv.date', [$start_date, $end_date])
                    ->orderBy('pv.date', 'desc')->get();
                $pdf = Pdf::loadView('layouts.paymentvoucher.pdfs.accToDate', compact(['data', 'start_date', 'end_date']));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 2) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, "", $partyname, $partytype);
            } else {
                $file_name = "Payment Voucher Report By Party (" . $start_date . " - " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('payment_vouchers as pv')
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

                    ->selectRaw('pv.*,sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.party_id', $partyname)
                    ->where('pv.party_type', $partytype)
                    ->whereBetween('pv.date', [$start_date, $end_date])
                    ->orderBy('pv.date', 'desc')->get();
                    $partyname = getPaymentVoucherPartyInfo($partyname,$partytype);
                $pdf = Pdf::loadView('layouts.paymentvoucher.pdfs.accToParty', compact(['data', 'start_date', 'end_date','partyname']));
                return $pdf->download($file_name);
            }
        } else  if ($report_code == 3) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename,"","");
            } else {
                $file_name = "Payment Voucher Report By Site (" . $start_date . " - " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('payment_vouchers as pv')
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

                    ->selectRaw('pv.*,sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.site_id', $sitename)            
                    ->whereBetween('pv.date', [$start_date, $end_date])
                    ->orderBy('pv.date', 'desc')->get();
                    $sitename = getSiteDetailsById($sitename)->name;
                $pdf = Pdf::loadView('layouts.paymentvoucher.pdfs.accToSite', compact(['data', 'start_date', 'end_date','sitename']));
                return $pdf->download($file_name);
            }
        }
        else  if ($report_code == 4) {
            if ($type == 1) {
                return $this->exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename,$partyname,$partytype);
            } else {
                $file_name = "Payment Voucher Report By Site (" . $start_date . " - " . $end_date . ").pdf";
                $data = DB::connection($user_db_conn_name)
                    ->table('payment_vouchers as pv')
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

                    ->selectRaw('pv.*,sc.name as company_name,vs.name as site_name,cu.name as created_user,au.name as approved_user,pu.name as paid_user, CASE WHEN pv.party_type = "bill" THEN bills_party.name WHEN pv.party_type = "material" THEN material_supplier.name WHEN pv.party_type = "other" THEN other_parties.name WHEN pv.party_type = "site" THEN ps.name END AS party_name')
                    ->where('pv.site_id', $sitename)         
                    ->where('pv.party_id', $partyname)
                    ->where('pv.party_type', $partytype)  
                    ->whereBetween('pv.date', [$start_date, $end_date])
                    ->orderBy('pv.date', 'desc')->get();
                    $sitename = getSiteDetailsById($sitename)->name;
                    $partyname = getPaymentVoucherPartyInfo($partyname,$partytype);
                $pdf = Pdf::loadView('layouts.paymentvoucher.pdfs.accToPartyAtSite', compact(['data', 'start_date', 'end_date','sitename','partyname']));
                return $pdf->download($file_name);
            }
        }
    }

    public function exportExcel($user_db_conn_name, $start_date, $end_date, $report_code, $sitename = null, $partyname = null, $partytype = null)
    {

        $file_name = "Payment Voucher ";

        if ($report_code == 1) {
            $file_name .= "Date Report ";
        } else if ($report_code == 2) {
            $file_name .= "Report By Party ";
        } else if ($report_code == 3) {

            $file_name .= "Report By Site ";
        } else if ($report_code == 4) {
            $file_name .= "Report By Party At Particular Site ";
        }

        $file_name .= "(" . $start_date . " TO " . $end_date . ").xlsx";

        return Excel::download(new PaymentExport($user_db_conn_name, $start_date, $end_date, $report_code, $sitename,  $partyname, $partytype), $file_name);
    }
}
