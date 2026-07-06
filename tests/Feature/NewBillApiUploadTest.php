<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class NewBillApiUploadTest extends TestCase
{
    private $tokenValue;
    private $tokenId;
    private $testUserId = 999;
    private $connName = 'company_rsgeotech';

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Set up config for tenant database
        config([
            'database.connections.company_rsgeotech' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'company_rsgeotech',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

        // 2. Ensure company record exists in the main database
        $exists = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $this->connName)
            ->exists();

        if (!$exists) {
            DB::connection('mysql')->table('companies')->insert([
                'name' => 'RSGeotech',
                'uid' => 'rsgeotech',
                'db_conn_name' => $this->connName,
                'db_name' => 'company_rsgeotech',
                'db_host' => '127.0.0.1',
                'db_port' => '3306',
                'db_pass' => '',
                'username' => 'root',
                'status' => 'Active',
                'max_users' => 100,
                'max_sites' => 50,
            ]);
        }

        // Clean up previous test runs
        DB::connection($this->connName)->table('new_bills_item_entry')->where('bill_id', 999)->delete();
        $existingBills = DB::connection($this->connName)->table('new_bill_entry')
            ->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])
            ->pluck('id')
            ->toArray();
        if (!empty($existingBills)) {
            DB::connection($this->connName)->table('new_bills_item_entry')->whereIn('bill_id', $existingBills)->delete();
        }
        DB::connection($this->connName)->table('bill_party_statement')->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])->delete();
        DB::connection($this->connName)->table('new_bill_entry')->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])->delete();
        DB::connection($this->connName)->table('new_bill_entry')->where('id', 999)->delete();
        DB::connection($this->connName)->table('bills_party')->where('id', 999)->delete();
        DB::connection($this->connName)->table('sites')->where('id', 999)->delete();
        DB::connection($this->connName)->table('bills_work')->where('id', 999)->delete();

        // 3. Ensure role and test user exist in tenant DB
        DB::connection($this->connName)->table('roles')->updateOrInsert(
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

        DB::connection($this->connName)->table('users')->updateOrInsert(
            ['id' => $this->testUserId],
            [
                'name' => 'API Admin User',
                'username' => 'apiadmin',
                'pass' => 'adminpass',
                'role_id' => 1,
                'status' => 'Active',
                'mobile_only' => 'no',
                'site_id' => 'all'
            ]
        );

        // Seed site, party, and work
        DB::connection($this->connName)->table('sites')->insert(
            ['id' => 999, 'name' => 'QA-SITE-999', 'status' => 'Active', 'address' => 'Test Site 999']
        );
        DB::connection($this->connName)->table('bills_party')->insert(
            ['id' => 999, 'name' => 'QA-PARTY-999', 'status' => 'Active', 'address' => 'Test Party 999']
        );
        DB::connection($this->connName)->table('bills_work')->insert(
            ['id' => 999, 'name' => 'QA-WORK-999']
        );

        // 4. Insert Sanctum token
        $this->tokenValue = Str::random(40);
        $hashedToken = hash('sha256', $this->tokenValue);
        $this->tokenId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => $this->testUserId,
            'name' => $this->connName,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('mysql')->table('personal_access_tokens')
            ->where('id', $this->tokenId)
            ->delete();

        // Clean up DB
        DB::connection($this->connName)->table('new_bills_item_entry')->where('bill_id', 999)->delete();
        $existingBills = DB::connection($this->connName)->table('new_bill_entry')
            ->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])
            ->pluck('id')
            ->toArray();
        if (!empty($existingBills)) {
            DB::connection($this->connName)->table('new_bills_item_entry')->whereIn('bill_id', $existingBills)->delete();
        }
        DB::connection($this->connName)->table('bill_party_statement')->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])->delete();
        DB::connection($this->connName)->table('new_bill_entry')->whereIn('bill_no', ['API-BILL-999', 'API-BILL-EDIT-999', 'API-MGMT-BILL-999', 'API-MGR-EDIT-999', 'API-LEGACY-BILL-999'])->delete();
        DB::connection($this->connName)->table('new_bill_entry')->where('id', 999)->delete();
        DB::connection($this->connName)->table('bills_party')->where('id', 999)->delete();
        DB::connection($this->connName)->table('sites')->where('id', 999)->delete();
        DB::connection($this->connName)->table('bills_work')->where('id', 999)->delete();

        parent::tearDown();
    }

    public function test_legacy_api_addnewbill_endpoint()
    {
        $file1 = UploadedFile::fake()->image('legacy_img.jpg');
        $file2 = UploadedFile::fake()->create('legacy_doc.pdf', 100, 'application/pdf');

        // Note: SiteBillsController::addnewbill expects items array in query string or payload
        $postData = [
            'conn' => $this->connName,
            'user_id' => $this->testUserId,
            'bill_site_id' => 999,
            'party_id' => 999,
            'bill_date' => '2026-07-06',
            'bill_period' => '2026-07-01 to 2026-07-06',
            'items' => json_encode([
                ['work_id' => 999, 'unit' => 'Pcs', 'rate' => 150, 'qty' => 10]
            ]),
            'remark' => 'Legacy API upload',
            'attachments' => [$file1, $file2]
        ];

        $response = $this->post('/api/addnewbill', $postData);

        $response->assertStatus(200);
        $resJson = json_decode($response->getContent(), true);
        $this->assertEquals('Ok', $resJson[0]['status']);

        $billId = $resJson[0]['inserted_id'];

        $bill = DB::connection($this->connName)->table('new_bill_entry')->find($billId);
        $this->assertNotNull($bill);
        $this->assertNotNull($bill->attachments);

        $attachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $attachments);

        // Cleanup
        foreach ($attachments as $path) {
            if (file_exists(public_path($path))) {
                unlink(public_path($path));
            }
        }
    }

    public function test_api_site_bills_controller_workflow()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        $file1 = UploadedFile::fake()->image('api_img1.jpg');
        $file2 = UploadedFile::fake()->create('api_doc1.pdf', 100, 'application/pdf');

        $postData = [
            'party_id' => 999,
            'site_id' => 999,
            'bill_no' => 'API-BILL-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'items' => [
                ['work_id' => 999, 'unit' => 'Pcs', 'rate' => 150, 'qty' => 10]
            ],
            'remark' => 'API Bills workflow',
            'attachments' => [$file1, $file2]
        ];

        // 1. Store
        $response = $this->postJson('/api/v1/bills', $postData, $headers);
        $response->assertStatus(200);
        $this->assertEquals('Ok', $response->json('status'));

        $billId = $response->json('id');

        // Set status to 'Pending' so update and delete are permitted
        DB::connection($this->connName)->table('new_bill_entry')->where('id', $billId)->update(['status' => 'Pending']);

        $bill = DB::connection($this->connName)->table('new_bill_entry')->find($billId);
        $this->assertNotNull($bill);
        $this->assertNotNull($bill->attachments);

        $attachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $attachments);
        $this->assertTrue(file_exists(public_path($attachments[0])));
        $this->assertTrue(file_exists(public_path($attachments[1])));

        // 2. Update (keep the first, delete the second, add a new one)
        $newFile = UploadedFile::fake()->image('api_img_new.png');
        $updateData = [
            'party_id' => 999,
            'site_id' => 999,
            'bill_no' => 'API-BILL-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'items' => [
                ['work_id' => 999, 'unit' => 'Pcs', 'rate' => 150, 'qty' => 10]
            ],
            'remark' => 'API Bills workflow updated',
            'existing_attachments' => [$attachments[0]],
            'attachments' => [$newFile]
        ];

        $response = $this->postJson('/api/v1/bills/' . $billId, $updateData, $headers);
        $response->assertStatus(200);

        $bill = DB::connection($this->connName)->table('new_bill_entry')->find($billId);
        $updatedAttachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $updatedAttachments);
        $this->assertContains($attachments[0], $updatedAttachments);
        $this->assertNotContains($attachments[1], $updatedAttachments);
        $this->assertFalse(file_exists(public_path($attachments[1])));
        $this->assertTrue(file_exists(public_path($updatedAttachments[1])));

        // Make sure it remains 'Pending' for deletion
        DB::connection($this->connName)->table('new_bill_entry')->where('id', $billId)->update(['status' => 'Pending']);

        // 3. Destroy
        $response = $this->deleteJson('/api/v1/bills/' . $billId, [], $headers);
        $response->assertStatus(200);

        $this->assertFalse(file_exists(public_path($attachments[0])));
        $this->assertFalse(file_exists(public_path($updatedAttachments[1])));
    }

    public function test_api_management_controller_workflow()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        $file1 = UploadedFile::fake()->image('mgmt_img1.jpg');
        $file2 = UploadedFile::fake()->create('mgmt_doc1.pdf', 100, 'application/pdf');

        $postData = [
            'bill_party_id' => 999,
            'bill_site_id' => 999,
            'bill_no' => 'API-MGMT-BILL-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'items' => [
                ['work_id' => 999, 'unit' => 'Pcs', 'rate' => 150, 'qty' => 10]
            ],
            'remark' => 'Mgmt Bills workflow',
            'attachments' => [$file1, $file2]
        ];

        // 1. Store
        $response = $this->postJson('/api/v1/management/bills', $postData, $headers);
        $response->assertStatus(200);
        $this->assertEquals('Ok', $response->json('status'));

        $billId = $response->json('id');

        // Set status to 'Pending' so update and delete are permitted
        DB::connection($this->connName)->table('new_bill_entry')->where('id', $billId)->update(['status' => 'Pending']);

        $bill = DB::connection($this->connName)->table('new_bill_entry')->find($billId);
        $this->assertNotNull($bill);
        $this->assertNotNull($bill->attachments);

        $attachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $attachments);
        $this->assertTrue(file_exists(public_path($attachments[0])));
        $this->assertTrue(file_exists(public_path($attachments[1])));

        // 2. Update
        $newFile = UploadedFile::fake()->image('mgmt_img_new.png');
        $updateData = [
            'bill_party_id' => 999,
            'bill_site_id' => 999,
            'bill_no' => 'API-MGMT-BILL-999',
            'bill_date' => '2026-07-06',
            'bill_from_date' => '2026-07-01',
            'bill_to_date' => '2026-07-06',
            'existing_attachments' => [$attachments[0]],
            'attachments' => [$newFile]
        ];

        $response = $this->patchJson('/api/v1/management/bills/' . $billId, $updateData, $headers);
        $response->assertStatus(200);

        $bill = DB::connection($this->connName)->table('new_bill_entry')->find($billId);
        $updatedAttachments = json_decode($bill->attachments, true);
        $this->assertCount(2, $updatedAttachments);
        $this->assertContains($attachments[0], $updatedAttachments);
        $this->assertNotContains($attachments[1], $updatedAttachments);
        $this->assertFalse(file_exists(public_path($attachments[1])));
        $this->assertTrue(file_exists(public_path($updatedAttachments[1])));

        // Make sure it remains 'Pending' for deletion
        DB::connection($this->connName)->table('new_bill_entry')->where('id', $billId)->update(['status' => 'Pending']);

        // 3. Delete
        $response = $this->deleteJson('/api/v1/management/bills/' . $billId, [], $headers);
        $response->assertStatus(200);

        $this->assertFalse(file_exists(public_path($attachments[0])));
        $this->assertFalse(file_exists(public_path($updatedAttachments[1])));
    }
}
