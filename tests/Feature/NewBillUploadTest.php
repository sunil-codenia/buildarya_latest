<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class NewBillUploadTest extends TestCase
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
        DB::connection($this->conn)->table('new_bills_item_entry')->where('bill_id', 999)->delete();
        $existingBills = DB::connection($this->conn)->table('new_bill_entry')
            ->whereIn('bill_no', ['BILL-QA-999', 'BILL-QA-EDIT-999'])
            ->pluck('id')
            ->toArray();
        if (!empty($existingBills)) {
            DB::connection($this->conn)->table('new_bills_item_entry')->whereIn('bill_id', $existingBills)->delete();
        }
        DB::connection($this->conn)->table('bill_party_statement')->whereIn('bill_no', ['BILL-QA-999', 'BILL-QA-EDIT-999'])->delete();
        DB::connection($this->conn)->table('new_bill_entry')->whereIn('bill_no', ['BILL-QA-999', 'BILL-QA-EDIT-999'])->delete();
        DB::connection($this->conn)->table('new_bill_entry')->where('id', 999)->delete();
        DB::connection($this->conn)->table('bills_party')->where('id', 999)->delete();
        DB::connection($this->conn)->table('sites')->where('id', 999)->delete();
        DB::connection($this->conn)->table('bills_work')->where('id', 999)->delete();

        // Seed default admin role if empty or missing
        DB::connection($this->conn)->table('roles')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Admin',
                'is_superadmin' => 'yes',
                'add_duration' => 'anytime',
                'view_duration' => 'anytime',
                'initial_entry_status' => 'Approved',
                'entry_at_site' => 'all',
                'visiblity_at_site' => 'all',
            ]
        );

        // Seed default user
        DB::connection($this->conn)->table('users')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Admin User',
                'username' => 'admin',
                'pass' => 'password',
                'role_id' => 1,
                'status' => 'Active',
                'site_id' => '1',
            ]
        );

        // Seed site
        DB::connection($this->conn)->table('sites')->insert(
            ['id' => 999, 'name' => 'QA-SITE-999', 'status' => 'Active', 'address' => 'Test Site 999']
        );

        // Seed party
        DB::connection($this->conn)->table('bills_party')->insert(
            ['id' => 999, 'name' => 'QA-PARTY-999', 'status' => 'Active', 'address' => 'Test Party 999']
        );

        // Seed work
        DB::connection($this->conn)->table('bills_work')->insert(
            ['id' => 999, 'name' => 'QA-WORK-999']
        );
    }

    public function test_add_new_bill_with_multiple_attachments()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'anytime',
            'company_modules' => [4], // Billing module access
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
        ];

        // Mock uploads
        $file1 = UploadedFile::fake()->image('bill_img.jpg');
        $file2 = UploadedFile::fake()->create('bill_doc.pdf', 100, 'application/pdf');

        $postData = [
            'bill_site_id' => 999,
            'bill_party_id' => 999,
            'bill_no' => 'BILL-QA-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'item' => [999],
            'qty' => [10],
            'rate' => [150],
            'unit' => ['Pcs'],
            'remark' => 'QA Bill Upload Remark',
            'attachments' => [$file1, $file2]
        ];

        $response = $this->withSession($sessionData)
            ->post('/addnewbill', $postData);

        // Should redirect on success
        $response->assertStatus(302);

        // Verify it was inserted in database
        $bill = DB::connection($this->conn)->table('new_bill_entry')
            ->where('bill_no', 'BILL-QA-999')
            ->first();

        $this->assertNotNull($bill);
        $this->assertNotNull($bill->attachments);

        $attachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $attachments);
        $this->assertStringContainsString('images/app_images/company_rsgeotech/bill', $attachments[0]);
        $this->assertStringContainsString('.jpg', $attachments[0]);
        $this->assertStringContainsString('.pdf', $attachments[1]);

        // Clean up uploaded files from public dir
        foreach ($attachments as $path) {
            $absPath = public_path($path);
            if (file_exists($absPath)) {
                unlink($absPath);
            }
        }
    }

    public function test_edit_bill_delete_some_attachments_and_add_new_ones()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'add_duration' => 'anytime',
            'company_modules' => [4],
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
        ];

        // Seed a bill with 2 attachments
        $dummyPath1 = 'images/app_images/company_rsgeotech/bill/dummy1.jpg';
        $dummyPath2 = 'images/app_images/company_rsgeotech/bill/dummy2.pdf';
        
        // Ensure directories exist
        @mkdir(dirname(public_path($dummyPath1)), 0777, true);
        file_put_contents(public_path($dummyPath1), 'dummy content 1');
        file_put_contents(public_path($dummyPath2), 'dummy content 2');

        DB::connection($this->conn)->table('new_bill_entry')->insert([
            'id' => 999,
            'bill_no' => 'BILL-QA-EDIT-999',
            'party_id' => 999,
            'site_id' => 999,
            'billdate' => '2026-07-06',
            'bill_period' => '2026-07-01 to 2026-07-06',
            'status' => 'Pending',
            'amount' => 1500,
            'remark' => 'Original Remark',
            'user_id' => 1,
            'attachments' => json_encode([$dummyPath1, $dummyPath2])
        ]);

        // Mock upload of a new attachment
        $newFile = UploadedFile::fake()->image('new_img.png');

        $postData = [
            'id' => 999,
            'bill_site_id' => 999,
            'bill_party_id' => 999,
            'bill_no' => 'BILL-QA-EDIT-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'item' => [999],
            'qty' => [10],
            'rate' => [150],
            'unit' => ['Pcs'],
            'remark' => 'Updated Remark',
            // Keep dummyPath1 but delete dummyPath2 by omitting it from existing_attachments
            'existing_attachments' => [$dummyPath1],
            'attachments' => [$newFile]
        ];

        $response = $this->withSession($sessionData)
            ->post('/updateEditBill', $postData);

        $response->assertStatus(302);

        // Verify database state
        $bill = DB::connection($this->conn)->table('new_bill_entry')->find(999);
        $this->assertNotNull($bill);

        $attachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $attachments);
        $this->assertContains($dummyPath1, $attachments);
        $this->assertNotContains($dummyPath2, $attachments);

        // Verify dummyPath2 was deleted from filesystem
        $this->assertFalse(file_exists(public_path($dummyPath2)));
        $this->assertTrue(file_exists(public_path($dummyPath1)));

        // Clean up remaining files
        foreach ($attachments as $path) {
            $absPath = public_path($path);
            if (file_exists($absPath)) {
                unlink($absPath);
            }
        }
    }
}
