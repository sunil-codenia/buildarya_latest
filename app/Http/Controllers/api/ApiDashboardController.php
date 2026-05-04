<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApiDashboardController extends Controller
{
    /**
     * Get Dashboard Stats for Flutter App
     * Mirrors the website dashboard logic exactly.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        try {
            $conn = config('database.default');
            
            // 1. Get Filters
            $filter_type = $request->get('date_filter', 'this_year');
            $from_date = $request->get('from_date');
            $to_date = $request->get('to_date');
            $req_site_id = $request->get('site_id'); // Can be 'all' or specific ID

            // Resolve Dates using helper
            $filter_dates = get_dashboard_filter_dates($filter_type, $from_date, $to_date);
            $from = $filter_dates['start'];
            $to = $filter_dates['end'];

            // 2. Resolve Site Scope
            $role_details = getRoleDetailsById($user->role_id);
            $visiblity_at_site = $role_details->visiblity_at_site;

            $site_id = 'all'; // Default to Company Overview
            if ($visiblity_at_site == 'current') {
                $site_id = $user->site_id;
            } else if ($req_site_id && $req_site_id != 'all') {
                $site_id = $req_site_id;
            }

            $stats = [];
            $stats['filter_type'] = $filter_type;
            $stats['from_date'] = $from;
            $stats['to_date'] = $to;

            if ($site_id && $site_id != 'all') {
                // --- SITE DASHBOARD ---
                $stats['dashboard_type'] = 'Site';
                $stats['site_id'] = $site_id;
                $stats['site_balance'] = get_site_balance_data_widget($site_id, $to);
                $stats['employees_on_site'] = get_employee_on_site_data_widget($site_id);
                
                // Pending Flags
                $stats['pending'] = get_pending_flags_data_widget($site_id, $from, $to);
                
                // Expense Summary
                $stats['expense_summary'] = get_site_expense_area_chart_widget($site_id, $from, $to);
                
                // Monthly Trend
                $stats['monthly_expense'] = get_monthlyExpensesFormatted_chart_widget($site_id, $from, $to);
                
                // Head Breakdown
                $stats['head_breakdown'] = get_monthlyExpenses_chart_head_table($site_id, $from, $to);
            } else {
                // --- COMPANY DASHBOARD ---
                $stats['dashboard_type'] = 'Company';
                $stats['total_working_sites'] = get_total_sites_data_widget();
                $stats['total_employees'] = get_total_employee_data_widget();
                
                // Expenses for the selected period
                $exp_summary = get_company_expense_area_chart_widget($from, $to);
                $stats['period_expenses'] = $exp_summary['filteredExpense'] ?? $exp_summary['monthExpense'];
                $stats['expense_summary'] = $exp_summary;

                // Pending Flags
                $stats['pending'] = get_company_pending_flags_data_widget($from, $to);
                
                // Monthly Trend
                $stats['monthly_expense'] = get_company_monthlyExpensesFormatted_chart_widget($from, $to);
                
                // Head Breakdown
                $stats['head_breakdown'] = get_company_monthlyExpenses_chart_head_table($from, $to);
            }

            return response()->json([
                'status' => 'Ok',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'Error',
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Dashboard CSV
     */
    public function export(Request $request)
    {
        // Re-use the existing CSV export logic from DashboardController
        // Since it's identical, we can just call it or mirror it.
        $web_dashboard = new \App\Http\Controllers\DashboardController();
        return $web_dashboard->exportCsv($request);
    }
}
