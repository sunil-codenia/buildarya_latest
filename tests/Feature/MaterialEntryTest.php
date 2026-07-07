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

    public function test_add_new_material_with_multiple_images()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [3],
        ];

        $image1 = \Illuminate\Http\UploadedFile::fake()->image('test_image1.jpg');
        $image2 = \Illuminate\Http\UploadedFile::fake()->image('test_image2.jpg');
        $image3 = \Illuminate\Http\UploadedFile::fake()->image('test_image3.jpg');
        $image4 = \Illuminate\Http\UploadedFile::fake()->image('test_image4.jpg');
        $image5 = \Illuminate\Http\UploadedFile::fake()->image('test_image5.jpg');

        $postData = [
            'site_id' => [999],
            'supplier' => [999],
            'material_id' => [999],
            'unit' => [999],
            'qty' => [10],
            'vehical' => ['TEST-VEHICLE-IMG'],
            'remark' => ['TEST-REMARK-IMG'],
            'date' => [date('Y-m-d')],
            'image' => [$image1],
            'image2' => [$image2],
            'image3' => [$image3],
            'image4' => [$image4],
            'image5' => [$image5],
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewmaterial', $postData);

        $response->assertRedirect('/verified_material');

        $entry = DB::connection($this->conn)->table('material_entry')
            ->where('remark', 'TEST-REMARK-IMG')
            ->first();

        $this->assertNotNull($entry);
        $images = explode(',', $entry->image);
        $this->assertCount(5, $images);
        foreach ($images as $img) {
            $this->assertStringContainsString('images/app_images/company_rsgeotech/material/', $img);
        }
        $this->assertNull($entry->image2);
        $this->assertNull($entry->image3);
        $this->assertNull($entry->image4);
        $this->assertNull($entry->image5);

        // Cleanup files
        foreach ($images as $img) {
            if ($img && file_exists(public_path($img))) {
                unlink(public_path($img));
            }
        }
    }

    public function test_update_material_entry_clear_image2()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'all',
            'company_modules' => [3],
        ];

        // 1. Create a entry with images first
        $id = DB::connection($this->conn)->table('material_entry')->insertGetId([
            'site_id' => 999,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 10,
            'vehical' => 'TEST-VEHICLE',
            'remark' => 'TEST-REMARK-UPDATE',
            'date' => date('Y-m-d'),
            'image' => 'images/expense.png',
            'image2' => 'images/app_images/company_rsgeotech/material/test_temp2.jpg',
            'image3' => 'images/app_images/company_rsgeotech/material/test_temp3.jpg',
            'image4' => 'images/app_images/company_rsgeotech/material/test_temp4.jpg',
            'image5' => 'images/app_images/company_rsgeotech/material/test_temp5.jpg',
            'status' => 'Approved',
            'user_id' => 1,
        ]);

        // 2. Perform edit update with clear parameters
        $postData = [
            'id' => $id,
            'site_id' => 999,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 10,
            'vehical' => 'TEST-VEHICLE-UPDATED',
            'remark' => 'TEST-REMARK-UPDATE',
            'date' => date('Y-m-d'),
            'clear_image2' => '1',
            'clear_image3' => '1',
            'clear_image4' => '1',
            'clear_image5' => '1',
        ];

        $response = $this->withSession($sessionData)
            ->post('/updatematerialEntry', $postData);

        $response->assertRedirect('/verified_material');

        $entry = DB::connection($this->conn)->table('material_entry')
            ->where('id', $id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEquals('TEST-VEHICLE-UPDATED', $entry->vehical);
        // Verify that the images are removed from the single comma-separated image list
        $images = explode(',', $entry->image);
        $this->assertCount(1, $images);
        $this->assertEquals('images/expense.png', $images[0]);
    }
}

