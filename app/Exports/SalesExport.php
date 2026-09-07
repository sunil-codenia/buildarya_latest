<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;


class SalesExport implements FromView
{

    use Exportable;

    protected $user_db_conn_name;

    protected $report_code;
    protected $projectname;
    protected $partyname, $financial_year, $companyname, $headname;

    public function __construct($user_db_conn_name, $report_code = null,  $projectname = null, $partyname = null, $financial_year = null, $companyname = null, $headname = null)

    {
        $this->user_db_conn_name = $user_db_conn_name;

        $this->partyname = $partyname;
        $this->report_code = $report_code;
        $this->projectname = $projectname;
        $this->financial_year = $financial_year;
        $this->companyname = $companyname;
        $this->headname = $headname;
    }

    public function view(): View
    {
      
        if ($this->report_code == 1) {

            $result = array();
            $heads = [
                "Create Datetime",
                "Invoice No.",
                "Bill Date",
                "Project Name",
                "Party Name",
                "Company Name",
                "Invoice FY",
                                "Entry FY",
                "Taxable Value",
                "GST Rate",
                "Final Amount"
            ];
            $dedaddHeaders = DB::connection($this->user_db_conn_name)
                ->table('sales_dedadd')->orderBy('type', 'asc')->get();
            foreach ($dedaddHeaders as $dedh) {
                $heads[] = $dedh->name;
            }
            $heads[] = "Balance";
            $column_count = count($heads)+1;
            $invoices = DB::connection($this->user_db_conn_name)
                ->table('sales_invoice as si')
                ->join('sales_company as sc', 'sc.id', '=', 'si.company_id')
                ->join('sales_project as sp', 'sp.id', '=', 'si.project_id')
                ->join('sales_party as sparty', 'sparty.id', '=', 'si.party_id')
                ->select('si.*', 'sc.name as company_name', 'sp.name as project_name', 'sparty.name as party_name')
                ->where('si.party_id', $this->partyname)
                ->orderBy('si.date', 'desc')->get();



            foreach ($invoices as $invoice) {
                $res = array();
                $dedaddTypes = DB::connection($this->user_db_conn_name)->table('sales_manage_invoice')
                    ->join('sales_dedadd', 'sales_manage_invoice.type_id', '=', 'sales_dedadd.id')
                    ->where('sales_manage_invoice.invoice_id', $invoice->id)
                    ->select('sales_manage_invoice.*', 'sales_dedadd.name', 'sales_dedadd.type')
                    ->orderBy('sales_manage_invoice.date', 'asc')
                    ->get();

                $baseRow = [
                    $invoice->create_datetime,
                    $invoice->invoice_no,
                    $invoice->date,
                    $invoice->project_name,
                    $invoice->party_name,
                    $invoice->company_name,
                    
                    $invoice->financial_year,
                    "",
                    $invoice->taxable_value,
                    $invoice->gst_rate,
                    $invoice->amount
                ];
                $balance = $invoice->amount;
                foreach ($dedaddHeaders as $dedh) {
                    $baseRow[] = "";
                }

                $baseRow[] = $balance;
                array_push($result, $baseRow);

                foreach ($dedaddTypes as $type) {
                    $row = [
                        "",
                        $invoice->invoice_no,
                        $type->date,
                        $invoice->project_name,
                        $invoice->party_name,
                        $invoice->company_name,
                         $invoice->financial_year,
                        getFinancialYearByDate($type->date),

                        "",
                        "",
                        ""
                    ];

                    foreach ($dedaddHeaders as $dedh) {
                        if ($dedh->id == $type->type_id) {
                            if ($type->type == "ded") {
                                $balance -= $type->amount;
                            } elseif ($type->type == "add") {
                                $balance += $type->amount;
                            }
                            $row[] = $type->amount;
                        } else {
                            $row[] = "";
                        }
                    }

                    $row[] = $balance;
                    array_push($result, $row);
                }
                if($invoice->id != $invoices->last()->id){
                    $blank_row = array_fill(0, count($heads)+11, "");
                    array_push($result, $blank_row);
                }

            }
      
          
            $party_name = DB::connection($this->user_db_conn_name)->table('sales_party')->where('id', $this->partyname)->first()->name;
            return view('layouts.sales.exports.accToParty', [
                'heads' => $heads,
                'body' => $result,
                'party_name' => $party_name,
                'column_count' => $column_count,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        } else if ($this->report_code == 2) {

            $result = array();
            $invoices = DB::connection($this->user_db_conn_name)
                ->table('sales_invoice as si')
                ->join('sales_company as sc', 'sc.id', '=', 'si.company_id')
                ->join('sales_project as sp', 'sp.id', '=', 'si.project_id')
                ->join('sales_party as sparty', 'sparty.id', '=', 'si.party_id')
                ->select('si.*', 'sc.name as company_name', 'sp.name as project_name', 'sparty.name as party_name')
                ->where('si.project_id', $this->projectname)
                ->orderBy('si.date', 'desc')->get();

            $heads = DB::connection($this->user_db_conn_name)
                ->table('sales_dedadd')->orderBy('type', 'asc')->get();
            foreach ($invoices as $invoice) {
                $res = array();
                array_push($res, $invoice->invoice_no);
                array_push($res, $invoice->company_name);
                array_push($res, $invoice->party_name);
                array_push($res, $invoice->financial_year);
                array_push($res, $invoice->status);
                array_push($res, $invoice->taxable_value);
                array_push($res, $invoice->amount);
                foreach ($heads as $head) {
                    $manage_invoice = DB::connection($this->user_db_conn_name)
                        ->table('sales_manage_invoice')->where('invoice_id', $invoice->id)->where('type_id', $head->id)->sum('amount');
                    array_push($res, $manage_invoice);
                }
                $balance = getSalesInvoiceBalance($invoice->id);
                array_push($res, $balance);
                array_push($result, $res);
            }
            $heads_res = array('Invoice No.', 'Company', 'Party', 'Financial Year', 'Status', 'Taxable Value', 'Amount');
            foreach ($heads as $head) {
                array_push($heads_res, $head->name);
            }
            array_push($heads_res, "Balance");
            $transposed = array_map(null, ...$result);


            $nonZeroColumns = [];
            foreach ($transposed as $index => $column) {
                // Skip the non-numeric fields (first columns before financial values)
                if ($index < 7 || $index >= count($heads_res) - 1) {
                    $nonZeroColumns[] = $index;
                    continue;
                }

                // Filter only numeric values from the column
                $numericValues = is_array($column)
                    ? array_filter($column, 'is_numeric')
                    : (is_numeric($column) ? [$column] : []);

                // Check if the numeric values are all zeros
                $allZeros = count(array_unique($numericValues)) === 1 && array_sum($numericValues) === 0;

                if (!$allZeros) { // Keep non-zero columns
                    $nonZeroColumns[] = $index;
                }
            }
            $filteredHeaders = array_values(array_intersect_key($heads_res, array_flip($nonZeroColumns)));

            $filteredInvoices = array_map(function ($invoice) use ($nonZeroColumns) {
                return array_values(array_intersect_key($invoice, array_flip($nonZeroColumns)));
            }, $result);
            $column_count = count($filteredHeaders);
            $project_name = DB::connection($this->user_db_conn_name)->table('sales_project')->where('id', $this->projectname)->first()->name;
            return view('layouts.sales.exports.accToProject', [
                'heads' => $filteredHeaders,
                'body' => $filteredInvoices,
                'project_name' => $project_name,
                'column_count' => $column_count + 1,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        } else if ($this->report_code == 3) {

            $result = array();
            $invoices = DB::connection($this->user_db_conn_name)
                ->table('sales_invoice as si')
                ->join('sales_company as sc', 'sc.id', '=', 'si.company_id')
                ->join('sales_project as sp', 'sp.id', '=', 'si.project_id')
                ->join('sales_party as sparty', 'sparty.id', '=', 'si.party_id')
                ->select('si.*', 'sc.name as company_name', 'sp.name as project_name', 'sparty.name as party_name')
                ->where('si.financial_year', $this->financial_year)
                ->orderBy('si.date', 'desc')->get();

            $heads = DB::connection($this->user_db_conn_name)
                ->table('sales_dedadd')->orderBy('type', 'asc')->get();
            foreach ($invoices as $invoice) {
                $res = array();
                array_push($res, $invoice->invoice_no);
                array_push($res, $invoice->company_name);
                array_push($res, $invoice->project_name);
                array_push($res, $invoice->party_name);
                array_push($res, $invoice->status);
                array_push($res, $invoice->taxable_value);
                array_push($res, $invoice->amount);
                foreach ($heads as $head) {
                    $manage_invoice = DB::connection($this->user_db_conn_name)
                        ->table('sales_manage_invoice')->where('invoice_id', $invoice->id)->where('type_id', $head->id)->sum('amount');
                    array_push($res, $manage_invoice);
                }
                $balance = getSalesInvoiceBalance($invoice->id);
                array_push($res, $balance);
                array_push($result, $res);
            }
            $heads_res = array('Invoice No.', 'Company', 'Project',  'Party', 'Status', 'Taxable Value', 'Amount');
            foreach ($heads as $head) {
                array_push($heads_res, $head->name);
            }
            array_push($heads_res, "Balance");
            $transposed = array_map(null, ...$result);


            $nonZeroColumns = [];
            foreach ($transposed as $index => $column) {
                // Skip the non-numeric fields (first columns before financial values)
                if ($index < 7 || $index >= count($heads_res) - 1) {
                    $nonZeroColumns[] = $index;
                    continue;
                }

                // Filter only numeric values from the column
                $numericValues = is_array($column)
                    ? array_filter($column, 'is_numeric')
                    : (is_numeric($column) ? [$column] : []);

                // Check if the numeric values are all zeros
                $allZeros = count(array_unique($numericValues)) === 1 && array_sum($numericValues) === 0;

                if (!$allZeros) { // Keep non-zero columns
                    $nonZeroColumns[] = $index;
                }
            }
            $filteredHeaders = array_values(array_intersect_key($heads_res, array_flip($nonZeroColumns)));

            $filteredInvoices = array_map(function ($invoice) use ($nonZeroColumns) {
                return array_values(array_intersect_key($invoice, array_flip($nonZeroColumns)));
            }, $result);
            $column_count = count($filteredHeaders);
            return view('layouts.sales.exports.accToFinancialYear', [
                'heads' => $filteredHeaders,
                'body' => $filteredInvoices,
                'financial_year' => $this->financial_year,
                'column_count' => $column_count + 1,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        } else if ($this->report_code == 4) {

            $result = array();
            $invoices = DB::connection($this->user_db_conn_name)
                ->table('sales_invoice as si')
                ->join('sales_company as sc', 'sc.id', '=', 'si.company_id')
                ->join('sales_project as sp', 'sp.id', '=', 'si.project_id')
                ->join('sales_party as sparty', 'sparty.id', '=', 'si.party_id')
                ->select('si.*', 'sc.name as company_name', 'sp.name as project_name', 'sparty.name as party_name')
                ->where('si.financial_year', $this->financial_year)
                ->where('si.company_id', $this->companyname)
                ->orderBy('si.date', 'desc')->get();

            $heads = DB::connection($this->user_db_conn_name)
                ->table('sales_dedadd')->orderBy('type', 'asc')->get();
            foreach ($invoices as $invoice) {
                $res = array();
                array_push($res, $invoice->invoice_no);
                array_push($res, $invoice->project_name);
                array_push($res, $invoice->party_name);
                array_push($res, $invoice->status);
                array_push($res, $invoice->taxable_value);
                array_push($res, $invoice->amount);
                foreach ($heads as $head) {
                    $manage_invoice = DB::connection($this->user_db_conn_name)
                        ->table('sales_manage_invoice')->where('invoice_id', $invoice->id)->where('type_id', $head->id)->sum('amount');
                    array_push($res, $manage_invoice);
                }
                $balance = getSalesInvoiceBalance($invoice->id);
                array_push($res, $balance);
                array_push($result, $res);
            }
            $heads_res = array('Invoice No.', 'Project',  'Party', 'Status', 'Taxable Value', 'Amount');
            foreach ($heads as $head) {
                array_push($heads_res, $head->name);
            }
            array_push($heads_res, "Balance");
            $transposed = array_map(null, ...$result);


            $nonZeroColumns = [];
            foreach ($transposed as $index => $column) {
                // Skip the non-numeric fields (first columns before financial values)
                if ($index < 6 || $index >= count($heads_res) - 1) {
                    $nonZeroColumns[] = $index;
                    continue;
                }

                // Filter only numeric values from the column
                $numericValues = is_array($column)
                    ? array_filter($column, 'is_numeric')
                    : (is_numeric($column) ? [$column] : []);

                // Check if the numeric values are all zeros
                $allZeros = count(array_unique($numericValues)) === 1 && array_sum($numericValues) === 0;

                if (!$allZeros) { // Keep non-zero columns
                    $nonZeroColumns[] = $index;
                }
            }
            $filteredHeaders = array_values(array_intersect_key($heads_res, array_flip($nonZeroColumns)));

            $filteredInvoices = array_map(function ($invoice) use ($nonZeroColumns) {
                return array_values(array_intersect_key($invoice, array_flip($nonZeroColumns)));
            }, $result);
            $column_count = count($filteredHeaders);
            $company_name = DB::connection($this->user_db_conn_name)->table('sales_company')->where('id', $this->companyname)->first()->name;
            return view('layouts.sales.exports.accToCompany', [
                'heads' => $filteredHeaders,
                'body' => $filteredInvoices,
                'financial_year' => $this->financial_year,
                'company_name' => $company_name,
                'column_count' => $column_count + 1,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        } else {

            $result = array();
            $head_id = $this->headname;
            $invoices = DB::connection($this->user_db_conn_name)
                ->table('sales_invoice as si')
                ->join('sales_company as sc', 'sc.id', '=', 'si.company_id')
                ->join('sales_project as sp', 'sp.id', '=', 'si.project_id')
                ->join('sales_party as sparty', 'sparty.id', '=', 'si.party_id')
                ->select('si.*', 'sc.name as company_name', 'sp.name as project_name', 'sparty.name as party_name')
                ->where('si.financial_year', $this->financial_year)
                ->whereIn('si.id', function ($query) use ($head_id) {
                    $query->select('smi.invoice_id')
                        ->from('sales_manage_invoice as smi')
                        ->where('smi.type_id', $head_id);
                })
                ->orderBy('si.date', 'desc')->get();


            $head_name = DB::connection($this->user_db_conn_name)
                ->table('sales_dedadd')->where('id', $this->headname)->first();
            foreach ($invoices as $invoice) {
                $res = array();
                array_push($res, $invoice->invoice_no);
                array_push($res, $invoice->project_name);
                array_push($res, $invoice->party_name);
                array_push($res, $invoice->company_name);
                array_push($res, $invoice->status);
                array_push($res, $invoice->taxable_value);
                array_push($res, $invoice->amount);
                $balance = getSalesInvoiceBalance($invoice->id);
                array_push($res, $balance);
                $manage_invoice = DB::connection($this->user_db_conn_name)
                    ->table('sales_manage_invoice')->where('invoice_id', $invoice->id)->where('type_id', $this->headname)->sum('amount');
                array_push($res, $manage_invoice);


                array_push($result, $res);
            }
            $heads_res = array('Invoice No.', 'Project',  'Party', 'Company', 'Status', 'Taxable Value', 'Amount', 'Balance', $head_name->name);
            $column_count = count($heads_res);
            return view('layouts.sales.exports.accToHead', [
                'heads' => $heads_res,
                'body' => $result,
                'head_name' => $head_name->name,
                'financial_year' => $this->financial_year,
                'column_count' => $column_count + 1,
                'color' => session()->get('primary_color')[0],
                'sec_color' => session()->get('secondry_color')[0],
            ]);
        }
    }
}
