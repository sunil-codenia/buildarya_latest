<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class QRCodeUploadTest extends TestCase
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

        // Clean up previous test runs
        DB::connection($this->conn)->table('payment_vouchers')->where('voucher_no', 'QA-V-999')->delete();
        DB::connection($this->conn)->table('bills_party')->where('id', 999)->delete();
        DB::connection($this->conn)->table('material_supplier')->where('id', 999)->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Supplier 999')->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Supplier 999 Updated')->delete();

        // Seed roles & user
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

        // Seed default company & site for payment vouchers using updateOrInsert to prevent duplicate key errors
        DB::connection($this->conn)->table('sales_company')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA Company', 'status' => 'Active']
        );

        DB::connection($this->conn)->table('sites')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA Site', 'status' => 'Active', 'address' => 'QA Site Address']
        );
    }

    public function test_bill_party_qr_code_upload_and_update()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'comp_id' => 'company_rsgeotech',
            'add_duration' => 'anytime',
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
            'company_modules' => [4],
        ];

        // 1. Add Bill Party
        $qrFile = UploadedFile::fake()->image('qr_party.png');
        $postData = [
            'id' => 999,
            'name' => 'QA Bill Party 999',
            'address' => 'Test Address',
            'gstin' => 'GSTIN999',
            'bankname' => 'Bank',
            'bank_ac' => '123456',
            'ifsc' => 'IFSC999',
            'ac_holder_name' => 'Holder',
            'qr_code' => $qrFile
        ];

        $response = $this->withSession($sessionData)->post('/addbillparty', $postData);
        $response->assertStatus(302);

        $party = DB::connection($this->conn)->table('bills_party')->where('name', 'QA Bill Party 999')->first();
        $this->assertNotNull($party);
        $this->assertNotNull($party->qr_code);
        $this->assertTrue(File::exists(public_path($party->qr_code)));

        $oldQrPath = $party->qr_code;

        // 2. Update Bill Party with new QR Code
        $newQrFile = UploadedFile::fake()->image('qr_party_new.png');
        $updateData = [
            'id' => $party->id,
            'name' => 'QA Bill Party 999 Updated',
            'address' => 'Test Address Updated',
            'gstin' => 'GSTIN999',
            'bankname' => 'Bank',
            'bank_ac' => '123456',
            'ifsc' => 'IFSC999',
            'ac_holder_name' => 'Holder',
            'qr_code' => $newQrFile
        ];

        $response = $this->withSession($sessionData)->post('/updatebillparty', $updateData);
        $response->assertStatus(302);

        $partyUpdated = DB::connection($this->conn)->table('bills_party')->find($party->id);
        $this->assertNotNull($partyUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $partyUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($partyUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($partyUpdated->qr_code));
    }

    public function test_material_supplier_qr_code_upload_and_update()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'comp_id' => 'company_rsgeotech',
            'add_duration' => 'anytime',
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
            'company_modules' => [3],
        ];

        // 1. Add Material Supplier
        $qrFile = UploadedFile::fake()->image('qr_supplier.png');
        $postData = [
            'name' => 'QA Supplier 999',
            'address' => 'Test Address',
            'gstin' => 'GSTIN999',
            'bank_name' => 'Bank',
            'bank_ac' => '123456',
            'bank_ifsc' => 'IFSC999',
            'bank_ac_holder' => 'Holder',
            'cost_category_id' => 1,
            'qr_code' => $qrFile
        ];

        $response = $this->withSession($sessionData)->post('/addmaterialsupplier', $postData);
        $response->assertStatus(302);

        $supplier = DB::connection($this->conn)->table('material_supplier')->where('name', 'QA Supplier 999')->first();
        $this->assertNotNull($supplier);
        $this->assertNotNull($supplier->qr_code);
        $this->assertTrue(File::exists(public_path($supplier->qr_code)));

        $oldQrPath = $supplier->qr_code;

        // 2. Update Material Supplier
        $newQrFile = UploadedFile::fake()->image('qr_supplier_new.png');
        $updateData = [
            'id' => $supplier->id,
            'name' => 'QA Supplier 999 Updated',
            'address' => 'Test Address',
            'gstin' => 'GSTIN999',
            'bank_name' => 'Bank',
            'bank_ac' => '123456',
            'bank_ifsc' => 'IFSC999',
            'bank_ac_holder' => 'Holder',
            'cost_category_id' => 1,
            'qr_code' => $newQrFile
        ];

        $response = $this->withSession($sessionData)->post('/updatematerialsupplier', $updateData);
        $response->assertStatus(302);

        $supplierUpdated = DB::connection($this->conn)->table('material_supplier')->find($supplier->id);
        $this->assertNotNull($supplierUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $supplierUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($supplierUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($supplierUpdated->qr_code));
    }

    public function test_payment_voucher_qr_code_upload_and_update()
    {
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => $this->conn,
            'comp_id' => 'company_rsgeotech',
            'add_duration' => 'anytime',
            'primary_color' => ['#000000'],
            'secondry_color' => ['#ffffff'],
            'company_modules' => [8],
        ];

        // Seed a party to associate with the payment voucher
        DB::connection($this->conn)->table('bills_party')->updateOrInsert(
            ['id' => 999],
            ['name' => 'QA Party 999', 'status' => 'Active']
        );

        // 1. Add Payment Voucher
        $qrFile = UploadedFile::fake()->image('qr_pv.png');
        $postData = [
            'company_id' => [999],
            'site_id' => [999],
            'party_id' => ['999||bill'],
            'voucher_no' => ['QA-V-999'],
            'amount' => [1500],
            'date' => [date('Y-m-d')],
            'payment_details' => ['QA details'],
            'remark' => ['QA remark'],
            'qr_code' => [$qrFile]
        ];

        $response = $this->withSession($sessionData)->post('/addnewpaymentvouchers', $postData);
        $response->assertStatus(302);

        $pv = DB::connection($this->conn)->table('payment_vouchers')->where('voucher_no', 'QA-V-999')->first();
        $this->assertNotNull($pv);
        $this->assertNotNull($pv->qr_code);
        $this->assertTrue(File::exists(public_path($pv->qr_code)));

        $oldQrPath = $pv->qr_code;

        // 2. Update Payment Voucher
        $newQrFile = UploadedFile::fake()->image('qr_pv_new.png');
        $updateData = [
            'id' => $pv->id,
            'company_id' => 999,
            'site_id' => 999,
            'party_id' => '999||bill',
            'voucher_no' => 'QA-V-999',
            'amount' => 1800,
            'date' => date('Y-m-d'),
            'payment_details' => 'QA details updated',
            'remark' => 'QA remark updated',
            'qr_code' => $newQrFile
        ];

        $response = $this->withSession($sessionData)->post('/updateEditpaymentvouchers', $updateData);
        $response->assertStatus(302);

        $pvUpdated = DB::connection($this->conn)->table('payment_vouchers')->find($pv->id);
        $this->assertNotNull($pvUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $pvUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($pvUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($pvUpdated->qr_code));
    }

    public function test_api_store_material_supplier_with_qr_code()
    {
        // 1. Ensure company record exists in the main database
        $exists = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $this->conn)
            ->exists();

        if (!$exists) {
            DB::connection('mysql')->table('companies')->insert([
                'name' => 'RSGeotech',
                'uid' => 'rsgeotech',
                'db_conn_name' => $this->conn,
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

        // 2. Insert Sanctum Token
        $tokenValue = \Illuminate\Support\Str::random(40);
        $hashedToken = hash('sha256', $tokenValue);
        $tokenId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => 1,
            'name' => $this->conn,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $tokenId . '|' . $tokenValue,
        ];

        // 3. Mock Request upload
        $qrFile = UploadedFile::fake()->image('api_qr_supplier.png');
        $postData = [
            'name' => 'QA Supplier API 999',
            'address' => 'Test API Address',
            'gstin' => 'GSTIN999API',
            'bank_name' => 'Bank API',
            'bank_ac' => '123456789',
            'bank_ifsc' => 'IFSC999API',
            'bank_ac_holder' => 'Holder API',
            'cost_category_id' => 1,
            'status' => 'Active',
            'qr_code' => $qrFile
        ];

        // Ensure clean slate
        DB::connection($this->conn)->table('material_supplier')->where('name', 'QA Supplier API 999')->delete();
        DB::connection($this->conn)->table('material_supplier')->where('name', 'QA Supplier API 999 Updated')->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Supplier API 999')->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Supplier API 999 Updated')->delete();

        // 4. Make POST call to API
        $response = $this->postJson('/api/v1/materials/suppliers', $postData, $headers);
        $response->assertStatus(200);
        $resJson = $response->json();
        $this->assertEquals('Ok', $resJson['status']);
        
        $supplierId = $resJson['id'];
        $supplier = DB::connection($this->conn)->table('material_supplier')->find($supplierId);
        $this->assertNotNull($supplier);
        $this->assertNotNull($supplier->qr_code);
        $this->assertTrue(File::exists(public_path($supplier->qr_code)));

        $oldQrPath = $supplier->qr_code;

        // 5. Update Material Supplier via API
        $newQrFile = UploadedFile::fake()->image('api_qr_supplier_new.png');
        $updateData = [
            'name' => 'QA Supplier API 999 Updated',
            'qr_code' => $newQrFile
        ];

        $response = $this->postJson('/api/v1/materials/suppliers/' . $supplierId, $updateData, $headers);
        $response->assertStatus(200);

        $supplierUpdated = DB::connection($this->conn)->table('material_supplier')->find($supplierId);
        $this->assertNotNull($supplierUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $supplierUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($supplierUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($supplierUpdated->qr_code));
        DB::connection($this->conn)->table('material_supplier')->where('id', $supplierId)->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Supplier API 999 Updated')->delete();
        DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->delete();
    }

    public function test_api_store_bill_party_with_qr_code()
    {
        // 1. Ensure company record exists in the main database
        $exists = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $this->conn)
            ->exists();

        if (!$exists) {
            DB::connection('mysql')->table('companies')->insert([
                'name' => 'RSGeotech',
                'uid' => 'rsgeotech',
                'db_conn_name' => $this->conn,
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

        // 2. Insert Sanctum Token
        $tokenValue = \Illuminate\Support\Str::random(40);
        $hashedToken = hash('sha256', $tokenValue);
        $tokenId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => 1,
            'name' => $this->conn,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $tokenId . '|' . $tokenValue,
        ];

        // 3. Mock Request upload (Store)
        $qrFile = UploadedFile::fake()->image('api_qr_party.png');
        $postData = [
            'name' => 'QA Bill Party API 999',
            'address' => 'Test API Address',
            'panno' => 'PAN999API',
            'bankname' => 'Bank API',
            'bank_ac' => '123456789',
            'ifsc' => 'IFSC999API',
            'ac_holder_name' => 'Holder API',
            'cost_category_id' => 1,
            'status' => 'Active',
            'qr_code' => $qrFile
        ];

        // Ensure clean slate
        DB::connection($this->conn)->table('bills_party')->where('name', 'QA Bill Party API 999')->delete();
        DB::connection($this->conn)->table('bills_party')->where('name', 'QA Bill Party API 999 Updated')->delete();

        // 4. Make POST call to API to Create
        $response = $this->postJson('/api/v1/bill-parties', $postData, $headers);
        $response->assertStatus(200);
        $resJson = $response->json();
        $this->assertEquals('Ok', $resJson['status']);
        
        $partyId = $resJson['id'];
        $party = DB::connection($this->conn)->table('bills_party')->find($partyId);
        $this->assertNotNull($party);
        $this->assertNotNull($party->qr_code);
        $this->assertTrue(File::exists(public_path($party->qr_code)));

        $oldQrPath = $party->qr_code;

        // 5. Update Bill Party via API using PATCH and JSON base64 string
        $newQrFile = UploadedFile::fake()->image('api_qr_party_new.png');
        $updateData = [
            'name' => 'QA Bill Party API 999 Updated',
            // Send new QR code as base64 in json
            'qr_code' => 'data:image/png;base64,' . base64_encode(file_get_contents($newQrFile->path()))
        ];

        $response = $this->postJson('/api/v1/bill-parties/' . $partyId, $updateData, $headers);
        $response->assertStatus(200);

        $partyUpdated = DB::connection($this->conn)->table('bills_party')->find($partyId);
        $this->assertNotNull($partyUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $partyUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($partyUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($partyUpdated->qr_code));
        DB::connection($this->conn)->table('bills_party')->where('id', $partyId)->delete();
        DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->delete();
    }

    public function test_api_store_other_party_with_qr_code()
    {
        // 1. Ensure company record exists in the main database
        $exists = DB::connection('mysql')->table('companies')
            ->where('db_conn_name', $this->conn)
            ->exists();

        if (!$exists) {
            DB::connection('mysql')->table('companies')->insert([
                'name' => 'RSGeotech',
                'uid' => 'rsgeotech',
                'db_conn_name' => $this->conn,
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

        // 2. Insert Sanctum Token
        $tokenValue = \Illuminate\Support\Str::random(40);
        $hashedToken = hash('sha256', $tokenValue);
        $tokenId = DB::connection('mysql')->table('personal_access_tokens')->insertGetId([
            'tokenable_type' => 'App\User',
            'tokenable_id' => 1,
            'name' => $this->conn,
            'token' => $hashedToken,
            'abilities' => json_encode(['*']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $tokenId . '|' . $tokenValue,
        ];

        // 3. Mock Request upload (Store)
        $qrFile = UploadedFile::fake()->image('api_qr_other_party.png');
        $postData = [
            'name' => 'QA Other Party API 999',
            'address' => 'Test API Address',
            'panno' => 'PAN999API',
            'bank_name' => 'Bank API',
            'bank_ac' => '123456789',
            'bank_ifsc' => 'IFSC999API',
            'bank_ac_holder' => 'Holder API',
            'cost_category_id' => 1,
            'status' => 'Active',
            'qr_code' => $qrFile
        ];

        // Ensure clean slate
        DB::connection($this->conn)->table('other_parties')->where('name', 'QA Other Party API 999')->delete();
        DB::connection($this->conn)->table('other_parties')->where('name', 'QA Other Party API 999 Updated')->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Other Party API 999')->delete();

        // 4. Make POST call to API to Create
        $response = $this->postJson('/api/v1/other-parties', $postData, $headers);
        $response->assertStatus(201);
        $resJson = $response->json();
        $this->assertEquals('Ok', $resJson['status']);
        
        $partyId = $resJson['id'];
        $party = DB::connection($this->conn)->table('other_parties')->find($partyId);
        $this->assertNotNull($party);
        $this->assertNotNull($party->qr_code);
        $this->assertTrue(File::exists(public_path($party->qr_code)));

        $oldQrPath = $party->qr_code;

        // 5. Update Other Party via API using POST and new image upload
        $newQrFile = UploadedFile::fake()->image('api_qr_other_party_new.png');
        $updateData = [
            'name' => 'QA Other Party API 999 Updated',
            'cost_category_id' => 1,
            'qr_code' => $newQrFile
        ];

        $response = $this->postJson('/api/v1/other-parties/' . $partyId, $updateData, $headers);
        $response->assertStatus(200);

        $partyUpdated = DB::connection($this->conn)->table('other_parties')->find($partyId);
        $this->assertNotNull($partyUpdated->qr_code);
        $this->assertNotEquals($oldQrPath, $partyUpdated->qr_code);
        $this->assertTrue(File::exists(public_path($partyUpdated->qr_code)));
        $this->assertFalse(File::exists(public_path($oldQrPath))); // Should be deleted

        // Clean up
        @unlink(public_path($partyUpdated->qr_code));
        DB::connection($this->conn)->table('other_parties')->where('id', $partyId)->delete();
        DB::connection($this->conn)->table('contact_profile')->where('comp_name', 'QA Other Party API 999')->delete();
        DB::connection('mysql')->table('personal_access_tokens')->where('id', $tokenId)->delete();
    }
}
