<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;

class VerifiedExpenseExport implements FromView
{
    use Exportable;
    protected $expenses;

    public function __construct($expenses)
    {
        $this->expenses = $expenses;
    }

    public function view(): View
    {
        return view('layouts.expense.exports.verified_export', [
            'expenses' => $this->expenses,
            'color' => session()->get('primary_color')[0] ?? '#49c5b6',
            'sec_color' => session()->get('secondary_color')[0] ?? '#54d4c6',
        ]);
    }
}
