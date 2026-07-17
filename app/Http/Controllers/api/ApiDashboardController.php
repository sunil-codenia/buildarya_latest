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
                $site_id = $site_id = $req_site_id;
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
     * Get Sales Invoices Data
     */
    public function salesInvoices(Request $request)
    {
        $user = $request->user();
        $filter_type = $request->get('date_filter', 'this_year');
        $site_id = $request->get('site_id', 'all');
        $filter_dates = get_dashboard_filter_dates($filter_type, $request->get('from_date'), $request->get('to_date'));
        
        if ($site_id == 'all') {
            $data = get_company_sales_invoices_chart_widget($filter_dates['start'], $filter_dates['end']);
        } else {
            $data = get_site_sales_invoices_chart_widget($site_id, $filter_dates['start'], $filter_dates['end']);
        }

        return response()->json(['status' => 'Ok', 'data' => $data]);
    }

    /**
     * Get Payment Vouchers Summary & Trend
     */
    public function paymentVouchers(Request $request)
    {
        $user = $request->user();
        $filter_type = $request->get('date_filter', 'this_year');
        $site_id = $request->get('site_id', 'all');
        $filter_dates = get_dashboard_filter_dates($filter_type, $request->get('from_date'), $request->get('to_date'));
        
        if ($site_id == 'all') {
            $flags = get_company_pending_flags_data_widget($filter_dates['start'], $filter_dates['end']);
            $trend = get_company_payment_voucher_chart_widget($filter_dates['start'], $filter_dates['end']);
        } else {
            $flags = get_pending_flags_data_widget($site_id, $filter_dates['start'], $filter_dates['end']);
            $trend = get_site_payment_voucher_chart_widget($site_id, $filter_dates['start'], $filter_dates['end']);
        }

        return response()->json([
            'status' => 'Ok', 
            'data' => [
                'pending_vouchers' => $flags['pending_pv'] ?? 0,
                'unpaid_vouchers' => $flags['unpaid_pv'] ?? 0,
                'trend' => $trend
            ]
        ]);
    }

    /**
     * Get Detailed Expenses Breakdown
     */
    public function expenses(Request $request)
    {
        $user = $request->user();
        $filter_type = $request->get('date_filter', 'this_year');
        $site_id = $request->get('site_id', 'all');
        $filter_dates = get_dashboard_filter_dates($filter_type, $request->get('from_date'), $request->get('to_date'));
        
        if ($site_id == 'all') {
            $trend = get_company_monthlyExpensesFormatted_chart_widget($filter_dates['start'], $filter_dates['end']);
            $heads = get_company_monthlyExpenses_chart_head_table($filter_dates['start'], $filter_dates['end']);
        } else {
            $trend = get_monthlyExpensesFormatted_chart_widget($site_id, $filter_dates['start'], $filter_dates['end']);
            $heads = get_monthlyExpenses_chart_head_table($site_id, $filter_dates['start'], $filter_dates['end']);
        }

        return response()->json(['status' => 'Ok', 'data' => ['trend' => $trend, 'heads' => $heads]]);
    }

    /**
     * Get Site Bills Data
     */
    public function bills(Request $request)
    {
        $user = $request->user();
        $filter_type = $request->get('date_filter', 'this_year');
        $site_id = $request->get('site_id', 'all');
        $filter_dates = get_dashboard_filter_dates($filter_type, $request->get('from_date'), $request->get('to_date'));
        
        if ($site_id == 'all') {
            $trend = get_company_site_bills_area_chart($filter_dates['start'], $filter_dates['end']);
            $work = get_company_site_bills_area_chart_work_table($filter_dates['start'], $filter_dates['end']);
        } else {
            $trend = get_site_bills_area_chart($site_id, $filter_dates['start'], $filter_dates['end']);
            $work = get_site_bills_area_chart_work_table($site_id, $filter_dates['start'], $filter_dates['end']);
        }

        return response()->json(['status' => 'Ok', 'data' => ['trend' => $trend, 'work_breakdown' => $work]]);
    }

    /**
     * Get Assets List
     */
    public function assets(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $assets = DB::connection($user_db_conn_name)->table('assets')
            ->leftJoin('asset_head', 'asset_head.id', '=', 'assets.head_id')
            ->leftJoin('sites', 'sites.id', '=', 'assets.site_id')
            ->select('assets.*', 'asset_head.name as head_name', 'sites.name as site_name')
            ->get();

        return response()->json([
            'status' => 'Ok', 
            'data' => $assets
        ]);
    }

    /**
     * Get Machinery List
     */
    public function machinery(Request $request)
    {
        $user_db_conn_name = $request->session()->get('comp_db_conn_name');
        $machinery = DB::connection($user_db_conn_name)->table('machinery_details')
            ->leftJoin('machinery_head', 'machinery_head.id', '=', 'machinery_details.head_id')
            ->leftJoin('sites', 'sites.id', '=', 'machinery_details.site_id')
            ->select('machinery_details.*', 'machinery_head.name as head_name', 'sites.name as site_name')
            ->get();

        return response()->json([
            'status' => 'Ok', 
            'data' => $machinery
        ]);
    }

    /**
     * Export Dashboard CSV
     */
    public function export(Request $request)
    {
        $web_dashboard = new \App\Http\Controllers\DashboardController();
        return $web_dashboard->exportCsv($request);
    }

    /**
     * Get SaaS Subscription Invoices from Shaarvik according to login
     */
    public function saasInvoices(Request $request)
    {
        $connName = config('database.default');

        $company = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $connName)
            ->orWhere('db_name', $connName)
            ->first();

        if (!$company) {
            return response()->json([
                'status' => 'Failed',
                'message' => 'Company not found for the current tenant.'
            ], 404);
        }

        $companyUid = $company->uid;
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');

        try {
            // Fetch from Shaarvik
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get("{$shaarvikUrl}/api/mysql/invoices", [
                'companyUid' => $companyUid,
            ]);

            if ($response->successful()) {
                $invoices = $response->json() ?: [];
                
                // Add pdf_url for the mobile app to download/view the invoice
                $invoices = array_map(function($inv) {
                    $inv['pdf_url'] = url("/api/v1/dashboard/saas-invoices/{$inv['id']}/download");
                    return $inv;
                }, $invoices);

                // Calculate Upcoming Payment
                $latestInvoice = null;
                foreach ($invoices as $inv) {
                    if (!empty($inv['subscription_end_date'])) {
                        $latestInvoice = $inv;
                        break;
                    }
                }

                if ($latestInvoice) {
                    $billingCycle = strtolower($latestInvoice['billing_cycle'] ?? 'monthly');
                    $cycleMultiplier = ($billingCycle === 'yearly' || $billingCycle === 'annually') ? 12 : (($billingCycle === 'quarterly') ? 3 : 1);
                    $extraUsers = isset($company->extra_users) ? (int)$company->extra_users : 0;
                    $extraSites = isset($company->extra_sites) ? (int)$company->extra_sites : 0;
                    $addonAmount = (($extraUsers * 100) + ($extraSites * 200)) * $cycleMultiplier;
                    
                    $nextAmount = (float)($latestInvoice['subscription_amount'] ?? $latestInvoice['amount']);
                    
                    $newStartDateRaw = $latestInvoice['subscription_end_date'];
                    $newStartDateCarbon = \Carbon\Carbon::parse($newStartDateRaw);
                    $subId = $latestInvoice['subscription_id'] ?? rand(1000, 9999);
                    $proformaNumber = 'PRO-' . $newStartDateCarbon->format('Ym') . '-' . str_pad($subId, 4, '0', STR_PAD_LEFT);
                    
                    $upcomingInvoice = [
                        'id' => 'upcoming',
                        'invoice_number' => $proformaNumber,
                        'plan_name' => ($latestInvoice['subscription_plan'] ?? 'SaaS Subscription') . ' (Next Payment)',
                        'invoice_date' => \Carbon\Carbon::parse($latestInvoice['subscription_start_date'])->format('Y-m-d'),
                        'due_date' => \Carbon\Carbon::parse($latestInvoice['subscription_end_date'])->format('Y-m-d'),
                        'amount' => $nextAmount,
                        'final_amount' => $nextAmount,
                        'paid_amount' => 0,
                        'balance_amount' => $nextAmount,
                        'status' => 'Pending',
                        'pdf_url' => null,
                        'is_upcoming' => true,
                        'subscription_id' => $latestInvoice['subscription_id'] ?? null,
                        'extra_users' => $extraUsers,
                        'extra_sites' => $extraSites,
                        'addon_amount' => $addonAmount
                    ];
                    array_unshift($invoices, $upcomingInvoice);
                }

                return response()->json([
                    'status' => 'Ok',
                    'data' => $invoices
                ]);
            }

            if ($response->status() == 400) {
                // Fallback for older Shaarvik API using companyId and client-side filtering
                $fallbackResponse = \Illuminate\Support\Facades\Http::timeout(15)->get("{$shaarvikUrl}/api/mysql/invoices", [
                    'companyId' => 1,
                ]);

                if ($fallbackResponse->successful()) {
                    $allInvoices = $fallbackResponse->json() ?: [];
                    $companyName = $company->name;
                    $companyEmail = $company->email;

                    $filtered = array_values(array_filter($allInvoices, function ($inv) use ($companyUid, $companyName, $companyEmail) {
                        $clientName = strtolower($inv['client']['name'] ?? $inv['client_name'] ?? '');
                        $clientEmail = strtolower($inv['client']['email'] ?? $inv['client_email'] ?? '');
                        $uidLower = strtolower($companyUid);
                        $nameLower = strtolower($companyName);
                        $emailLower = strtolower($companyEmail);

                        return $clientName === $uidLower
                            || (!empty($nameLower) && $clientName === $nameLower)
                            || (!empty($emailLower) && !empty($clientEmail) && $clientEmail === $emailLower);
                    }));

                    $filtered = array_map(function($inv) {
                        $inv['pdf_url'] = url("/api/v1/dashboard/saas-invoices/{$inv['id']}/download");
                        return $inv;
                    }, $filtered);

                    // Calculate Upcoming Payment
                    $latestInvoice = null;
                    foreach ($filtered as $inv) {
                        if (!empty($inv['subscription_end_date'])) {
                            $latestInvoice = $inv;
                            break;
                        }
                    }

                    if ($latestInvoice) {
                        $billingCycle = strtolower($latestInvoice['billing_cycle'] ?? 'monthly');
                        $cycleMultiplier = ($billingCycle === 'yearly' || $billingCycle === 'annually') ? 12 : (($billingCycle === 'quarterly') ? 3 : 1);
                        $extraUsers = isset($company->extra_users) ? (int)$company->extra_users : 0;
                        $extraSites = isset($company->extra_sites) ? (int)$company->extra_sites : 0;
                        $addonAmount = (($extraUsers * 100) + ($extraSites * 200)) * $cycleMultiplier;
                        
                        $nextAmount = (float)($latestInvoice['subscription_amount'] ?? $latestInvoice['amount']);
                        
                        $newStartDateRaw = $latestInvoice['subscription_end_date'];
                        $newStartDateCarbon = \Carbon\Carbon::parse($newStartDateRaw);
                        $subId = $latestInvoice['subscription_id'] ?? rand(1000, 9999);
                        $proformaNumber = 'PRO-' . $newStartDateCarbon->format('Ym') . '-' . str_pad($subId, 4, '0', STR_PAD_LEFT);
                        
                        $upcomingInvoice = [
                            'id' => 'upcoming',
                            'invoice_number' => $proformaNumber,
                            'plan_name' => ($latestInvoice['subscription_plan'] ?? 'SaaS Subscription') . ' (Next Payment)',
                            'invoice_date' => \Carbon\Carbon::parse($latestInvoice['subscription_start_date'])->format('Y-m-d'),
                            'due_date' => \Carbon\Carbon::parse($latestInvoice['subscription_end_date'])->format('Y-m-d'),
                            'amount' => $nextAmount,
                            'final_amount' => $nextAmount,
                            'paid_amount' => 0,
                            'balance_amount' => $nextAmount,
                            'status' => 'Pending',
                            'pdf_url' => null,
                            'is_upcoming' => true,
                            'subscription_id' => $latestInvoice['subscription_id'] ?? null,
                            'extra_users' => $extraUsers,
                            'extra_sites' => $extraSites,
                            'addon_amount' => $addonAmount
                        ];
                        array_unshift($filtered, $upcomingInvoice);
                    }

                    return response()->json([
                        'status' => 'Ok',
                        'data' => $filtered
                    ]);
                }
            }

            return response()->json([
                'status' => 'Failed',
                'message' => 'Failed to retrieve invoices from Shaarvik.',
                'error' => $response->body()
            ], $response->status());

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("SaaS Invoices API Connection failed: " . $e->getMessage());
            return response()->json([
                'status' => 'Error',
                'message' => 'Could not connect to Shaarvik service to fetch invoices.'
            ], 502);
        }
    }

    /**
     * Download SaaS Invoice PDF (API)
     */
    public function downloadPdf(Request $request, $id)
    {
        $connName = config('database.default');
        $company = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $connName)
            ->orWhere('db_name', $connName)
            ->first();

        if (!$company) {
            return response()->json(['status' => 'Failed', 'message' => 'Company not found'], 404);
        }

        $companyUid = $company->uid;
        $shaarvikUrl = rtrim(env('SHAARVIK_URL', 'https://shaarviktechnologies.com'), '/');

        try {
            $invoices = [];
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get("{$shaarvikUrl}/api/mysql/invoices", [
                'companyUid' => $companyUid,
            ]);

            if ($response->successful()) {
                $invoices = $response->json() ?: [];
            } else if ($response->status() == 400) {
                $fallbackResponse = \Illuminate\Support\Facades\Http::timeout(15)->get("{$shaarvikUrl}/api/mysql/invoices", [
                    'companyId' => 1,
                ]);
                if ($fallbackResponse->successful()) {
                    $invoices = $fallbackResponse->json() ?: [];
                }
            }

            if (!empty($invoices)) {
                $invoice = collect($invoices)->firstWhere('id', $id);
                if (!$invoice) {
                    return response()->json(['status' => 'Failed', 'message' => 'Invoice not found'], 404);
                }

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('layouts.invoice_pdf', compact('invoice'));
                return $pdf->download("Invoice_{$invoice['invoice_number']}.pdf");
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to generate API invoice PDF: " . $e->getMessage());
        }

        return response()->json(['status' => 'Error', 'message' => 'Could not download invoice PDF at this time'], 500);
    }
}
