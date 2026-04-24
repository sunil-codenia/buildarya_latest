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
     * Protected by auth:sanctum
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        try {
            $conn = config('database.default');
            
            // Note: The 'tenant' middleware has already set this as the default connection 
            // and populated the session for legacy helpers.
            
            $site_id = $user->site_id;
            $to_date = Carbon::now()->format('Y-m-d');
            $from_date = Carbon::now()->startOfMonth()->format('Y-m-d');

            $stats = [];

            // 1. Site Balance (if specific site)
            if ($site_id && $site_id != 'all') {
                $stats['site_balance'] = get_site_balance_data_widget($site_id, $to_date, $conn);
                $stats['employees_on_site'] = get_employee_on_site_data_widget($site_id, $conn);
            } else {
                $stats['total_sites'] = get_total_sites_data_widget($conn);
                $stats['total_employees'] = get_total_employee_data_widget($conn);
            }

            // 2. Monthly Expenses
            $stats['monthly_expense'] = get_company_monthlyExpensesFormatted_chart_widget($from_date, $to_date, $conn);

            // 3. Pending Flags (Summary)
            if ($site_id && $site_id != 'all') {
                $stats['pending'] = get_pending_flags_data_widget($site_id, $from_date, $to_date, $conn);
            } else {
                $stats['pending'] = get_company_pending_flags_data_widget($from_date, $to_date, $conn);
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
}
