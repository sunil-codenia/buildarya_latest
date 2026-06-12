<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ChatSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set up default config for tenant database
        config([
            'database.connections.company_rsgeotech' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'database' => 'company_rsgeotech',
                'username' => 'root',
                'password' => '',
            ]
        ]);
    }

    public function test_guest_is_redirected_to_login()
    {
        $response = $this->get('/tasks/chat/messages/1');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_other_users_messages()
    {
        // Mock session for user 82 (sunil) who is not an admin
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 82,
            'name' => 'sunil',
            'role' => 8,
            'is_superadmin' => 'no',
            'comp_db_conn_name' => 'company_rsgeotech',
            'role_perms_set' => true,
            'permissions' => [
                [
                    // Module 14 is task management. We give only view access, not add.
                    14 => [
                        'can_view' => 1,
                        'can_add' => 0,
                        'can_edit' => 0,
                        'can_certify' => 0,
                        'can_pay' => 0,
                        'can_delete' => 0,
                        'can_report' => 0,
                    ]
                ]
            ],
            'company_modules' => [14],
        ];

        // Call fetch messages for another user (e.g. user 5) -> should be forbidden (403)
        $response = $this->withSession($sessionData)->get('/tasks/chat/messages/5');
        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error', 'message' => 'Access denied.']);

        // Call fetch messages for their own ID (82) -> should succeed (200)
        $response = $this->withSession($sessionData)->get('/tasks/chat/messages/82');
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_send_messages_to_other_users()
    {
        // Mock session for user 82 (sunil) who is not an admin
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 82,
            'name' => 'sunil',
            'role' => 8,
            'is_superadmin' => 'no',
            'comp_db_conn_name' => 'company_rsgeotech',
            'role_perms_set' => true,
            'permissions' => [
                [
                    14 => [
                        'can_view' => 1,
                        'can_add' => 0,
                    ]
                ]
            ],
            'company_modules' => [14],
        ];

        // Try to send message as user 82 to user 5 -> should be forbidden (403)
        $response = $this->withSession($sessionData)->postJson('/tasks/chat/send', [
            'user_id' => 5,
            'message' => 'Hello',
        ]);
        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error', 'message' => 'Access denied.']);

        // Send message to themselves (thread 82) -> should succeed (200)
        $response = $this->withSession($sessionData)->postJson('/tasks/chat/send', [
            'user_id' => 82,
            'message' => 'Hello',
        ]);
        $response->assertStatus(200);
    }

    public function test_admin_can_access_any_users_messages()
    {
        // Mock session for admin user
        $sessionData = [
            'key' => 'test-session-key',
            'uid' => 1,
            'name' => 'Admin',
            'role' => 1,
            'is_superadmin' => 'yes',
            'comp_db_conn_name' => 'company_rsgeotech',
            'role_perms_set' => true,
            'permissions' => [],
            'company_modules' => [14],
        ];

        // Admin can access any user's messages (e.g. user 82)
        $response = $this->withSession($sessionData)->get('/tasks/chat/messages/82');
        $response->assertStatus(200);

        // Admin can send message to any user (e.g. user 82)
        $response = $this->withSession($sessionData)->postJson('/tasks/chat/send', [
            'user_id' => 82,
            'message' => 'Hello from Admin',
        ]);
        $response->assertStatus(200);
    }
}
