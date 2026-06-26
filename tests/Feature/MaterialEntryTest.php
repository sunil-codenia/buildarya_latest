<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MaterialEntryTest extends TestCase
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

        // Ensure materials, sites, units exist or seed them
        DB::connection($this->conn)->table('materials')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA-TEST-MAT']
        );
        DB::connection($this->conn)->table('sites')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA-TEST-SITE', 'status' => 'Active', 'address' => 'Test Address']
        );
        DB::connection($this->conn)->table('units')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA-TEST-UNIT']
        );
        DB::connection($this->conn)->table('material_supplier')->updateOrInsert(
            ['id' => 999],
            [
                'name' => 'QA-TEST-SUPPLIER',
                'status' => 'Active',
                'address' => 'Test Address',
                'gstin' => 'GSTIN123',
                'bank_ac' => '123',
                'bank_ifsc' => 'IFSC',
                'bank_name' => 'Bank',
                'bank_ac_holder' => 'Holder'
            ]
        );

        // Clean up test data to make runs idempotent
        DB::connection($this->conn)->table('material_entry')->where('remark', 'LIKE', '%TEST-%')->delete();
        DB::connection($this->conn)->table('material_stock_record')->where('material_id', 999)->delete();
        DB::connection($this->conn)->table('material_stock_record')->where('material_id', 999999)->delete();
        DB::connection($this->conn)->table('material_stock_transactions')->where('material_id', 999)->delete();
        DB::connection($this->conn)->table('material_stock_transactions')->where('material_id', 999999)->delete();
    }

    public function test_add_new_material_success_flow()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [3], // Material module access
        ];

        $postData = [
            'site_id' => [999],
            'supplier' => [999],
            'material_id' => [999],
            'unit' => [999],
            'qty' => [10],
            'vehical' => ['TEST-VEHICLE'],
            'remark' => ['TEST-REMARK'],
            'date' => [date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewmaterial', $postData);

        $response->assertRedirect('/verified_material');

        // Check it exists in the database
        $this->assertDatabaseHas('material_entry', [
            'remark' => 'TEST-REMARK',
            'qty' => 10,
            'status' => 'Approved',
        ], $this->conn);

        // Check that stock transactions and record were updated
        $this->assertDatabaseHas('material_stock_transactions', [
            'material_id' => 999,
            'site_id' => 999,
            'qty' => 10,
            'type' => 'IN',
        ], $this->conn);

        $this->assertDatabaseHas('material_stock_record', [
            'material_id' => 999,
            'site_id' => 999,
            'qty' => 10,
        ], $this->conn);
    }

    public function test_add_new_material_transaction_rollback_on_invalid_material()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [3],
        ];

        $postData = [
            'site_id' => [999],
            'supplier' => [999],
            'material_id' => [999999], // Invalid material ID!
            'unit' => [999],
            'qty' => [10],
            'vehical' => ['TEST-VEHICLE'],
            'remark' => ['TEST-REMARK-FAIL'],
            'date' => [date('Y-m-d')],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewmaterial', $postData);

        $response->assertRedirect('/verified_material');
        $response->assertSessionHas('error');

        // Verify database has NO entry for the failed remark
        $this->assertDatabaseMissing('material_entry', [
            'remark' => 'TEST-REMARK-FAIL',
        ], $this->conn);

        // Verify stock was not changed
        $this->assertDatabaseMissing('material_stock_record', [
            'material_id' => 999999,
            'site_id' => 999,
        ], $this->conn);
    }
}
