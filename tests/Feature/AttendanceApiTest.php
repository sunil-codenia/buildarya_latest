<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class AttendanceApiTest extends TestCase
{
    private $tokenValue;
    private $tokenId;
    private $testUserId = 1; // Sachin Sharma in company_rsgeotech
    private $connName = 'company_rsgeotech';
    private $createdFiles = [];

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

        // 3. Insert fake Sanctum token into central DB
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

        // 4. Clean up any existing attendance record for today in company_rsgeotech
        DB::connection($this->connName)->table('attendance')
            ->where('user_id', $this->testUserId)
            ->where('date', now()->format('Y-m-d'))
            ->delete();
    }

    protected function tearDown(): void
    {
        // 1. Clean up personal access token
        DB::connection('mysql')->table('personal_access_tokens')
            ->where('id', $this->tokenId)
            ->delete();

        // 2. Clean up attendance record
        DB::connection($this->connName)->table('attendance')
            ->where('user_id', $this->testUserId)
            ->where('date', now()->format('Y-m-d'))
            ->delete();

        // 3. Clean up uploaded files
        foreach ($this->createdFiles as $filePath) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }
        }

        parent::tearDown();
    }

    public function test_clock_in_and_clock_out_with_images()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->tokenId . '|' . $this->tokenValue,
        ];

        // --- PART 1: CLOCK IN ---
        $clockInFile = UploadedFile::fake()->image('in_photo.jpg');

        $clockInResponse = $this->postJson('/api/v1/attendance/clock-in', [
            'site_id' => 1,
            'in_location' => '28.6139,77.2090',
            'remarks' => 'Test clock in with image',
            'image' => $clockInFile,
        ], $headers);

        $clockInResponse->assertStatus(200);
        $clockInResponse->assertJsonFragment(['status' => 'Ok', 'message' => 'Clocked in successfully!']);

        // Assert database has the record
        $attendanceRecord = DB::connection($this->connName)->table('attendance')
            ->where('user_id', $this->testUserId)
            ->where('date', now()->format('Y-m-d'))
            ->first();

        $this->assertNotNull($attendanceRecord);
        $this->assertNotNull($attendanceRecord->image);
        $this->assertStringContainsString('images/app_images/rsgeotech/attendance/', $attendanceRecord->image);

        // Keep track of the file path for cleanup and check existence
        $fullInPath = public_path($attendanceRecord->image);
        $this->createdFiles[] = $fullInPath;
        $this->assertFileExists($fullInPath);

        // --- PART 2: CLOCK OUT ---
        $clockOutFile = UploadedFile::fake()->image('out_photo.jpg');

        $clockOutResponse = $this->postJson('/api/v1/attendance/clock-out', [
            'out_location' => '28.6139,77.2090',
            'remarks' => 'Test clock out with image',
            'image' => $clockOutFile,
        ], $headers);

        $clockOutResponse->assertStatus(200);
        $clockOutResponse->assertJsonFragment(['status' => 'Ok', 'message' => 'Clocked out successfully!']);

        // Assert database is updated with out_image
        $updatedRecord = DB::connection($this->connName)->table('attendance')
            ->where('user_id', $this->testUserId)
            ->where('date', now()->format('Y-m-d'))
            ->first();

        $this->assertNotNull($updatedRecord->out_image);
        $this->assertStringContainsString('images/app_images/rsgeotech/attendance/', $updatedRecord->out_image);

        // Keep track of the out_image file path for cleanup and check existence
        $fullOutPath = public_path($updatedRecord->out_image);
        $this->createdFiles[] = $fullOutPath;
        $this->assertFileExists($fullOutPath);
    }
}
