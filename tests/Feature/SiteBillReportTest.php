<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class SiteBillReportTest extends TestCase
{
    protected $conn = 'company_rsgeotech';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.connections.company_rsgeotech' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'company_rsgeotech',
                'username' => 'root',
                'password' => '',
            ]
        ]);

        // Clean up previous test runs to prevent duplicate key constraint violations
        DB::connection($this->conn)->table('bill_party_statement')->where('id', 888)->delete();
        DB::connection($this->conn)->table('bill_party_statement')->where('bill_no', 888)->delete();
        DB::connection($this->conn)->table('new_bill_entry')->where('id', 888)->delete();
        DB::connection($this->conn)->table('bills_party')->where('id', 888)->delete();
        DB::connection($this->conn)->table('sites')->where('id', 888)->delete();

        // Seed site
        DB::connection($this->conn)->table('sites')->insert(
            ['id' => 888, 'name' => 'QA-SITE-888', 'status' => 'Active', 'address' => 'Test Site 888']
        );

        // Seed party
        DB::connection($this->conn)->table('bills_party')->insert(
            ['id' => 888, 'name' => 'QA-PARTY-888', 'status' => 'Active', 'address' => 'Test Party 888']
        );

        // Seed some bills
        DB::connection($this->conn)->table('new_bill_entry')->insert(
            [
                'id' => 888,
                'bill_no' => 'BILL-888',
                'party_id' => 888,
                'site_id' => 888,
                'billdate' => '2026-06-25',
                'bill_period' => '2026-06-01 to 2026-06-25',
                'status' => 'Approved',
                'amount' => 5000,
                'remark' => 'QA TEST BILL',
                'user_id' => 1
            ]
        );

        // Seed statement
        DB::connection($this->conn)->table('bill_party_statement')->insert(
            [
                'id' => 888,
                'party_id' => 888,
                'type' => 'Debit',
                'particular' => 'BILL-888',
                'bill_no' => 888,
                'create_datetime' => '2026-06-25 12:00:00'
            ]
        );
    }

    public function test_site_bill_report_type_7_excel_export()
    {
        Excel::fake();

        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [4], // Billing module access
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
        ];

        $postData = [
            'Report_Type' => 1,
            'type' => 7,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'site_id' => 888,
            'party_id' => 888,
        ];

        $response = $this->withSession($sessionData)
            ->post('/sitebillreport', $postData);

        $response->assertStatus(200);

        Excel::assertDownloaded('Bill Party Report At Particular Site (2026-06-01 TO 2026-06-30).xlsx');
    }

    public function test_site_bill_report_type_11_excel_export()
    {
        Excel::fake();

        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [4], // Billing module access
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
        ];

        $postData = [
            'Report_Type' => 1,
            'type' => 11,
            'party_id' => 888,
        ];

        $response = $this->withSession($sessionData)
            ->post('/sitebillreport', $postData);

        $response->assertStatus(200);

        Excel::assertDownloaded('Bill Party Statement-QA-PARTY-888.xlsx');
    }
}
