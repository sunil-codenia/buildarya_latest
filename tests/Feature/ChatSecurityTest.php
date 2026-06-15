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

    public function test_non_admin_with_task_add_permission_cannot_access_other_users_messages()
    {
        // Mock session for user 82 (sunil) who has can_add permission for Tasks but is not an admin
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
                        'can_add' => 1,
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

    public function test_non_admin_with_task_add_permission_cannot_send_messages_to_other_users()
    {
        // Mock session for user 82 (sunil) who has can_add permission for Tasks but is not an admin
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
                        'can_add' => 1,
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

    public function test_task_chat_security_rules()
    {
        $conn = 'company_rsgeotech';
        
        // Dynamically add columns if they are missing in the test database schema
        if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'assigned_to')) {
            DB::connection($conn)->statement("ALTER TABLE `tasks` ADD COLUMN `assigned_to` INT NULL");
        }
        if (!\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'assigned_by')) {
            DB::connection($conn)->statement("ALTER TABLE `tasks` ADD COLUMN `assigned_by` INT NULL");
        }
        
        // Ensure tasks table and database connections work
        // Insert a test task
        $taskData = [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'site_id' => 0,
            'assigned_to' => 82, // Assigned to user 82 (sunil)
            'assigned_by' => 5,  // Created by user 5
            'created_by' => 5,   // Created by user 5
            'status' => 'Pending',
            'priority' => 'Medium',
            'created_at' => now(),
        ];
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'updated_at')) {
            $taskData['updated_at'] = now();
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'project_id')) {
            $taskData['project_id'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'total_units')) {
            $taskData['total_units'] = 0;
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'unit_type')) {
            $taskData['unit_type'] = '';
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'task_type')) {
            $taskData['task_type'] = 'TASK';
        }
        if (\Illuminate\Support\Facades\Schema::connection($conn)->hasColumn('tasks', 'parent_task_id')) {
            $taskData['parent_task_id'] = 0;
        }
        $taskId = DB::connection($conn)->table('tasks')->insertGetId($taskData);

        // 1. Participant (Assignee: 82) session data
        $assigneeSession = [
            'key' => 'test-session-key',
            'uid' => 82,
            'name' => 'sunil',
            'role' => 8,
            'is_superadmin' => 'no',
            'comp_db_conn_name' => $conn,
            'role_perms_set' => true,
            'permissions' => [[14 => ['can_view' => 1]]],
            'company_modules' => [14],
        ];

        // 2. Participant (Creator/Admin: 5) session data
        $creatorSession = [
            'key' => 'test-session-key',
            'uid' => 5,
            'name' => 'admin',
            'role' => 1,
            'is_superadmin' => 'yes',
            'comp_db_conn_name' => $conn,
            'role_perms_set' => true,
            'permissions' => [[14 => ['can_view' => 1]]],
            'company_modules' => [14],
        ];

        // 3. Unauthorized User (User 99) session data
        $unauthorizedSession = [
            'key' => 'test-session-key',
            'uid' => 99,
            'name' => 'intruder',
            'role' => 8,
            'is_superadmin' => 'no',
            'comp_db_conn_name' => $conn,
            'role_perms_set' => true,
            'permissions' => [[14 => ['can_view' => 1]]],
            'company_modules' => [14],
        ];

        // --- FETCH MESSAGES TEST ---
        
        // Assignee can access task chat messages (should succeed, returns 200)
        $response = $this->withSession($assigneeSession)->get('/tasks/chat/task-messages/' . $taskId);
        $response->assertStatus(200);

        // Creator/Admin can access task chat messages (should succeed, returns 200)
        $response = $this->withSession($creatorSession)->get('/tasks/chat/task-messages/' . $taskId);
        $response->assertStatus(200);

        // Unauthorized user cannot access task chat messages (should return 403)
        $response = $this->withSession($unauthorizedSession)->get('/tasks/chat/task-messages/' . $taskId);
        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error', 'message' => 'Access denied.']);

        // --- SEND MESSAGES TEST ---

        // Assignee can send messages (should succeed, returns 200)
        $response = $this->withSession($assigneeSession)->postJson('/tasks/chat/task-send', [
            'task_id' => $taskId,
            'message' => 'Hello from assignee',
        ]);
        $response->assertStatus(200);

        // Unauthorized user cannot send messages (should return 403)
        $response = $this->withSession($unauthorizedSession)->postJson('/tasks/chat/task-send', [
            'task_id' => $taskId,
            'message' => 'Hello from unauthorized',
        ]);
        $response->assertStatus(403);
        $response->assertJsonFragment(['status' => 'error', 'message' => 'Access denied.']);

        // Cleanup the test task and test chat messages
        DB::connection($conn)->table('task_chats')->where('task_id', $taskId)->delete();
        DB::connection($conn)->table('tasks')->where('id', $taskId)->delete();
    }
}
