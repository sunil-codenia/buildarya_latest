<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MaterialEntryApiTest extends TestCase
{
    private $tokenValue;
    private $tokenId;
    private $testUserId = 999;
    private $connName = 'company_rsgeotech';

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
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]
        ]);

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

        // Clean up
        DB::connection($this->connName)->table('material_entry')->where('user_id', $this->testUserId)->delete();
        DB::connection($this->connName)->table('material_supplier')->where('id', 999)->delete();
        DB::connection($this->connName)->table('materials')->where('id', 999)->delete();
        DB::connection($this->connName)->table('units')->where('id', 999)->delete();
        DB::connection($this->connName)->table('sites')->where('id', 999)->delete();

        // Seed
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

        DB::connection($this->connName)->table('sites')->insert(['id' => 999, 'name' => 'QA-SITE-999', 'status' => 'Active', 'address' => 'Test Site 999']);
        DB::connection($this->connName)->table('material_supplier')->insert(['id' => 999, 'name' => 'QA-SUPPLIER-999', 'status' => 'Active']);
        DB::connection($this->connName)->table('materials')->insert(['id' => 999, 'name' => 'QA-MATERIAL-999']);
        DB::connection($this->connName)->table('units')->insert(['id' => 999, 'name' => 'QA-UNIT-999']);

        // Insert Sanctum token
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
        DB::connection('mysql')->table('personal_access_tokens')->where('id', $this->tokenId)->delete();
        DB::connection($this->connName)->table('material_entry')->where('user_id', $this->testUserId)->delete();
        DB::connection($this->connName)->table('material_supplier')->where('id', 999)->delete();
        DB::connection($this->connName)->table('materials')->where('id', 999)->delete();
        DB::connection($this->connName)->table('units')->where('id', 999)->delete();
        DB::connection($this->connName)->table('sites')->where('id', 999)->delete();
        parent::tearDown();
    }

    public function test_api_store_material_entry_with_multiple_images()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        $file1 = UploadedFile::fake()->image('img1.jpg');
        $file2 = UploadedFile::fake()->image('img2.jpg');
        $file3 = UploadedFile::fake()->image('img3.jpg');

        $postData = [
            'site_id' => [999],
            'supplier' => [999],
            'material_id' => [999],
            'unit' => [999],
            'qty' => [100],
            'vehical' => ['HR-55-1234'],
            'remark' => ['First bulk entry'],
            'date' => ['2026-07-06'],
            'image' => [$file1],
            'image2' => [$file2],
            'image3' => [$file3],
        ];

        $response = $this->postJson('/api/v1/materials/entries', $postData, $headers);
        $response->assertStatus(200);
        $this->assertEquals('Ok', $response->json('status'));

        $ids = $response->json('ids');
        $this->assertNotEmpty($ids);
        $id = $ids[0];

        $entry = DB::connection($this->connName)->table('material_entry')->find($id);
        $this->assertNotNull($entry);

        $images = explode(',', $entry->image);
        $this->assertCount(3, $images);
        foreach ($images as $img) {
            $this->assertStringContainsString('images/app_images/company_rsgeotech/material/', $img);
            $this->assertTrue(file_exists(public_path($img)));
            unlink(public_path($img));
        }

        $this->assertNull($entry->image2);
        $this->assertNull($entry->image3);
        $this->assertNull($entry->image4);
        $this->assertNull($entry->image5);
    }

    public function test_api_update_material_entry_with_multiple_images()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        // 1. Pre-insert record
        $id = DB::connection($this->connName)->table('material_entry')->insertGetId([
            'site_id' => 999,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 50,
            'vehical' => 'HR-55',
            'remark' => 'Update test api',
            'date' => '2026-07-06',
            'image' => 'images/expense.png',
            'user_id' => $this->testUserId,
            'status' => 'Pending',
        ]);

        $file1 = UploadedFile::fake()->image('edit_img1.jpg');
        $file2 = UploadedFile::fake()->image('edit_img2.jpg');

        $updateData = [
            'site_id' => 999,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 60,
            'vehical' => 'HR-55-UPDATED',
            'remark' => 'Update test api updated',
            'date' => '2026-07-06',
            'image' => $file1,
            'image2' => $file2,
        ];

        $response = $this->postJson('/api/v1/materials/entries/' . $id, $updateData, $headers);
        $response->assertStatus(200);

        $entry = DB::connection($this->connName)->table('material_entry')->find($id);
        $this->assertNotNull($entry);
        $this->assertEquals(60, $entry->qty);

        $images = explode(',', $entry->image);
        $this->assertCount(2, $images);
        foreach ($images as $img) {
            $this->assertStringContainsString('images/app_images/company_rsgeotech/material/', $img);
            $this->assertTrue(file_exists(public_path($img)));
            unlink(public_path($img));
        }

        $this->assertNull($entry->image2);
        $this->assertNull($entry->image3);
        $this->assertNull($entry->image4);
        $this->assertNull($entry->image5);
    }

    public function test_legacy_api_add_material_entry_with_multiple_images()
    {
        $file1 = UploadedFile::fake()->image('legacy_img1.jpg');
        $file2 = UploadedFile::fake()->image('legacy_img2.jpg');

        $postData = [
            'conn' => $this->connName,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 75,
            'vehical' => 'LEG-123',
            'remark' => 'Legacy test api',
            'location' => 'Test Loc',
            'site_id' => 999,
            'user_id' => $this->testUserId,
            'date' => date('Y-m-d'),
            'image' => [$file1],
            'image2' => [$file2],
        ];

        $response = $this->post('/api/addmaterialentry', $postData);
        $response->assertStatus(200);

        $resultJson = json_decode($response->getContent(), true);
        $this->assertNotEmpty($resultJson);
        $this->assertEquals('Ok', $resultJson[0]['status']);

        $entry = DB::connection($this->connName)->table('material_entry')
            ->where('qty', 75)
            ->where('vehical', 'LEG-123')
            ->first();
        $this->assertNotNull($entry);

        $images = explode(',', $entry->image);
        $this->assertCount(2, $images);
        foreach ($images as $img) {
            $this->assertStringContainsString('images/app_images/company_rsgeotech/material/', $img);
            $this->assertTrue(file_exists(public_path($img)));
            unlink(public_path($img));
        }
    }

    public function test_api_store_multiple_material_entries_with_multiple_images_each()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        $file0_1 = UploadedFile::fake()->image('row0_img1.jpg');
        $file0_2 = UploadedFile::fake()->image('row0_img2.jpg');
        $file1_1 = UploadedFile::fake()->image('row1_img1.jpg');
        $file1_2 = UploadedFile::fake()->image('row1_img2.jpg');

        $postData = [
            'site_id' => [999, 999],
            'supplier' => [999, 999],
            'material_id' => [999, 999],
            'unit' => [999, 999],
            'qty' => [100, 50],
            'vehical' => ['HR-01', 'HR-02'],
            'remark' => ['Row 0 remark', 'Row 1 remark'],
            'date' => ['2026-07-06', '2026-07-06'],
            'image' => [$file0_1, $file1_1],
            'image2' => [$file0_2, $file1_2],
        ];

        $response = $this->postJson('/api/v1/materials/entries', $postData, $headers);
        $response->assertStatus(200);
        $this->assertEquals('Ok', $response->json('status'));

        $ids = $response->json('ids');
        $this->assertCount(2, $ids);

        // Row 0
        $entry0 = DB::connection($this->connName)->table('material_entry')->find($ids[0]);
        $this->assertNotNull($entry0);
        $images0 = explode(',', $entry0->image);
        $this->assertCount(2, $images0);
        foreach ($images0 as $img) {
            $this->assertTrue(file_exists(public_path($img)));
            unlink(public_path($img));
        }

        // Row 1
        $entry1 = DB::connection($this->connName)->table('material_entry')->find($ids[1]);
        $this->assertNotNull($entry1);
        $images1 = explode(',', $entry1->image);
        $this->assertCount(2, $images1);
        foreach ($images1 as $img) {
            $this->assertTrue(file_exists(public_path($img)));
            unlink(public_path($img));
        }
    }

    public function test_api_store_and_update_material_entry_with_converted_qty()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        // 1. Test POST store
        $postData = [
            'site_id' => [999],
            'supplier' => [999],
            'material_id' => [999],
            'unit' => [999],
            'qty' => [100],
            'converted_qty' => [123.45],
            'vehical' => ['HR-55-1234'],
            'remark' => ['Test API store converted_qty'],
            'date' => ['2026-07-06'],
        ];

        $response = $this->postJson('/api/v1/materials/entries', $postData, $headers);
        $response->assertStatus(200);
        $this->assertEquals('Ok', $response->json('status'));

        $ids = $response->json('ids');
        $this->assertNotEmpty($ids);
        $id = $ids[0];

        $entry = DB::connection($this->connName)->table('material_entry')->find($id);
        $this->assertNotNull($entry);
        $this->assertEquals('123.45', $entry->converted_qty);

        // 2. Test GET show
        $getResponse = $this->getJson('/api/v1/materials/entries/' . $id, $headers);
        $getResponse->assertStatus(200);
        $this->assertEquals('123.45', $getResponse->json('data.converted_qty'));

        // 3. Test POST update
        $updateData = [
            'site_id' => 999,
            'supplier' => 999,
            'material_id' => 999,
            'unit' => 999,
            'qty' => 110,
            'converted_qty' => 150.60,
            'vehical' => 'HR-55-UPDATED',
            'remark' => 'Test API update converted_qty',
            'date' => '2026-07-06',
        ];

        $updateResponse = $this->postJson('/api/v1/materials/entries/' . $id, $updateData, $headers);
        $updateResponse->assertStatus(200);

        $updatedEntry = DB::connection($this->connName)->table('material_entry')->find($id);
        $this->assertNotNull($updatedEntry);
        $this->assertEquals('150.6', $updatedEntry->converted_qty);
    }
}
