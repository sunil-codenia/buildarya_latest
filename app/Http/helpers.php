<?php

use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;


function getFinancialYearByDate($date)
{
    $timestamp = strtotime($date);
    $year = date('Y', $timestamp);
    $month = date('m', $timestamp);

    if ((int)$month >= 4) {
        // From April to December, financial year starts from current year
        $startYear = $year;
        $endYear = $year + 1;
    } else {
        // From January to March, financial year starts from previous year
        $startYear = $year - 1;
        $endYear = $year;
    }

    return $startYear . '-' . $endYear;
}

function getSiteDetailsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $sites = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->get()[0];
    return $sites;
}
function getRoleDetailsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $roles = DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->get()[0];
    return $roles;
}

function getSalesProjects($id = null)
{
    if ($id == null) {
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $projects = DB::connection($user_db_conn_name)->table('sales_project')->get();
        return $projects;
    } else {
        $user_db_conn_name = session()->get('comp_db_conn_name');
        $projects = DB::connection($user_db_conn_name)->table('sales_project')->where('id', $id)->get()[0];
        return $projects;
    }
}
function getSalesInvoiceBalance($id)
{

    $user_db_conn_name = session()->get('comp_db_conn_name');
    $invoice = DB::connection($user_db_conn_name)->table('sales_invoice')->where('id', '=', $id)->first();
    $amount = $invoice->amount;
    $manage = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->leftJoin('sales_dedadd', 'sales_dedadd.id', '=', 'sales_manage_invoice.type_id')->where('sales_manage_invoice.invoice_id', '=', $id)->select('sales_manage_invoice.*', 'sales_dedadd.name as type_name', 'sales_dedadd.type as type')->get();
    $credit = 0;
    $debit = 0;
    foreach ($manage as $mng) {
        if ($mng->type == 'add') {
            $debit += $mng->amount;
        } else {
            $credit += $mng->amount;
        }
    }
    $balance  = $amount + $debit - $credit;
    return $balance;
}

function getUserDetailsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $users = DB::connection($user_db_conn_name)->table('users')->where('id', '=', $id)->get()[0];
    return $users;
}


function getallRoles()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $roles = DB::connection($user_db_conn_name)->table('roles')->get();
    return $roles;
}
function getallworkslist()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $works = DB::connection($user_db_conn_name)->table('bills_work')->get();
    return $works;
}
function getWorkDetailsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $work = DB::connection($user_db_conn_name)->table('bills_work')->where('id', '=', $id)->get()[0];
    return $work;
}
function getallsites()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $sites = DB::connection($user_db_conn_name)->table('sites')->get();
    return $sites;
}
function getallmaterialsupplier()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $suppliers = DB::connection($user_db_conn_name)->table('material_supplier')->get();
    return $suppliers;
}
function getallmaterial()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $materials = DB::connection($user_db_conn_name)->table('materials')->get();
    return $materials;
}
function getallActivesites()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $sites = DB::connection($user_db_conn_name)->table('sites')->where('status', '=', 'Active')->get();
    return $sites;
}
function getallbillparties()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $parties = DB::connection($user_db_conn_name)->table('bills_party')->get();
    return $parties;
}
function getallexpenseparties()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $parties = DB::connection($user_db_conn_name)->table('expense_party')->get();
    return $parties;
}
function getallexpenseheads()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $heads = DB::connection($user_db_conn_name)->table('expense_head')->get();
    return $heads;
}
function getallMachineryHeads()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $heads = DB::connection($user_db_conn_name)->table('machinery_head')->get();

    return $heads;
}

function getallAssetHeads()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $heads = DB::connection($user_db_conn_name)->table('asset_head')->get();

    return $heads;
}

function getAssetHeadsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return DB::connection($user_db_conn_name)->table('asset_head')->where('id', '=', $id)->first();
}

function getMachineryHeadsById($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return DB::connection($user_db_conn_name)->table('machinery_head')->where('id', '=', $id)->first();
}

function getcompanyModules()
{
    $comp_id = session()->get('comp_db_id');
    $modules = DB::table('company_modules')->join('modules', 'modules.id', '=', 'company_modules.module_id')->select('modules.id', 'modules.name')->where('company_modules.company_id', '=', $comp_id)->get();
    return $modules;
}
function getcompanyModulesName($id)
{
    $comp_id = session()->get('comp_db_id');
    $modules = DB::table('modules')->where('id', '=', $id)->get()[0]->name;
    return $modules;
}
function getViewDurationByRole($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $duration = DB::connection($user_db_conn_name)->table('roles')->select('view_duration')->where('id', $id)->get();
    return $duration[0]->view_duration;
}

function getAddDurationByRole($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $duration = DB::connection($user_db_conn_name)->table('roles')->select('add_duration')->where('id', $id)->get();
    return $duration[0]->add_duration;
}
function getInitialEntryStatusByRole($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $duration = DB::connection($user_db_conn_name)->table('roles')->select('initial_entry_status')->where('id', $id)->get();
    return $duration[0]->initial_entry_status;
}

function getAccessSitesByRole($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $duration = DB::connection($user_db_conn_name)->table('roles')->select('entry_at_site')->where('id', $id)->get();
    return $duration[0]->entry_at_site;
}

function checkmodulepermission($module_id, $permission)
{
    if (session()->get('role_perms_set') === false) {
        return 1;
    }
    
    $perm = session()->get('permissions')[0];
    if (!isset($perm[$module_id][$permission])) {
        return 0;
    }
    $res  = $perm[$module_id][$permission];
    return $res;
}

function isSuperAdmin()
{
    return session()->get('is_superadmin') == 'yes';
}

function canViewModule($module_id)
{
    if (isSuperAdmin()) {
        return true;
    }
    return checkmodulepermission($module_id, 'can_view') == 1;
}

// app function start----------------------------------------------------------------
function getAppDataAccessByRole($id, $conn)
{

    $data = DB::connection($conn)->table('roles')->select('data_shown_in_app')->where('id', $id)->get();
    return $data[0]->data_shown_in_app;
}
function getAppViewDurationByRole($id, $conn)
{
    $data = DB::connection($conn)->table('roles')->select('view_duration_in_app')->where('id', $id)->get();
    return $data[0]->view_duration_in_app;
}
function getAppRoleByUId($id, $conn)
{
    $users = DB::connection($conn)->table('users')->select('role_id')->where('id', '=', $id)->get();
    return $users[0]->role_id;
}
function getAppInitialEntryStatusByRole($id, $conn)
{

    $duration = DB::connection($conn)->table('roles')->select('initial_entry_status')->where('id', '=', $id)->get();
    return $duration[0]->initial_entry_status;
}
function getAppDataAccess($id = null)
{
    $data = [
        'self' => 'User Data',
        'current' => 'Assigned Site Only',
        'all' => 'All Sites',
    ];
    if ($id != null) {
        return $data[$id];
    } else {
        return $data;
    }
}
function getAppSiteDetailsById($id, $user_db_conn_name)
{
    $sites = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->get()[0];
    return $sites;
}
function getAppRoleDetailsById($id, $user_db_conn_name)
{
    $roles = DB::connection($user_db_conn_name)->table('roles')->where('id', '=', $id)->get()[0];
    return $roles;
}



// app function ends----------------------------------------------------------------

function getviewdurations($id = null)
{
    $data = [
        '1m' => 'Last Month',
        '3m' => 'Last 3 Months',
        '6m' => 'Last 6 Months',
        '12m' => 'Last 12 Months',
        'complete' => 'Complete Data',
    ];
    if ($id != null) {
        return $data[$id];
    } else {
        return $data;
    }
}
function getadddurations($id = null)
{
    $data = [
        'current' => 'Current Date Only',
        '1m' => 'Last Month',
        '3m' => 'Last 3 Months',
        '6m' => 'Last 6 Months',
        '12m' => 'Last 12 Months',
        'anytime' => 'No Date Boundation',
    ];
    if ($id != null) {
        return $data[$id];
    } else {
        return $data;
    }
}
function getInvoiceHeads($id = null)
{
    $data = [
        'add' => 'Addition',
        'ded' => 'Deduction',
    ];
    if ($id !== null) {
        return $data[$id];
    } else {
        return $data;
    }
}
function getsiteEntryAccess($id = null)
{
    $data = [
        'current' => 'Assigned Site Only',
        'all' => 'All Sites',
    ];
    if ($id != null) {
        return $data[$id];
    } else {
        return $data;
    }
}
function getFinancialYear()
{
    $data = [
        1 => '2012-2013',
        2 => '2013-2014',
        3 => '2014-2015',
        4 => '2015-2016',
        5 => '2016-2017',
        6 => '2017-2018',
        7 => '2018-2019',
        8 => '2019-2020',
        9 => '2020-2021',
        10 => '2021-2022',
        11 => '2022-2023',
        12 => '2023-2024',
        13 => '2024-2025',
        14 => '2025-2026',
        15 => '2026-2027',
        16 => '2027-2028',
        17 => '2028-2029',
        18 => '2029-2030'
    ];
    return $data;
}

function getCurrentFinancialYear()
{
    date_default_timezone_set("Asia/Kolkata");
    if (date('m') <= 3) {
        $financial_year = (date('Y') - 1) . '-' . date('Y');
    } else {
        $financial_year = date('Y') . '-' . (date('Y') + 1);
    }
    return $financial_year;
}
function get_dashboard_filter_dates($type = 'this_year', $from = null, $to = null)
{
    date_default_timezone_set("Asia/Kolkata");
    $start = null;
    $end = Carbon::now()->endOfDay();

    switch ($type) {
        case 'today':
            $start = Carbon::today()->startOfDay();
            break;
        case 'this_week':
            $start = Carbon::now()->startOfWeek();
            break;
        case 'this_year':
            $start = Carbon::now()->startOfYear();
            $end = Carbon::now()->endOfYear();
            break;
        case 'all_time':
            $start = Carbon::create(2000, 1, 1)->startOfDay();
            $end = Carbon::create(2099, 12, 31)->endOfDay();
            break;
        case 'custom':
            if ($from) $start = Carbon::parse($from)->startOfDay();
            if ($to) $end = Carbon::parse($to)->endOfDay();
            break;
        case 'this_month':
        default:
            $start = Carbon::now()->startOfMonth();
            break;
    }
    
    return [
        'start' => $start ? $start->toDateTimeString() : null, 
        'end' => $end ? $end->toDateTimeString() : null
    ];
}

function getdurationdates($id = null)
{
    date_default_timezone_set("Asia/Kolkata");
    $today_start = date('Y-m-d 00:00:00');
    $today = date('Y-m-d 23:59:59');
    switch ($id) {
        case 'current':
            $min = $today_start;
            $max = $today;
            break;
        case '1m':
            $min = date('Y-m-d 00:00:00', strtotime($today . ' -1 months'));
            $max = $today;
            break;
        case '3m':
            $min = date('Y-m-d 00:00:00', strtotime($today . ' -3 months'));
            $max = $today;
            break;
        case '6m':
            $min = date('Y-m-d 00:00:00', strtotime($today . ' -6 months'));
            $max = $today;
            break;
        case '12m':
            $min = date('Y-m-d 00:00:00', strtotime($today . ' -12 months'));
            $max = $today;
            break;
        case 'complete':
            $min = '0001-01-01 00:00:00';
            $max = '2100-01-01 23:59:59';
            break;
        case 'anytime':
            $min = '0001-01-01 00:00:00';
            $max = '2100-01-01 23:59:59';
            break;
        default:
            $min = '0001-01-01 00:00:00';
            $max = '2100-01-01 23:59:59';
            break;
    }
    $data = [
        'today' => $today_start,
        'min' => $min,
        'max' => $max
    ];
    return $data;
}

function getStructuredAmount($amount, $with_currency, $with_sub)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $currency = DB::connection($user_db_conn_name)->table('settings')->select('value')->where('name', 'currency')->get()[0]->value;

    $temp_amount = 0;
    $f = $amount * 1.0;
    if ($with_sub) {
        if ($f >= 10000000.0) {
            $temp_amount = number_format($f / 10000000, 2) . "Cr";
        } else if ($f >= 100000.0) {
            $temp_amount = number_format($f / 100000, 2) . "L";
        } else if ($f >= 1000.0) {
            $temp_amount = number_format($f / 1000, 2) . "K";
        } else {
            $temp_amount =  number_format($f, 2);
        }
    } else {
        $temp_amount = number_format($f, 2);
    }
    if ($with_currency) {
        return $currency . " " . $temp_amount;
    } else {
        return $temp_amount;
    }
}

function getStatusColor($status)
{
    $color = "";
    switch ($status) {
        case 'Pending':
            $color = "orange";
            break;
        case "Approved":
            $color = "green";
            break;
        case "Rejected":
            $color = "red";
            break;
        default:
            $color = "yellow";
    }
    return $color;
}
function getPaymentVoucherPartyInfo($id, $type)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = array();
    if ($type == 'bill') {
        $data['type'] = 'Bill Party';
        $data['party_status'] = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->get()[0];
    } else if ($type == 'material') {
        $data['type'] = 'Material Supplier';
        $data['party_status'] = DB::connection($user_db_conn_name)->table('material_supplier')->where('id', '=', $id)->get()[0];
    } else if ($type == 'other') {
        $data['type'] = 'Other Party';
        $data['party_status'] = DB::connection($user_db_conn_name)->table('other_parties')->where('id', '=', $id)->get()[0];
    } else if ($type == 'site') {
        $data['type'] = 'Site';
        $data['party_status'] = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->get()[0];
    }

    return $data;
}
function getPaymentVoucherPartyBalance($id,$type){

    if ($type == 'bill') {
       
        $balance = getBillPartyBalance($id);
    } else if ($type == 'material') {

        $balance = getMaterialsSupplierBalance($id);
    } else if ($type == 'other') {

        $balance = getOtherPartyBalance($id);
    } else if ($type == 'site') {

        $balance = getSiteBalance($id);
    }
    return $balance;
}
function isUserDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $expense_entry = DB::connection($user_db_conn_name)->table('expenses')->where('user_id', '=', $id)->get();
    $material_entry = DB::connection($user_db_conn_name)->table('material_entry')->where('user_id', '=', $id)->get();
    $new_bill_entry = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('user_id', '=', $id)->get();
    $machinery_services = DB::connection($user_db_conn_name)->table('machinery_services')->where('user_id', '=', $id)->get();
    $payment_vouchers = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('created_by', '=', $id)->get();
    if (count($expense_entry) > 0 || count($material_entry) > 0 || count($new_bill_entry) > 0 || count($machinery_services) > 0 || count($payment_vouchers) > 0) {
        $result = false;
    }
    return $result;
}
function isSiteDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    if (DB::connection($user_db_conn_name)->table('assets')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('bills_rate')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('expenses')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('expense_party')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('machinery_details')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('material_entry')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('sites_transaction')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('site_payments')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('users')->where('site_id', '=', $id)->exists()) return false;
    if (DB::connection($user_db_conn_name)->table('payment_vouchers')->where('site_id', '=', $id)->exists()) return false;
    
    return true;
}
function isExpenseHeadDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return !DB::connection($user_db_conn_name)->table('expenses')
        ->where('head_id', '=', $id)
        ->exists();
}
function isExpensepartyDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return !DB::connection($user_db_conn_name)->table('expenses')
        ->where('party_type', '=', 'expense')
        ->where('party_id', '=', $id)
        ->exists();
}
function isRoleDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('users')->where('role_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isSaleCompanyDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('sales_invoice')->where('company_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isOtherPartyDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('party_id', '=', $id)->where('party_type', '=', 'other')->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isInvoiceHeadDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('type_id', '=', $id)->get();
    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isSalesPartyDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('sales_invoice')->where('party_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isSalesProjectDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isSalesInvoiceDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->where('invoice_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isBillPartyDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check1 = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('party_id', '=', $id)->get();
    $check2 = DB::connection($user_db_conn_name)->table('expenses')->where('party_type', '=', 'bill')->where('party_id', '=', $id)->get();


    if (count($check1) > 0 || count($check2) > 0) {
        $result = false;
    }
    return $result;
}
function isBillWorkDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;

    $check = DB::connection($user_db_conn_name)->table('new_bills_item_entry')->where('work_id', '=', $id)->get();

    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isMaterialSupplierDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return !DB::connection($user_db_conn_name)->table('material_entry')
        ->where('supplier', '=', $id)
        ->exists();
}
function isMaterialDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $result = true;
    $check = DB::connection($user_db_conn_name)->table('material_entry')->where('material_id', '=', $id)->get();
    if (count($check) > 0) {
        $result = false;
    }
    return $result;
}
function isMaterialUnitDeletable($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    return !DB::connection($user_db_conn_name)->table('material_entry')
        ->where('unit', '=', $id)
        ->exists();
}
function getSiteBalance($id, $to_date = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $expenseQuery = DB::connection($user_db_conn_name)->table('sites_transaction')->join('expenses', 'expenses.id', '=', 'sites_transaction.expense_id')->where('sites_transaction.site_id', '=', $id)->where('sites_transaction.type', '=', 'Debit');
    $paymentOutQuery = DB::connection($user_db_conn_name)->table('sites_transaction')->join('site_payments', 'site_payments.id', '=', 'sites_transaction.payment_id')->where('sites_transaction.site_id', '=', $id)->where('sites_transaction.type', '=', 'Debit')->whereNotNull('sites_transaction.payment_id');
    $paymentInQuery = DB::connection($user_db_conn_name)->table('sites_transaction')->join('site_payments', 'site_payments.id', '=', 'sites_transaction.payment_id')->where('sites_transaction.site_id', '=', $id)->where('sites_transaction.type', '=', 'Credit')->whereNotNull('sites_transaction.payment_id');
    $voucherQuery = DB::connection($user_db_conn_name)->table('sites_transaction')->join('payment_vouchers', 'payment_vouchers.id', '=', 'sites_transaction.payment_voucher_id')->where('sites_transaction.site_id', '=', $id)->where('sites_transaction.type', '=', 'Credit')->whereNotNull('sites_transaction.payment_voucher_id');

    if ($to_date) {
        $expenseQuery->where('expenses.date', '<=', $to_date);
        $paymentOutQuery->where('site_payments.date', '<=', $to_date);
        $paymentInQuery->where('site_payments.date', '<=', $to_date);
        $voucherQuery->where('payment_vouchers.date', '<=', $to_date);
    }

    $debit1 = $expenseQuery->sum('expenses.amount');
    $debit2 = $paymentOutQuery->sum('site_payments.amount');
    $credit1 = $paymentInQuery->sum('site_payments.amount');
    $credit2 = $voucherQuery->sum('payment_vouchers.amount');

    $credit = $credit1 + $credit2;
    $debit = $debit1 + $debit2;
    $balance = $credit - $debit;
    return $balance;
}
function getBillPartyBalance($id, $user_db_conn_name = null)
{
    if ($user_db_conn_name == null) {
        $user_db_conn_name = session()->get('comp_db_conn_name');
    }
    $debit1 = DB::connection($user_db_conn_name)->table('bill_party_statement')->join('new_bill_entry', 'new_bill_entry.id', '=', 'bill_party_statement.bill_no')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Debit')->sum('new_bill_entry.amount');
    $debit2 = DB::connection($user_db_conn_name)->table('bill_party_statement')->join('bill_party_payments', 'bill_party_payments.id', '=', 'bill_party_statement.payment_id')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Debit')->sum('bill_party_payments.amount');
    $credit1 = DB::connection($user_db_conn_name)->table('bill_party_statement')->join('expenses', 'expenses.id', '=', 'bill_party_statement.expense_id')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Credit')->whereNotNull('bill_party_statement.expense_id')->sum('expenses.amount');
    $credit2 = DB::connection($user_db_conn_name)->table('bill_party_statement')->join('bill_party_payments', 'bill_party_payments.id', '=', 'bill_party_statement.payment_id')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Credit')->whereNotNull('bill_party_statement.payment_id')->sum('bill_party_payments.amount');
    $credit3 = DB::connection($user_db_conn_name)->table('bill_party_statement')->join('payment_vouchers', 'payment_vouchers.id', '=', 'bill_party_statement.payment_voucher_id')->where('bill_party_statement.party_id', '=', $id)->where('bill_party_statement.type', '=', 'Credit')->whereNotNull('bill_party_statement.payment_voucher_id')->sum('payment_vouchers.amount');
    $credit = $credit1 + $credit2 + $credit3;
    $debit = $debit1 + $debit2;
    $balance = $debit - $credit;
    return $balance;
}
function getMaterialsSupplierBalance($id){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $debit = DB::connection($user_db_conn_name)->table('material_supplier_statement')->join('material_entry', 'material_entry.id', '=', 'material_supplier_statement.entry_id')->where('material_supplier_statement.supplier_id', '=', $id)->where('material_supplier_statement.type', '=', 'Debit')->whereNotNull('material_supplier_statement.entry_id')->sum('material_entry.amount');

    $credit = DB::connection($user_db_conn_name)->table('material_supplier_statement')->join('payment_vouchers', 'payment_vouchers.id', '=', 'material_supplier_statement.payment_voucher_id')->where('material_supplier_statement.supplier_id', '=', $id)->where('material_supplier_statement.type', '=', 'Credit')->whereNotNull('material_supplier_statement.payment_voucher_id')->sum('payment_vouchers.amount');
   
    $balance = $credit - $debit;
    return $balance;
}
function getOtherPartyBalance($id){
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $credit = DB::connection($user_db_conn_name)->table('other_party_statement')->join('payment_vouchers', 'payment_vouchers.id', '=', 'other_party_statement.payment_voucher_id')->where('other_party_statement.party_id', '=', $id)->where('other_party_statement.type', '=', 'Credit')->whereNotNull('other_party_statement.payment_voucher_id')->sum('payment_vouchers.amount');
   
    return $credit;
}
function getExpensePartyInfo($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $expense_party = DB::connection($user_db_conn_name)->table('expense_party')->where('id', '=', $id)->First();
    return $expense_party;
}
function getExpensePartyNameByPartyType($id, $partytype)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');

    if ($partytype == 'expense') {
        $party = DB::connection($user_db_conn_name)->table('expense_party')->where('id', $id)->first();
    } else {
        $party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', $id)->first();
    }

    return $party ? $party->name : '';
}
function getBillPartyInfo($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $expense_party = DB::connection($user_db_conn_name)->table('bills_party')->where('id', '=', $id)->First();
    return $expense_party;
}
function getLatestBillNo()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $sequence = DB::connection($user_db_conn_name)->table('settings')->where('name', '=', 'bill_sequence')->First()->value;
    $lastbill = DB::connection($user_db_conn_name)->table('new_bill_entry')->orderBy('id', 'desc')->First();


    if (!empty($lastbill) || $lastbill > 0) {
        $lastbill = $lastbill->id;
    } else {
        $lastbill =  0;
    }

    $newbillno = $sequence . ($lastbill + 1);

    return $newbillno;
}

function getLatestBillNoForApp($user_db_conn_name)
{
    $sequence = DB::connection($user_db_conn_name)->table('settings')->where('name', '=', 'bill_sequence')->First()->value;
    $lastbill = DB::connection($user_db_conn_name)->table('new_bill_entry')->orderBy('id', 'desc')->First();
    if (!empty($lastbill) || $lastbill > 0) {
        $lastbill = $lastbill->id;
    } else {
        $lastbill =  0;
    }
    $newbillno = $sequence . ($lastbill + 1);
    return $newbillno;
}
function getLatestPaymentVoucherNo()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $sequence = DB::connection($user_db_conn_name)->table('settings')->where('name', '=', 'payment_voucher_sequence')->First()->value;
    $lastbill = DB::connection($user_db_conn_name)->table('payment_vouchers')->orderBy('id', 'desc')->First();
    if (!empty($lastbill) || $lastbill > 0) {
        $lastbill = $lastbill->id;
    } else {
        $lastbill =  0;
    }
    $newbillno = $sequence . ($lastbill + 1);
    return $newbillno;
}
function getAssetHeadUsageCount($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $role_id = session()->get('role');
    $site_id = session()->get('site_id');
    $role_details = getRoleDetailsById($role_id);
    $visiblity_at_site = $role_details->visiblity_at_site;

    if ($visiblity_at_site == 'current') {
        $filters = [['assets.head_id', '=', $id], ['assets.site_id', '=', $site_id]];
    } else {
        $filters = [['assets.head_id', '=', $id]];
    }
    $count = DB::connection($user_db_conn_name)->table('assets')->where($filters)->count('id');
    return $count;
}
function getMachineryHeadUsageCount($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $role_id = session()->get('role');
    $site_id = session()->get('site_id');
    $role_details = getRoleDetailsById($role_id);
    $visiblity_at_site = $role_details->visiblity_at_site;

    if ($visiblity_at_site == 'current') {
        $filters = [['machinery_details.head_id', '=', $id], ['machinery_details.site_id', '=', $site_id]];
    } else {
        $filters = [['machinery_details.head_id', '=', $id]];
    }
    $count = DB::connection($user_db_conn_name)->table('machinery_details')->where($filters)->count('id');
    return $count;
}
function is_asset_head($id, $user_db_conn_name = null)
{
    if ($user_db_conn_name == null) {
        $user_db_conn_name = session()->get('comp_db_conn_name');
    }
    $head = DB::connection($user_db_conn_name)->table('assets_expense_head')->where('head_id', '=', $id)->get();
    if (count($head) > 0) {
        return true;
    } else {
        return false;
    }
}
function is_machinery_head($id, $user_db_conn_name = null)
{
    if ($user_db_conn_name == null) {
        $user_db_conn_name = session()->get('comp_db_conn_name');
    }

    $head = DB::connection($user_db_conn_name)->table('machinery_expense_head')->where('head_id', '=', $id)->get();
    if (count($head) > 0) {
        return true;
    } else {
        return false;
    }
}
function getAssetHeads()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('asset_head')->get();
    return $data;
}

function getMachineryHeads()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('machinery_head')->get();
    return $data;
}
function getAssetLastTransfer($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('asset_transaction')->where('asset_id', '=', $id)->orderBy('id', 'desc')->first();
    return $data;
}
function getmachineryLastTransfer($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('machinery_transaction')->where('machinery_id', '=', $id)->orderBy('id', 'desc')->first();
    return $data;
}
function getMachineryNextService($id)
{

    $user_db_conn_name = session()->get('comp_db_conn_name');
    $data = DB::connection($user_db_conn_name)->table('machinery_services')->where('machinery_id', '=', $id)->orderBy('id', 'desc')->limit(1)->get();
    $res = "";
    if (count($data) > 0) {
        $res = $data[0]->next_service_on;
    }
    return $res;
}


// firebase code start



function sendAlertNotification($user_id, $msg, $title)
{
    //   $access_token = $this->generateAccessToken();
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $fcm_code =  DB::connection($user_db_conn_name)->table('users')->where('id', '=', $user_id)->get()[0]->fcm_id;

    try {
        $access_token = sendPushNotification();
        $response = Http::withHeaders([

            'Content-type' => 'application/json',
            'Authorization' => 'Bearer ' . $access_token
        ])->post('https://fcm.googleapis.com/v1/projects/constructionmunshi/messages:send', [
            'message' => [
                'token' => $fcm_code,
                'notification' => [
                    'title' => $title,
                    'body' => $msg

                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'alert.mp3'
                        ],
                    ],
                ]
            ]
        ]);
    } catch (\Exception $e) {
        // Log or ignore notification failure to prevent crashing main process
    }
}



function sendPushNotification()
{

    $credentialsFilePath = base_path("constructionmunshi-firebase-adminsdk-9ibpz-ec7993929c.json");
    $client = new \Google_Client();
    $client->setAuthConfig($credentialsFilePath);
    $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
    $apiurl = 'https://fcm.googleapis.com/v1/projects/constructionmunshi/messages:send';
    $client->refreshTokenWithAssertion();
    $token = $client->getAccessToken();
    $access_token = $token['access_token'];
    return $access_token;
}
// firebase code ends



function attachmentUrl($path)
{
    return url($path);
}

//document module functions

function getDocHeadOptions($id, $string = false)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $option = DB::connection($user_db_conn_name)->table('doc_head_option')->where('head_id', $id)->get();
    if ($string) {
        $res = "";
        $count = 0;
        foreach ($option as $opt) {
            $res .= $opt->name;
            $count++;
            if ($count != count($option)) {
                $res .= ", ";
            }
        }
        return $res;
    }
    return $option;
}
function getNoOfContactInCompanyProfile($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $count = DB::connection($user_db_conn_name)->table('contact')->where('profile_id', $id)->count();
    return $count;
}
function getContactCategories()
{
    $data = ['Expense Party', 'Material Supplier', 'Bills Party', 'Employees', 'Government Officials', 'Consultants', 'Legal Advisors', 'Officers', 'Other Party'];
    return $data;
}
function addActivity($ref_id, $ref_table, $action, $module_id, $uid = null, $conn = null)
{

    if ($conn == null) {
        $conn = session()->get('comp_db_conn_name');
    }
    if ($uid == null) {
        $uid = session()->get('uid');
    }
    $current = Carbon::now('Asia/Kolkata');
    $currentDate = $current->format('Y-m-d');
    $currentTime = $current->format('h:i A');
    $data = [
        'uid' => $uid,
        'ref_id' => $ref_id,
        'ref_table' => $ref_table,
        'action' => $action,
        'module_id' => $module_id,
        'date' => $currentDate,
        'time' => $currentTime,
    ];
    DB::connection($conn)->table('activity')->insert($data);
}
function formatSizeUnits($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

function getNoOfFilesByHeadId($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    // $meta = DB::connection($user_db_conn_name)->table('doc_meta')->select('doc_id')->where('head_id', $id)->get();
    $count = DB::connection($user_db_conn_name)
    ->table('doc_upload')
    ->where('status', 'Approved')
    ->whereIn('id', function($query) use ($id) {
        $query->select('doc_id')
              ->from('doc_meta')
              ->where('head_id', $id);
    })
    ->count();
    return $count;
}

// widgets helper functions start
function get_site_balance_data_widget($site_id, $to_date = null)
{
    $siteBalance = getStructuredAmount(getSiteBalance($site_id, $to_date), true, true);
    return $siteBalance;
}
function get_monthly_expense_data_widget($id, $from = null, $to = null)
{
    date_default_timezone_set('Asia/Kolkata');
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
        ->where('site_id', $id)
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('date', [$from, $to]);
    } else {
        $currentMonth = Carbon::now()->format('Y-m');
        $query->whereRaw("DATE_FORMAT(`date`, '%Y-%m') = ?", [$currentMonth]);
    }

    $monthExpense = $query->sum('amount');
    return getStructuredAmount($monthExpense, true, true);
}
function get_company_monthly_expense_data_widget($from = null, $to = null)
{
    date_default_timezone_set('Asia/Kolkata');
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('date', [$from, $to]);
    } else {
        $currentMonth = Carbon::now()->format('Y-m');
        $query->whereRaw("DATE_FORMAT(`date`, '%Y-%m') = ?", [$currentMonth]);
    }

    $monthExpense = $query->sum('amount');
    return getStructuredAmount($monthExpense, true, true);
}

function get_employee_on_site_data_widget($id)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $siteWorking = DB::connection($user_db_conn_name)->table('users')->where('site_id', $id)->where('status', 'Active')->count();
    return $siteWorking;
}
function get_total_employee_data_widget()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $siteWorking = DB::connection($user_db_conn_name)->table('users')->where('status', 'Active')->count();
    return $siteWorking;
}
function get_total_sites_data_widget()
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    $siteWorking = DB::connection($user_db_conn_name)->table('sites')->where('status', 'Active')->where('sites_type', 'Working Site')->count();
    return $siteWorking;
}
function get_site_expense_area_chart_widget($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');

    $currentMonth = Carbon::now()->format('Y-m');
    $currentYear = Carbon::now()->year;
    $today = Carbon::now()->format('Y-m-d');
    $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
    $endOfWeek = Carbon::now()->endOfWeek()->format('Y-m-d');

    $monthExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->whereRaw("DATE_FORMAT(`date`, '%Y-%m') = ?", [$currentMonth])->sum('amount'), true, true);
    $todayExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->where('date', $today)->sum('amount'), true, true);
    $yearExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->whereRaw("DATE_FORMAT(`date`, '%Y') = ?", [$currentYear])->sum('amount'), true, true);
    $weeklyExpenses = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->whereBetween('date', [$startOfWeek, $endOfWeek])->sum('amount'), true, true);
    $completeExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->sum('amount'), true, true);

    $filteredExpense = null;
    if ($from && $to) {
        $filteredExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('site_id', $id)->where('status', 'Approved')->whereBetween('date', [$from, $to])->sum('amount'), true, true);
    }

    $result = ['todayExpense' => $todayExpense, 'monthExpense' => $monthExpense, 'weeklyExpenses' => $weeklyExpenses, 'yearExpense' => $yearExpense,'completeExpense'=>$completeExpense, 'filteredExpense' => $filteredExpense];
    return $result;
}
function get_company_expense_area_chart_widget($from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');

    $currentMonth = Carbon::now()->format('Y-m');
    $currentYear = Carbon::now()->year;
    $today = Carbon::now()->format('Y-m-d');
    $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
    $endOfWeek = Carbon::now()->endOfWeek()->format('Y-m-d');


    $monthExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->whereRaw("DATE_FORMAT(`date`, '%Y-%m') = ?", [$currentMonth])->sum('amount'), true, true);
    $todayExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->where('date', $today)->sum('amount'), true, true);
    $yearExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->whereRaw("DATE_FORMAT(`date`, '%Y') = ?", [$currentYear])->sum('amount'), true, true);
    $weeklyExpenses = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->whereBetween('date', [$startOfWeek, $endOfWeek])->sum('amount'), true, true);
    $completeExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->sum('amount'), true, true);

    $filteredExpense = null;
    if ($from && $to) {
        $filteredExpense = getStructuredAmount(DB::connection($user_db_conn_name)->table('expenses')->where('status', 'Approved')->whereBetween('date', [$from, $to])->sum('amount'), true, true);
    }

    $result = ['todayExpense' => $todayExpense, 'monthExpense' => $monthExpense, 'weeklyExpenses' => $weeklyExpenses, 'yearExpense' => $yearExpense,'completeExpense'=>$completeExpense, 'filteredExpense' => $filteredExpense];
    return $result;
}
function get_monthlyExpenses_chart_head_table($id, $from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
    ->join('expense_head', 'expense_head.id', '=', 'expenses.head_id')
    ->where('expenses.site_id', '=', $id)
    ->where('expenses.status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('expenses.date', [$from, $to]);
    } else {
        $currentMonth = Carbon::now()->format('Y-m');
        $query->whereRaw("DATE_FORMAT(expenses.date, '%Y-%m') = ?", [$currentMonth]);
    }

    $data = $query->select('expense_head.name as label', DB::raw('SUM(expenses.amount) as value'))->groupBy('label')->get();
    return $data;
}
function get_company_monthlyExpenses_chart_head_table($from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
    ->join('expense_head', 'expense_head.id', '=', 'expenses.head_id')
    ->where('expenses.status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('expenses.date', [$from, $to]);
    } else {
        $currentMonth = Carbon::now()->format('Y-m');
        $query->whereRaw("DATE_FORMAT(expenses.date, '%Y-%m') = ?", [$currentMonth]);
    }

    $data = $query->select('expense_head.name as label', DB::raw('SUM(expenses.amount) as value'))->groupBy('label')->get();
    return $data;
}
function get_monthlyExpensesFormatted_chart_widget($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->where('site_id', $id)
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('date', [$from, $to]);
    } else {
        $query->whereYear('date', Carbon::now()->year);
    }

    $monthlyExpenses = $query->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->orderBy(DB::raw('YEAR(date)'))
        ->orderBy(DB::raw('MONTH(date)'))
        ->get();

    // Mapping to chart format
    $formattedData = [];
    foreach ($monthlyExpenses as $me) {
        $monthName = date("M", mktime(0, 0, 0, $me->month, 10));
        $formattedData[] = [
            'period' => $monthName . '-' . substr($me->year, 2),
            'expense' => $me->total_amount
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}
function get_company_monthlyExpensesFormatted_chart_widget($from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('expenses')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('date', [$from, $to]);
    } else {
        $query->whereYear('date', Carbon::now()->year);
    }

    $monthlyExpenses = $query->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->orderBy(DB::raw('YEAR(date)'))
        ->orderBy(DB::raw('MONTH(date)'))
        ->get();

    $formattedData = [];
    foreach ($monthlyExpenses as $me) {
        $monthName = date("M", mktime(0, 0, 0, $me->month, 10));
        $formattedData[] = [
            'period' => $monthName . '-' . substr($me->year, 2),
            'expense' => $me->total_amount
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}
function formatMonthlyDataOfYearlyContent($monthlyAmount, $year)
{
    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aug',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dec'
    ];

    $formattedData = [];
    foreach ($months as $monthNum => $monthName) {
        $expense = $monthlyAmount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $formattedData[] = [
            'period' => $monthName . '-' . substr($year, 2),
            'expense' => $expense
        ];
    }

    return $formattedData;
}
function get_pending_flags_data_widget($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $q_exp = DB::connection($user_db_conn_name)->table('expenses')->where('status', '=', 'Pending')->where('site_id', '=', $id);
    $q_mat = DB::connection($user_db_conn_name)->table('material_entry')->where('status', '=', 'Pending')->where('site_id', '=', $id);
    $q_bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', '=', 'Pending')->where('site_id', '=', $id);
    $q_pv_p = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('status', '=', 'Pending')->where('site_id', '=', $id);
    $q_pv_u = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('status', '=', 'Approved')->where('site_id', '=', $id);
    $q_ep = DB::connection($user_db_conn_name)->table('expense_party')->where('status', '=', 'Pending')->where('site_id', '=', $id);
    $q_bp = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Pending')->where('site_id', '=', $id);

    if ($from && $to) {
        $q_exp->whereBetween('expenses.date', [$from, $to]);
        $q_mat->whereBetween('material_entry.date', [$from, $to]);
        $q_bill->whereBetween('new_bill_entry.billdate', [$from, $to]);
        $q_pv_p->whereBetween('payment_vouchers.date', [$from, $to]);
        $q_pv_u->whereBetween('payment_vouchers.date', [$from, $to]);
        $q_ep->whereBetween('expense_party.create_datetime', [$from, $to]);
        $q_bp->whereBetween('bills_party.create_datetime', [$from, $to]);
    }



    $pending_expense = $q_exp->count();
    $pending_mat = $q_mat->count();
    $pending_bill = $q_bill->count();
    $pending_pv = $q_pv_p->count();
    $unpaid_pv = $q_pv_u->count();
    $pending_expense_party = $q_ep->count();
    $pending_bill_party = $q_bp->count();
    
    $result = ['pending_expense' => $pending_expense, 'pending_mat' => $pending_mat, 'pending_bill' => $pending_bill, 'pending_pv' => $pending_pv, 'unpaid_pv' => $unpaid_pv, 'pending_expense_party' => $pending_expense_party, 'pending_bill_party' => $pending_bill_party];
    return $result;
}
function get_company_pending_flags_data_widget($from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    
    $q_exp = DB::connection($user_db_conn_name)->table('expenses')->where('status', '=', 'Pending');
    $q_mat = DB::connection($user_db_conn_name)->table('material_entry')->where('status', '=', 'Pending');
    $q_bill = DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', '=', 'Pending');
    $q_pv_p = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('status', '=', 'Pending');
    $q_pv_u = DB::connection($user_db_conn_name)->table('payment_vouchers')->where('status', '=', 'Approved');
    $q_ep = DB::connection($user_db_conn_name)->table('expense_party')->where('status', '=', 'Pending');
    $q_bp = DB::connection($user_db_conn_name)->table('bills_party')->where('status', '=', 'Pending');

    if ($from && $to) {
        $q_exp->whereBetween('expenses.date', [$from, $to]);
        $q_mat->whereBetween('material_entry.date', [$from, $to]);
        $q_bill->whereBetween('new_bill_entry.billdate', [$from, $to]);
        $q_pv_p->whereBetween('payment_vouchers.date', [$from, $to]);
        $q_pv_u->whereBetween('payment_vouchers.date', [$from, $to]);
        $q_ep->whereBetween('expense_party.create_datetime', [$from, $to]);
        $q_bp->whereBetween('bills_party.create_datetime', [$from, $to]);
    }



    $pending_expense = $q_exp->count();
    $pending_mat = $q_mat->count();
    $pending_bill = $q_bill->count();
    $pending_pv = $q_pv_p->count();
    $unpaid_pv = $q_pv_u->count();
    $pending_expense_party = $q_ep->count();
    $pending_bill_party = $q_bp->count();
    
    $result = ['pending_expense' => $pending_expense, 'pending_mat' => $pending_mat, 'pending_bill' => $pending_bill, 'pending_pv' => $pending_pv, 'unpaid_pv' => $unpaid_pv, 'pending_expense_party' => $pending_expense_party, 'pending_bill_party' => $pending_bill_party];
    return $result;
}
function getFlagAlertIconClass($amount)
{
    if ($amount == 0) {
        return 'zmdi-assignment-check col-green';
    } else if ($amount < 10) {
        return 'zmdi-assignment-alert col-amber';
    } else {
        return 'zmdi-assignment-alert col-red';
    }
}
function get_site_sales_invoices_chart_widget($id, $from = null, $to = null)
{

    $user_db_conn_name = session()->get('comp_db_conn_name');
    $site = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->first();
    if ($site->project_id == 0) {
        return [];
    } else {
        $result = [];
        $project_id = $site->project_id;

        $query = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $project_id);
        
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }

        $invoices = $query->get();
        foreach ($invoices as $invoice) {


            $amount = $invoice->amount;
            
            $manage = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->leftJoin('sales_dedadd', 'sales_dedadd.id', '=', 'sales_manage_invoice.type_id')->where('sales_manage_invoice.invoice_id', '=', $invoice->id)->select('sales_manage_invoice.*', 'sales_dedadd.name as type_name', 'sales_dedadd.type as type')->get();
            $credit = 0;
            $debit = 0;
            foreach ($manage as $mng) {
                if ($mng->type == 'add') {
                    $debit += $mng->amount;
                } else {
                    $credit += $mng->amount;
                }
            }
            $balance  = $amount + $debit - $credit;
            $res = ["y" => $invoice->invoice_no, "a" => $amount, "b" => $balance];
            array_push($result, $res);
        }

        
    if (count($result) === 1) {
        $dummy = $result[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($result, $dummy);
    }
    return $result;
    }
}

function get_company_sales_invoices_chart_widget()
{

    $user_db_conn_name = session()->get('comp_db_conn_name');
  
        $result = [];
     

        $invoices = DB::connection($user_db_conn_name)->table('sales_invoice')
        ->select('*', DB::raw("
            CASE 
                WHEN SUBSTRING(financial_year, 1, 2) = '20' 
                    THEN CONCAT(SUBSTRING(financial_year, 3, 2), '-', RIGHT(SUBSTRING_INDEX(financial_year, '-', -1), 2))
                ELSE CONCAT(SUBSTRING(financial_year, 1, 2), '-', RIGHT(SUBSTRING_INDEX(financial_year, '-', -1), 2))
            END AS formatted_year
        "))
        ->orderBy(DB::raw("
            CASE 
                WHEN SUBSTRING(financial_year, 1, 2) = '20' 
                    THEN CONCAT(SUBSTRING(financial_year, 3, 2), '-', RIGHT(SUBSTRING_INDEX(financial_year, '-', -1), 2))
                ELSE CONCAT(SUBSTRING(financial_year, 1, 2), '-', RIGHT(SUBSTRING_INDEX(financial_year, '-', -1), 2))
            END
        "))
        ->get();
        if(count($invoices)>0){
        $financial_year = $invoices[0]->formatted_year;
        $fy_amount=0;
        $fy_balance=0;
        foreach ($invoices as $invoice) {
            if($invoice->formatted_year != $financial_year) {    
                $fin = explode("-",$financial_year);
                $final_fy = "20".$fin[0]." - 20".$fin[1];                    
                $res = ["y" => $final_fy, "a" => $fy_amount, "b" => $fy_balance];
                array_push($result, $res);
                $financial_year = $invoice->formatted_year;
                $fy_amount=0;
                $fy_balance=0;
            }
            $amount = $invoice->amount;        
            $manage = DB::connection($user_db_conn_name)->table('sales_manage_invoice')->leftJoin('sales_dedadd', 'sales_dedadd.id', '=', 'sales_manage_invoice.type_id')->where('sales_manage_invoice.invoice_id', '=', $invoice->id)->select('sales_manage_invoice.*', 'sales_dedadd.name as type_name', 'sales_dedadd.type as type')->get();
            $credit = 0;
            $debit = 0;
            foreach ($manage as $mng) {
                if ($mng->type == 'add') {
                    $debit += $mng->amount;
                } else {
                    $credit += $mng->amount;
                }
            }
            $balance  = $amount + $debit - $credit;
            $fy_amount += $amount;
            $fy_balance += $balance;           


        }
    }
        
    if (count($result) === 1) {
        $dummy = $result[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($result, $dummy);
    }
    return $result;
    
}
function get_site_sales_invoices_chart_data($id, $from = null, $to = null){

    $user_db_conn_name = session()->get('comp_db_conn_name');
    $site = DB::connection($user_db_conn_name)->table('sites')->where('id', '=', $id)->first();
    if ($site->project_id == 0) {
        $res = ["base" => 0, "tax" => 0, "amount" => 0];
    } else {
        $project_id = $site->project_id;
        $query = DB::connection($user_db_conn_name)->table('sales_invoice')->where('project_id', '=', $project_id);
        
        if ($from && $to) {
            $query->whereBetween('date', [$from, $to]);
        }
        
        $invoices = $query->get();
        $base_sales = 0;
        $gst = 0;
        $amount=0;
        foreach ($invoices as $invoice) {
            $amount += $invoice->amount;
            $base_sales += $invoice->taxable_value;
            $gst += $invoice->amount - $invoice->taxable_value;
        }
        $res = ["base" => getStructuredAmount($base_sales,true,true), "tax" => getStructuredAmount($gst,true,true), "amount" =>getStructuredAmount($amount,true,true)];

}
return $res;

}
function get_company_sales_invoices_chart_data($from = null, $to = null){

    $user_db_conn_name = session()->get('comp_db_conn_name');
    $query = DB::connection($user_db_conn_name)->table('sales_invoice');
    
    if ($from && $to) {
        $query->whereBetween('date', [$from, $to]);
    }
        
    $invoices = $query->get();
    $base_sales = 0;
    $gst = 0;
    $amount=0;
    foreach ($invoices as $invoice) {
        $amount += $invoice->amount;
        $base_sales += $invoice->taxable_value;
        $gst += $invoice->amount - $invoice->taxable_value;
    }
    $res = ["base" => getStructuredAmount($base_sales,true,true), "tax" => getStructuredAmount($gst,true,true), "amount" =>getStructuredAmount($amount,true,true)];

    return $res;
}
function get_site_bills_area_chart_work_table($id, $from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('new_bills_item_entry as nbi')
    ->join('new_bill_entry as nb', 'nbi.bill_id', '=', 'nb.id')
    ->join('bills_work as bw', 'nbi.work_id', '=', 'bw.id')
    ->select(
        'bw.name as name',
        'bw.unit as unit',
        DB::raw('SUM(nbi.qty) as total_qty'),
        DB::raw('SUM(nbi.amount) as total_amount')
    )
    ->where('nb.site_id','=',$id)
    ->where('nb.status','=','Approved');

    if ($from && $to) {
        $query->whereBetween('nb.billdate', [$from, $to]);
    } else {
        $query->whereYear('nb.billdate', Carbon::now()->year);
    }

    $data = $query->groupBy('bw.name','bw.unit')->get();
return $data;
}
function  get_site_bill_area_chart_widget_data($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    $currentMonth = Carbon::now()->format('Y-m');
    $currentYear = Carbon::now()->year;
    $today = Carbon::now()->format('Y-m-d');
    $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
    $endOfWeek = Carbon::now()->endOfWeek()->format('Y-m-d');


    $monthbill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->whereRaw("DATE_FORMAT(`billdate`, '%Y-%m') = ?", [$currentMonth])->sum('amount'), true, true);
    $todaybill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->where('billdate', $today)->sum('amount'), true, true);
    $yearbill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->whereRaw("DATE_FORMAT(`billdate`, '%Y') = ?", [$currentYear])->sum('amount'), true, true);
    $weeklybill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->whereBetween('billdate', [$startOfWeek, $endOfWeek])->sum('amount'), true, true);
    $completeBill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->sum('amount'), true, true);

    $filteredBill = null;
    if ($from && $to) {
        $filteredBill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('site_id', $id)->where('status', 'Approved')->whereBetween('billdate', [$from, $to])->sum('amount'), true, true);
    }

    $result = ['todaybill' => $todaybill, 'monthbill' => $monthbill, 'weeklybill' => $weeklybill, 'yearbill' => $yearbill,'completeBill'=>$completeBill, 'filteredBill' => $filteredBill];
    return $result;
}
function get_site_bills_area_chart($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('new_bill_entry')
        ->select(DB::raw('MONTH(billdate) as month'), DB::raw('YEAR(billdate) as year'), DB::raw('SUM(amount) as total_amount'))
        ->where('site_id', $id)
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('billdate', [$from, $to]);
    } else {
        $query->whereYear('billdate', Carbon::now()->year);
    }

    $monthlyBills = $query->groupBy(DB::raw('YEAR(billdate)'), DB::raw('MONTH(billdate)'))
        ->orderBy(DB::raw('YEAR(billdate)'))
        ->orderBy(DB::raw('MONTH(billdate)'))
        ->get();

    $formattedData = [];
    foreach ($monthlyBills as $mb) {
        $monthName = date("M", mktime(0, 0, 0, $mb->month, 10));
        $formattedData[] = [
            'period' => $monthName . '-' . substr($mb->year, 2),
            'expense' => $mb->total_amount
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}

function get_company_site_bills_area_chart_work_table($from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('new_bills_item_entry as nbi')
    ->join('new_bill_entry as nb', 'nbi.bill_id', '=', 'nb.id')
    ->join('bills_work as bw', 'nbi.work_id', '=', 'bw.id')
    ->select(
        'bw.name as name',
        'bw.unit as unit',
        DB::raw('SUM(nbi.qty) as total_qty'),
        DB::raw('SUM(nbi.amount) as total_amount')
    )
    ->where('nb.status','=','Approved');

    if ($from && $to) {
        $query->whereBetween('nb.billdate', [$from, $to]);
    } else {
        $query->whereYear('nb.billdate', Carbon::now()->year);
    }

    $data = $query->groupBy('bw.name','bw.unit')->get();
return $data;
}
function  get_company_site_bill_area_chart_widget_data($from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    $currentMonth = Carbon::now()->format('Y-m');
    $currentYear = Carbon::now()->year;
    $today = Carbon::now()->format('Y-m-d');
    $startOfWeek = Carbon::now()->startOfWeek()->format('Y-m-d');
    $endOfWeek = Carbon::now()->endOfWeek()->format('Y-m-d');


    $monthbill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->whereRaw("DATE_FORMAT(`billdate`, '%Y-%m') = ?", [$currentMonth])->sum('amount'), true, true);
    $todaybill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->where('billdate', $today)->sum('amount'), true, true);
    $yearbill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->whereRaw("DATE_FORMAT(`billdate`, '%Y') = ?", [$currentYear])->sum('amount'), true, true);
    $weeklybill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->whereBetween('billdate', [$startOfWeek, $endOfWeek])->sum('amount'), true, true);
    $completeBill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->sum('amount'), true, true);

    $filteredBill = null;
    if ($from && $to) {
        $filteredBill = getStructuredAmount(DB::connection($user_db_conn_name)->table('new_bill_entry')->where('status', 'Approved')->whereBetween('billdate', [$from, $to])->sum('amount'), true, true);
    }

    $result = ['todaybill' => $todaybill, 'monthbill' => $monthbill, 'weeklybill' => $weeklybill, 'yearbill' => $yearbill,'completeBill'=>$completeBill, 'filteredBill' => $filteredBill];
    return $result;
}
function get_company_site_bills_area_chart($from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query = DB::connection($user_db_conn_name)->table('new_bill_entry')
        ->select(DB::raw('MONTH(billdate) as month'), DB::raw('YEAR(billdate) as year'), DB::raw('SUM(amount) as total_amount'))
        ->where('status', 'Approved');

    if ($from && $to) {
        $query->whereBetween('billdate', [$from, $to]);
    } else {
        $query->whereYear('billdate', Carbon::now()->year);
    }

    $monthlyBills = $query->groupBy(DB::raw('YEAR(billdate)'), DB::raw('MONTH(billdate)'))
        ->orderBy(DB::raw('YEAR(billdate)'))
        ->orderBy(DB::raw('MONTH(billdate)'))
        ->get();

    $formattedData = [];
    foreach ($monthlyBills as $mb) {
        $monthName = date("M", mktime(0, 0, 0, $mb->month, 10));
        $formattedData[] = [
            'period' => $monthName . '-' . substr($mb->year, 2),
            'expense' => $mb->total_amount
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}
function get_asset_list_table_widget($id, $from = null, $to = null){    
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $query = DB::connection($user_db_conn_name)->table('assets')
        ->leftjoin('sites', 'sites.id', '=', 'assets.site_id')
        ->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')
        ->select('assets.*', 'sites.name as site', 'asset_head.name as head')
        ->where('assets.status','=','Working');

    return $query->get();
}
function get_company_asset_list_table_widget($from = null, $to = null){    
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $query = DB::connection($user_db_conn_name)->table('assets')
        ->leftjoin('sites', 'sites.id', '=', 'assets.site_id')
        ->leftjoin('asset_head', 'asset_head.id', '=', 'assets.head_id')
        ->select('assets.*', 'sites.name as site', 'asset_head.name as head')
        ->where('assets.status','=','Working');

    return $query->get();
}

function get_machinery_list_table_widget($id, $from = null, $to = null){    
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $query = DB::connection($user_db_conn_name)->table('machinery_details')
        ->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')
        ->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
        ->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')
        ->where('machinery_details.site_id','=',$id)
        ->where('machinery_details.status','=','Working');

    return $query->get();
}
function get_company_machinery_list_table_widget($from = null, $to = null){ 
    $user_db_conn_name = session()->get('comp_db_conn_name');

    $query = DB::connection($user_db_conn_name)->table('machinery_details')
        ->leftjoin('sites', 'sites.id', '=', 'machinery_details.site_id')
        ->leftjoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
        ->select('machinery_details.*', 'sites.name as site', 'machinery_head.name as head')
        ->where('machinery_details.status','=','Working');

    return $query->get();
}
function get_payment_voucher_chart_widget($id, $from = null, $to = null)
{
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query_base = DB::connection($user_db_conn_name)->table('payment_vouchers')
        ->where('site_id', $id)
        ->where('status', 'Paid');

    if ($from && $to) {
        $query_base->whereBetween('date', [$from, $to]);
    } else {
        $query_base->whereYear('date', Carbon::now()->year);
    }

    $monthly_totals = (clone $query_base)
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->orderBy(DB::raw('YEAR(date)'))->orderBy(DB::raw('MONTH(date)'))
        ->get();

    $monthly_bp = (clone $query_base)->where('party_type', 'bill')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_site = (clone $query_base)->where('party_type', 'site')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_mat = (clone $query_base)->where('party_type', 'material')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_op = (clone $query_base)->where('party_type', 'other')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    // Mapping logic (simplified for multi-year)
    $formattedData = [];
    foreach ($monthly_totals as $mt) {
        $monthNum = $mt->month;
        $year = $mt->year;
        $monthName = date("M", mktime(0, 0, 0, $monthNum, 10));
        
        $bp = $monthly_bp->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $site = $monthly_site->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $mat = $monthly_mat->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $other = $monthly_op->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;

        $formattedData[] = [
            'period' => $monthName . '-' . substr($year, 2),
            'total' => $mt->total_amount,
            'bp' => $bp,
            'site' => $site,
            'mat' => $mat,
            'other' => $other
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}
function get_payment_voucher_chart_complete_widget($id){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');


    $monthlyvouchers_total = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('site_id', '=', $id)
    ->where('status', '=', 'Paid')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
   
    $monthlyvouchers_bp =DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('site_id', '=', $id)
    ->where('status', '=', 'Paid')
    ->where('party_type', 'bill')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
    
    

    $monthlyvouchers_site = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('site_id', '=', $id)
    ->where('status', '=', 'Paid')
    ->where('party_type', 'site')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();

    $monthlyvouchers_mat = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('site_id', '=', $id)
    ->where('status', '=', 'Paid')
    ->where('party_type', 'material')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
    

    $monthlyvouchers_op = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('site_id', '=', $id)
    ->where('status', '=', 'Paid')
    ->where('party_type', 'other')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
    

$completeypvFormatted = formatMonthlyDataOfPaymentVoucehrCompleteContent($monthlyvouchers_total,$monthlyvouchers_bp,$monthlyvouchers_site,$monthlyvouchers_op,$monthlyvouchers_mat);
return $completeypvFormatted;
}
function formatMonthlyDataOfPaymentVoucehrYearlyContent($totalAmount,$bp_amount,$site_amount,$op_amount,$mat_amount, $year)
{
    $months = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aug',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dec'
    ];

    $formattedData = [];
    foreach ($months as $monthNum => $monthName) {
        $total = $totalAmount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $bp = $bp_amount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $site = $site_amount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $mat = $mat_amount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $op = $op_amount->firstWhere('month', $monthNum)->total_amount ?? 0;
        $formattedData[] = [
            'period' => $monthName . '-' . substr($year, 2),
            'total'=>$total,
            'bp' => $bp,
            'site' => $site,
            'mat' => $mat,
            'other' => $op,
        ];
    }

    return $formattedData;
}
function formatMonthlyDataOfPaymentVoucehrCompleteContent($totalAmount,$bp_amount,$site_amount,$op_amount,$mat_amount)
{
   

    $formattedData = [];
    foreach ($totalAmount as $tm) {
       
        $bp = $bp_amount->firstWhere('month_year', $tm->month_year)->total_amount ?? 0;
        $site = $site_amount->firstWhere('month_year', $tm->month_year)->total_amount ?? 0;
        $mat = $mat_amount->firstWhere('month_year', $tm->month_year)->total_amount ?? 0;
        $op = $op_amount->firstWhere('month_year', $tm->month_year)->total_amount ?? 0;
        $formattedData[] = [
            'period' => $tm->month_year,
            'total'=>$tm->total_amount,
            'bp' => $bp,
            'site' => $site,
            'mat' => $mat,
            'other' => $op,
        ];
    }

    return $formattedData;
}

function get_company_payment_voucher_chart_widget($from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');
    
    $query_base = DB::connection($user_db_conn_name)->table('payment_vouchers')
        ->where('status', 'Paid');

    if ($from && $to) {
        $query_base->whereBetween('date', [$from, $to]);
    } else {
        $query_base->whereYear('date', Carbon::now()->year);
    }

    $monthly_totals = (clone $query_base)
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
        ->orderBy(DB::raw('YEAR(date)'))->orderBy(DB::raw('MONTH(date)'))
        ->get();

    $monthly_bp = (clone $query_base)->where('party_type', 'bill')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_site = (clone $query_base)->where('party_type', 'site')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_mat = (clone $query_base)->where('party_type', 'material')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $monthly_op = (clone $query_base)->where('party_type', 'other')
        ->select(DB::raw('MONTH(date) as month'), DB::raw('YEAR(date) as year'), DB::raw('SUM(amount) as total_amount'))
        ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))->get();

    $formattedData = [];
    foreach ($monthly_totals as $mt) {
        $monthNum = $mt->month;
        $year = $mt->year;
        $monthName = date("M", mktime(0, 0, 0, $monthNum, 10));
        
        $bp = $monthly_bp->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $site = $monthly_site->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $mat = $monthly_mat->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;
        $other = $monthly_op->where('month', $monthNum)->where('year', $year)->first()->total_amount ?? 0;

        $formattedData[] = [
            'period' => $monthName . '-' . substr($year, 2),
            'total' => $mt->total_amount,
            'bp' => $bp,
            'site' => $site,
            'mat' => $mat,
            'other' => $other
        ];
    }
    
    if (count($formattedData) === 1) {
        $dummy = $formattedData[0];
        foreach($dummy as $key => $val) {
            if ($key !== 'period' && $key !== 'y') {
                $dummy[$key] = 0;
            }
        }
        if (isset($dummy['period'])) { $dummy['period'] = 'Start'; }
        if (isset($dummy['y'])) { $dummy['y'] = 'Start'; }
        array_unshift($formattedData, $dummy);
    }
    return $formattedData;
}

function get_company_payment_voucher_chart_complete_widget($from = null, $to = null){
    $user_db_conn_name = session()->get('comp_db_conn_name');
    date_default_timezone_set('Asia/Kolkata');

    $query_base = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->where('status', '=', 'Paid');

    if ($from && $to) {
        $query_base->whereBetween('date', [$from, $to]);
    }

    $monthlyvouchers_total = (clone $query_base)
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), 
        DB::raw('SUM(amount) as total_amount')
    )
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
   
    $monthlyvouchers_bp = (clone $query_base)
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('party_type', 'bill')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->get();
    
    

    $monthlyvouchers_site = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('status', '=', 'Paid')
    ->where('party_type', 'site')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();

    $monthlyvouchers_mat = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('status', '=', 'Paid')
    ->where('party_type', 'material')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
    

    $monthlyvouchers_op = DB::connection($user_db_conn_name)
    ->table('payment_vouchers')
    ->select(
        DB::raw('YEAR(date) as year'),
        DB::raw('MONTH(date) as month'),
        DB::raw('DATE_FORMAT(MIN(date), "%b-%Y") as month_year'), // Use MIN(date) to apply aggregation
        DB::raw('SUM(amount) as total_amount')
    )
    ->where('status', '=', 'Paid')
    ->where('party_type', 'other')
    ->groupBy(DB::raw('YEAR(date)'), DB::raw('MONTH(date)'))
    ->orderBy(DB::raw('YEAR(date)'), 'asc')
    ->orderBy(DB::raw('MONTH(date)'), 'asc')
    ->get();
    

$completeypvFormatted = formatMonthlyDataOfPaymentVoucehrCompleteContent($monthlyvouchers_total,$monthlyvouchers_bp,$monthlyvouchers_site,$monthlyvouchers_op,$monthlyvouchers_mat);
return $completeypvFormatted;
}
// widgets helper functions end