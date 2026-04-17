<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController extends Controller
{



    public function getCompanyDashboard(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');       
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;

        // Filter parameters
        $filter_type = $request->get('date_filter', 'this_year');
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $site_id = $request->get('site_id');
        $compare_site_id = $request->get('compare_site_id');

        $filter_dates = get_dashboard_filter_dates($filter_type, $from_date, $to_date);
        $from = $filter_dates['start'];
        $to = $filter_dates['end'];

        // If today is April 1st (start of FY), 'this_year' will only show today.
        // But for charts, we usually want to see the trend. 
        // However, user specifically asked for 'this_year' by default.
        $from_chart = $from;
        $to_chart = $to;

        if ($visiblity_at_site == 'current') {
            $assigned_ids = $request->session()->get('assigned_site_ids', []);
            $sitesnameadd = DB::connection($user_db_conn_name)->table('sites')->whereIn('id', $assigned_ids)->get();
            $id = $request->session()->get('site_id');
            return view('layouts.dashboard', compact(['id', 'from', 'to', 'from_chart', 'to_chart', 'filter_type', 'from_date', 'to_date', 'sitesnameadd']));
        } else {
            $sitesnameadd = DB::connection($user_db_conn_name)->select("SELECT * from `sites` WHERE status = 'Active'");
            if ($site_id && $site_id != 'all') {
                $id = $site_id;
                return view('layouts.dashboard', compact(['id', 'from', 'to', 'from_chart', 'to_chart', 'filter_type', 'from_date', 'to_date', 'sitesnameadd', 'compare_site_id']));
            }

            return view('layouts.company_dashboard', compact(['sitesnameadd', 'from', 'to', 'from_chart', 'to_chart', 'filter_type', 'from_date', 'to_date']));
        }
    }

    public function getSiteDashboardData(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $id = $request->get('display_site');

        return  view('layouts.dashboard', compact(['id']));
    }


    public function exportCsv(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $role_id = $request->session()->get('role');       
        $role_details = getRoleDetailsById($role_id);
        $visiblity_at_site = $role_details->visiblity_at_site;

        // Filter parameters
        $filter_type = $request->get('date_filter', 'this_year');
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $site_id = $request->get('site_id');
        $compare_site_id = $request->get('compare_site_id');

        $filter_dates = get_dashboard_filter_dates($filter_type, $from_date, $to_date);
        $from = $filter_dates['start'];
        $to = $filter_dates['end'];

        if ($visiblity_at_site == 'current') {
            $id = $request->session()->get('site_id');
        } else {
            if ($site_id && $site_id != 'all') {
                $id = $site_id;
            } else {
                $id = null;
            }
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="dashboard_export.csv"',
        ];

        return response()->stream(function () use ($id, $from, $to, $compare_site_id, $user_db_conn_name) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Dashboard Data Export']);
            fputcsv($file, ['Filter Type', str_replace('_', ' ', ucfirst($id ? 'Site' : 'Company')) . ' Dashboard']);
            fputcsv($file, ['From Date', $from, 'To Date', $to]);
            fputcsv($file, []);

            if ($id) {
                // --- SITE DASHBOARD ---
                $site_name = DB::connection($user_db_conn_name)->table('sites')->where('id', $id)->first()->name;
                fputcsv($file, ['Site Name', $site_name]);
                fputcsv($file, []);

                // 1. Site Summary
                fputcsv($file, ['--- Site Summary ---']);
                fputcsv($file, ['Metric', 'Value']);
                fputcsv($file, ['Site Balance', get_site_balance_data_widget($id, $to)]);
                fputcsv($file, ['Employees on Site', get_employee_on_site_data_widget($id)]);
                fputcsv($file, []);

                // 2. Pending Flags
                fputcsv($file, ['--- Pending Flags ---']);
                fputcsv($file, ['Flag Type', 'Count']);
                $flags = get_pending_flags_data_widget($id, $from, $to);
                fputcsv($file, ['Pending Expenses', $flags['pending_expense']]);
                fputcsv($file, ['Pending Material Entries', $flags['pending_mat']]);
                fputcsv($file, ['Pending Bills', $flags['pending_bill']]);
                fputcsv($file, ['Pending Payment Vouchers', $flags['pending_pv']]);
                fputcsv($file, ['Unpaid Payment Vouchers', $flags['unpaid_pv']]);
                fputcsv($file, ['Pending Expense Parties', $flags['pending_expense_party']]);
                fputcsv($file, ['Pending Bill Parties', $flags['pending_bill_party']]);
                fputcsv($file, []);

                // 3. Expense Summary
                fputcsv($file, ['--- Expense Summary ---']);
                fputcsv($file, ['Category', 'Amount']);
                $expSummary = get_site_expense_area_chart_widget($id, $from, $to);
                if ($expSummary['filteredExpense']) fputcsv($file, ['Filtered Range', $expSummary['filteredExpense']]);
                fputcsv($file, ['Today', $expSummary['todayExpense']]);
                fputcsv($file, ['This Week', $expSummary['weeklyExpenses']]);
                fputcsv($file, ['This Month', $expSummary['monthExpense']]);
                fputcsv($file, ['This Year', $expSummary['yearExpense']]);
                fputcsv($file, ['Till Date', $expSummary['completeExpense']]);
                fputcsv($file, []);

                // 4. Monthly Expenses (with Comparison)
                $monthlyExpenses = get_monthlyExpensesFormatted_chart_widget($id, $from, $to);
                $compare_name = null;
                $compare_expenses = [];
                if ($compare_site_id) {
                    $compare_name = DB::connection($user_db_conn_name)->table('sites')->where('id', $compare_site_id)->first()->name;
                    $compare_expenses = get_monthlyExpensesFormatted_chart_widget($compare_site_id, $from, $to);
                    fputcsv($file, ['--- Monthly Expenses Comparison ('.$site_name.' vs '.$compare_name.') ---']);
                    fputcsv($file, ['Month', $site_name . ' Expense', $compare_name . ' Expense']);
                    
                    $merged = [];
                    foreach ($monthlyExpenses as $row) { $row = (array)$row; $merged[$row['period']] = ['p' => $row['period'], 'orig' => $row['expense'] ?? 0, 'comp' => 0]; }
                    foreach ($compare_expenses as $row) { $row = (array)$row; if (isset($merged[$row['period']])) { $merged[$row['period']]['comp'] = $row['expense'] ?? 0; } else { $merged[$row['period']] = ['p' => $row['period'], 'orig' => 0, 'comp' => $row['expense'] ?? 0]; } }
                    foreach ($merged as $row) { fputcsv($file, [$row['p'], $row['orig'], $row['comp']]); }
                } else {
                    fputcsv($file, ['--- Monthly Expenses ---']);
                    fputcsv($file, ['Month', 'Expense']);
                    foreach ($monthlyExpenses as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['expense'] ?? 0]); }
                }
                fputcsv($file, []);

                // 5. Expense Breakdown By Head
                fputcsv($file, ['--- Expense Breakdown By Head ---']);
                fputcsv($file, ['Head Name', 'Amount']);
                $headExp = get_monthlyExpenses_chart_head_table($id, $from, $to);
                foreach ($headExp as $row) { fputcsv($file, [$row->label, $row->value]); }
                fputcsv($file, []);

                // 6. Sales Invoices
                fputcsv($file, ['--- Sales Invoices ---']);
                fputcsv($file, ['Month', 'Amount', 'Balance']);
                $sales = get_site_sales_invoices_chart_widget($id, $from, $to);
                foreach ($sales as $row) { $row = (array)$row; fputcsv($file, [$row['y'], $row['a'], $row['b']]); }
                fputcsv($file, []);

                // 7. Site Bills (with Comparison)
                $siteBills = get_site_bills_area_chart($id, $from, $to);
                if ($compare_site_id) {
                    $compare_bills = get_site_bills_area_chart($compare_site_id, $from, $to);
                    fputcsv($file, ['--- Site Bills Comparison ('.$site_name.' vs '.$compare_name.') ---']);
                    fputcsv($file, ['Month', $site_name . ' Amount', $compare_name . ' Amount']);
                    $merged = [];
                    foreach ($siteBills as $row) { $row = (array)$row; $merged[$row['period']] = ['p' => $row['period'], 'orig' => $row['expense'] ?? 0, 'comp' => 0]; }
                    foreach ($compare_bills as $row) { $row = (array)$row; if (isset($merged[$row['period']])) { $merged[$row['period']]['comp'] = $row['expense'] ?? 0; } else { $merged[$row['period']] = ['p' => $row['period'], 'orig' => 0, 'comp' => $row['expense'] ?? 0]; } }
                    foreach ($merged as $row) { fputcsv($file, [$row['p'], $row['orig'], $row['comp']]); }
                } else {
                    fputcsv($file, ['--- Site Bills ---']);
                    fputcsv($file, ['Month', 'Amount']);
                    foreach ($siteBills as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['expense'] ?? 0]); }
                }
                fputcsv($file, []);

                // 8. Site Bills Work Breakdown
                fputcsv($file, ['--- Site Bills Work Breakdown ---']);
                fputcsv($file, ['Work Name', 'Unit', 'Total Qty', 'Total Amount']);
                $workBreakdown = get_site_bills_area_chart_work_table($id, $from, $to);
                foreach ($workBreakdown as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['unit'], $row['total_qty'], $row['total_amount']]); }
                fputcsv($file, []);

                // 9. Payment Vouchers
                fputcsv($file, ['--- Payment Vouchers ---']);
                fputcsv($file, ['Month', 'Total', 'Bill Party', 'Site', 'Material Supplier', 'Other Party']);
                $vouchers = get_payment_voucher_chart_widget($id, $from, $to);
                foreach ($vouchers as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['total'], $row['bp'], $row['site'], $row['mat'], $row['other']]); }
                fputcsv($file, []);

                // 10. Assets
                fputcsv($file, ['--- Assets List ---']);
                fputcsv($file, ['Name', 'Head', 'Cost Price']);
                $assets = get_asset_list_table_widget($id, $from, $to);
                foreach ($assets as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['head'], $row['cost_price']]); }
                fputcsv($file, []);

                // 11. Machinery
                fputcsv($file, ['--- Machinery List ---']);
                fputcsv($file, ['Name', 'Head']);
                $machinery = get_machinery_list_table_widget($id, $from, $to);
                foreach ($machinery as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['head']]); }

            } else {
                // --- COMPANY DASHBOARD ---
                // 1. Company Summary
                fputcsv($file, ['--- Company Summary ---']);
                fputcsv($file, ['Metric', 'Value']);
                fputcsv($file, ['Total Sites', get_total_sites_data_widget()]);
                fputcsv($file, ['Total Employees', get_total_employee_data_widget()]);
                fputcsv($file, []);

                // 2. Pending Flags
                fputcsv($file, ['--- Pending Flags ---']);
                fputcsv($file, ['Flag Type', 'Count']);
                $flags = get_company_pending_flags_data_widget($from, $to);
                fputcsv($file, ['Pending Expenses', $flags['pending_expense']]);
                fputcsv($file, ['Pending Material Entries', $flags['pending_mat']]);
                fputcsv($file, ['Pending Bills', $flags['pending_bill']]);
                fputcsv($file, ['Pending Payment Vouchers', $flags['pending_pv']]);
                fputcsv($file, ['Unpaid Payment Vouchers', $flags['unpaid_pv']]);
                fputcsv($file, ['Pending Expense Parties', $flags['pending_expense_party']]);
                fputcsv($file, ['Pending Bill Parties', $flags['pending_bill_party']]);
                fputcsv($file, []);

                // 3. Company Expense Summary
                fputcsv($file, ['--- Company Expense Summary ---']);
                fputcsv($file, ['Category', 'Amount']);
                $expSummary = get_company_expense_area_chart_widget($from, $to);
                if ($expSummary['filteredExpense']) fputcsv($file, ['Filtered Range', $expSummary['filteredExpense']]);
                fputcsv($file, ['Today', $expSummary['todayExpense']]);
                fputcsv($file, ['This Week', $expSummary['weeklyExpenses']]);
                fputcsv($file, ['This Month', $expSummary['monthExpense']]);
                fputcsv($file, ['This Year', $expSummary['yearExpense']]);
                fputcsv($file, ['Till Date', $expSummary['completeExpense']]);
                fputcsv($file, []);

                // 4. Monthly Expenses
                fputcsv($file, ['--- Company Monthly Expenses ---']);
                fputcsv($file, ['Month', 'Expense']);
                $monthlyExpenses = get_company_monthlyExpensesFormatted_chart_widget($from, $to);
                foreach ($monthlyExpenses as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['expense'] ?? 0]); }
                fputcsv($file, []);

                // 5. Expense Breakdown By Head
                fputcsv($file, ['--- Company Expense Breakdown By Head ---']);
                fputcsv($file, ['Head Name', 'Amount']);
                $headExp = get_company_monthlyExpenses_chart_head_table($from, $to);
                foreach ($headExp as $row) { fputcsv($file, [$row->label, $row->value]); }
                fputcsv($file, []);

                // 6. Sales Invoices
                fputcsv($file, ['--- Company Sales Invoices ---']);
                fputcsv($file, ['Month', 'Amount', 'Balance']);
                $sales = get_company_sales_invoices_chart_widget($from, $to);
                foreach ($sales as $row) { $row = (array)$row; fputcsv($file, [$row['y'], $row['a'], $row['b']]); }
                fputcsv($file, []);

                // 7. Site Bills
                fputcsv($file, ['--- Company Site Bills ---']);
                fputcsv($file, ['Month', 'Amount']);
                $siteBills = get_company_site_bills_area_chart($from, $to);
                foreach ($siteBills as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['expense'] ?? 0]); }
                fputcsv($file, []);

                // 8. Site Bills Work Breakdown
                fputcsv($file, ['--- Company Site Bills Work Breakdown ---']);
                fputcsv($file, ['Work Name', 'Unit', 'Total Qty', 'Total Amount']);
                $workBreakdown = get_company_site_bills_area_chart_work_table($from, $to);
                foreach ($workBreakdown as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['unit'], $row['total_qty'], $row['total_amount']]); }
                fputcsv($file, []);

                // 9. Payment Vouchers
                fputcsv($file, ['--- Company Payment Vouchers ---']);
                fputcsv($file, ['Month', 'Total', 'Bill Party', 'Site', 'Material Supplier', 'Other Party']);
                $vouchers = get_company_payment_voucher_chart_widget($from, $to);
                foreach ($vouchers as $row) { $row = (array)$row; fputcsv($file, [$row['period'], $row['total'], $row['bp'], $row['site'], $row['mat'], $row['other']]); }
                fputcsv($file, []);

                // 10. Assets
                fputcsv($file, ['--- Company Assets List ---']);
                fputcsv($file, ['Name', 'Head', 'Site', 'Cost Price']);
                $assets = get_company_asset_list_table_widget($from, $to);
                foreach ($assets as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['head'], $row['site'], $row['cost_price']]); }
                fputcsv($file, []);

                // 11. Machinery
                fputcsv($file, ['--- Company Machinery List ---']);
                fputcsv($file, ['Name', 'Head', 'Site']);
                $machinery = get_company_machinery_list_table_widget($from, $to);
                foreach ($machinery as $row) { $row = (array)$row; fputcsv($file, [$row['name'], $row['head'], $row['site']]); }
            }

            fclose($file);
        }, 200, $headers);
    }
    public function switch_active_site(Request $request, $id)
    {
        $id = base64_decode($id);
        $assigned_ids = $request->session()->get('assigned_site_ids', []);
        
        if ($id == 'all' || in_array($id, $assigned_ids) || isSuperAdmin()) {
            $request->session()->put('site_id', $id);
            return redirect('/dashboard')->with('success', 'Site switched successfully!');
        }
        
        return redirect('/dashboard')->with('error', 'You are not assigned to this site!');
    }
}
