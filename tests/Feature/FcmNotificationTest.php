<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FcmNotificationTest extends TestCase
{
    private $connName = 'company_rsgeotech';
    private $testUserId = 82;

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

        // Ensure company record exists in the main database
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
    }

    public function test_fcm_token_registration_via_v1_endpoints()
    {
        // Drop user_devices if exists to test creation
        Schema::connection($this->connName)->dropIfExists('user_devices');

        $fcmToken = 'test_fcm_token_' . uniqid();

        // 1. Test registration via /api/v1/update_fcm_id
        $response = $this->postJson('/api/v1/update_fcm_id', [
            'conn' => $this->connName,
            'user_id' => $this->testUserId,
            'fcm_token' => $fcmToken,
            'platform' => 'android',
            'device_name' => 'Test Android Device'
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('FCM Code Updated Successfully!', $response->getContent());

        // Assert table was created and token inserted
        $this->assertTrue(Schema::connection($this->connName)->hasTable('user_devices'));
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->testUserId,
            'fcm_token' => $fcmToken,
            'is_active' => 1,
            'platform' => 'android'
        ], $this->connName);

        // 2. Test registration via /api/v1/api/update_fcm_id
        $newFcmToken = 'new_test_fcm_token_' . uniqid();
        $response = $this->postJson('/api/v1/api/update_fcm_id', [
            'conn' => $this->connName,
            'user_id' => $this->testUserId,
            'fcm_token' => $newFcmToken,
            'platform' => 'android',
            'device_name' => 'Test Android Device'
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('FCM Code Updated Successfully!', $response->getContent());

        // Assert token updated
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->testUserId,
            'fcm_token' => $newFcmToken,
            'is_active' => 1
        ], $this->connName);
    }

    public function test_fcm_cleanup_on_not_registered_response()
    {
        $fcmToken = 'invalid_fcm_token_to_cleanup';

        // Set token in users table
        DB::connection($this->connName)->table('users')
            ->where('id', $this->testUserId)
            ->update(['fcm_id' => $fcmToken]);

        // Insert into user_devices table
        if (!Schema::connection($this->connName)->hasTable('user_devices')) {
            $this->postJson('/api/v1/update_fcm_id', [
                'conn' => $this->connName,
                'user_id' => $this->testUserId,
                'fcm_token' => $fcmToken,
                'platform' => 'android'
            ]);
        } else {
            DB::connection($this->connName)->table('user_devices')->updateOrInsert(
                ['user_id' => $this->testUserId, 'fcm_token' => $fcmToken],
                ['is_active' => 1, 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Fake the Google token request and FCM send request
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'mocked_oauth_access_token',
                'expires_in' => 3600
            ], 200),
            'https://fcm.googleapis.com/v1/projects/*' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'NotRegistered',
                    'status' => 'NOT_FOUND',
                    'details' => [
                        [
                            '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                            'errorCode' => 'UNREGISTERED'
                        ]
                    ]
                ]
            ], 404)
        ]);

        // Call sendAlertNotification directly
        sendAlertNotification($this->testUserId, 'Test Message', 'Test Title', $this->connName);

        // Assert the token is deactivated in user_devices
        $this->assertDatabaseHas('user_devices', [
            'user_id' => $this->testUserId,
            'fcm_token' => $fcmToken,
            'is_active' => 0
        ], $this->connName);

        // Assert the token is cleared from users table
        $user = DB::connection($this->connName)->table('users')->where('id', $this->testUserId)->first();
        $this->assertNull($user->fcm_id);
    }
}
