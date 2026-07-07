<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class NewEnhancementsTest extends TestCase
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

    public function test_security_headers_present()
    {
        $response = $this->get('/');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Strict-Transport-Security');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'no-referrer-when-downgrade');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_add_doc_head_option_validation()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [1, 11],
        ];

        // Send a scalar string instead of an array for 'name'
        $response = $this->withSession($sessionData)
            ->post('/adddocheadoption', [
                'doc_head_id' => 1,
                'name' => 'not-an-array-string'
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['name']);
    }

    public function test_site_transfer_validation()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [1, 11],
        ];

        // Send invalid payload missing required fields (e.g. from_site_id is missing, passing from_site instead)
        $response = $this->withSession($sessionData)
            ->post('/siteToSiteBalanceTransfer', [
                'to_site_id' => 2,
                'amount' => 500,
                'date' => '2026-06-26'
            ]);

        $response->assertRedirect('/sites');
        $response->assertSessionHasErrors(['from_site_id']);
    }

    public function test_material_supplier_default_status_is_active()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'company_modules' => [3],
        ];

        // Delete any existing test suppliers
        DB::connection($this->conn)->table('material_supplier')->where('name', 'TEST-SUPPLIER-123')->delete();

        $response = $this->withSession($sessionData)
            ->post('/addmaterialsupplier', [
                'name' => 'TEST-SUPPLIER-123',
                'address' => 'Test Address',
                'gstin' => '1234567890',
                'bank_ac' => '111111111111',
                'bank_ifsc' => 'TEST0001234',
                'bank_name' => 'Test Bank',
                'bank_ac_holder' => 'Holder',
                'cost_category_id' => 1,
            ]);

        $response->assertRedirect('/materialsupplier');

        $this->assertDatabaseHas('material_supplier', [
            'name' => 'TEST-SUPPLIER-123',
            'status' => 'Active',
        ], $this->conn);
    }
}
