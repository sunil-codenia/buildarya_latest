<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ExpenseEntryTest extends TestCase
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

        // Seed default admin role if empty or missing
        DB::connection($this->conn)->table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Admin',
                'is_superadmin' => 'yes',
                'add_duration' => 'all',
                'view_duration' => 'all',
                'initial_entry_status' => 'Approved',
                'entry_at_site' => 'all',
                'visiblity_at_site' => 'all',
            ]
        );
    }

    public function test_add_new_expense_success_flow()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all', // to bypass date checks
            'company_modules' => [2], // Expense module access
        ];

        // Clean up previous test expenses
        DB::connection($this->conn)->table('expenses')->where('particular', 'LIKE', '%TEST-%')->delete();

        // 1. Single valid entry
        $postData = [
            'site_id' => [14],
            'party_id' => ['1||expense'],
            'head_id' => [26],
            'particular' => ['TEST-Single'],
            'amount' => [150],
            'remark' => ['Test Single Remark'],
            'date' => [date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewExpenses', $postData);

        $response->assertRedirect('/verified_expense');

        // Check it exists in the database
        $this->assertDatabaseHas('expenses', [
            'particular' => 'TEST-Single',
            'amount' => 150,
            'party_id' => '1',
            'party_type' => 'expense',
            'status' => 'Approved',
        ], $this->conn);
    }

    public function test_add_new_expense_numeric_fallback()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [2], // Expense module access
        ];

        DB::connection($this->conn)->table('expenses')->where('particular', 'LIKE', '%TEST-%')->delete();

        // 2. Numeric fallback party_id (without delimiter)
        $postData = [
            'site_id' => [14],
            'party_id' => ['1'], // Numeric only!
            'head_id' => [26],
            'particular' => ['TEST-NumericFallback'],
            'amount' => [200],
            'remark' => ['Test Numeric Fallback Remark'],
            'date' => [date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewExpenses', $postData);

        $response->assertRedirect('/verified_expense');

        $this->assertDatabaseHas('expenses', [
            'particular' => 'TEST-NumericFallback',
            'amount' => 200,
            'party_id' => '1',
            'party_type' => 'expense', // Fallback value
        ], $this->conn);
    }

    public function test_add_new_expense_bulk_with_machinery_head()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [2], // Expense module access
        ];

        DB::connection($this->conn)->table('expenses')->where('particular', 'LIKE', '%TEST-%')->delete();

        // Bulk entry: one ordinary head (26) and one machinery head (28)
        $postData = [
            'site_id' => [14, 14],
            'party_id' => ['1||expense', '1||expense'],
            'head_id' => [26, 28], // 26 is ordinary, 28 is machinery
            'particular' => ['TEST-Ordinary-Bulk', 'TEST-Machinery-Bulk'],
            'amount' => [100, 500],
            'remark' => ['Remark 1', 'Remark 2'],
            'date' => [date('Y-m-d'), date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewExpenses', $postData);

        // Redirects to /verified_expense because initial status is Approved
        $response->assertRedirect('/verified_expense');

        // Ordinary head should be Approved
        $this->assertDatabaseHas('expenses', [
            'particular' => 'TEST-Ordinary-Bulk',
            'status' => 'Approved',
        ], $this->conn);

        // Machinery head should be Pending
        $this->assertDatabaseHas('expenses', [
            'particular' => 'TEST-Machinery-Bulk',
            'status' => 'Pending',
        ], $this->conn);
    }

    public function test_add_new_expense_transaction_rollback_on_invalid_party()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [2], // Expense module access
        ];

        DB::connection($this->conn)->table('expenses')->where('particular', 'LIKE', '%TEST-%')->delete();

        // Try to insert with an invalid party ID (e.g. 999999)
        $postData = [
            'site_id' => [14],
            'party_id' => ['999999||expense'], // Invalid party!
            'head_id' => [26],
            'particular' => ['TEST-Rollback'],
            'amount' => [300],
            'remark' => ['Should be rolled back'],
            'date' => [date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewExpenses', $postData);

        // Since it failed, it redirects with error
        $response->assertRedirect('/verified_expense');
        $response->assertSessionHas('error');

        // Verify that the row was NOT created due to transaction rollback
        $this->assertDatabaseMissing('expenses', [
            'particular' => 'TEST-Rollback',
        ], $this->conn);
    }
}
