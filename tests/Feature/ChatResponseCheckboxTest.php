<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChatResponseCheckboxTest extends TestCase
{
    public function test_user_search_audit_table_has_is_checked_column(): void
    {
        $this->assertTrue(
            Schema::connection('mysql')->hasColumn('user_search_audit', 'is_checked'),
            'user_search_audit table should have is_checked column'
        );
    }

    public function test_update_check_endpoint_toggles_status(): void
    {
        // Insert a test audit record
        $id = DB::connection('mysql')->table('user_search_audit')->insertGetId([
            'username' => 'test_checkbox_user@test.com',
            'action_type' => 'search',
            'question' => 'test question for checkbox',
            'response' => 'test response',
            'response_payload' => json_encode(['status' => 'Ok']),
            'is_checked' => 0,
            'searched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNotNull($id);

        $user = \App\User::first() ?? new \App\User(['id' => 1]);

        // Update to checked (1)
        $response = $this->actingAs($user)->withSession([
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'username' => 'test_checkbox_user@test.com',
            'comp_db_conn_name' => 'mysql',
        ])->postJson(route('chat.response.updateCheck'), [
            'id' => $id,
            'is_checked' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'is_checked' => true,
                'id' => $id,
            ]);

        $row = DB::connection('mysql')->table('user_search_audit')->where('id', $id)->first();
        $this->assertEquals(1, $row->is_checked);

        // Update to unchecked (0)
        $response2 = $this->actingAs($user)->withSession([
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'username' => 'test_checkbox_user@test.com',
            'comp_db_conn_name' => 'mysql',
        ])->postJson(route('chat.response.updateCheck'), [
            'id' => $id,
            'is_checked' => 0,
        ]);

        $response2->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'is_checked' => false,
                'id' => $id,
            ]);

        $row2 = DB::connection('mysql')->table('user_search_audit')->where('id', $id)->first();
        $this->assertEquals(0, $row2->is_checked);

        // Clean up
        DB::connection('mysql')->table('user_search_audit')->where('id', $id)->delete();
    }

    public function test_bulk_update_check_endpoint(): void
    {
        $id1 = DB::connection('mysql')->table('user_search_audit')->insertGetId([
            'username' => 'bulk_user1@test.com',
            'action_type' => 'search',
            'question' => 'bulk question 1',
            'response_payload' => json_encode(['test' => 1]),
            'is_checked' => 0,
            'created_at' => now(),
        ]);

        $id2 = DB::connection('mysql')->table('user_search_audit')->insertGetId([
            'username' => 'bulk_user2@test.com',
            'action_type' => 'search',
            'question' => 'bulk question 2',
            'response_payload' => json_encode(['test' => 2]),
            'is_checked' => 0,
            'created_at' => now(),
        ]);

        $user = \App\User::first() ?? new \App\User(['id' => 1]);
        $response = $this->actingAs($user)->withSession([
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'comp_db_conn_name' => 'mysql',
        ])->postJson(route('chat.response.bulkUpdateCheck'), [
            'ids' => [$id1, $id2],
            'is_checked' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'count' => 2,
            ]);

        $this->assertEquals(1, DB::connection('mysql')->table('user_search_audit')->where('id', $id1)->value('is_checked'));
        $this->assertEquals(1, DB::connection('mysql')->table('user_search_audit')->where('id', $id2)->value('is_checked'));

        // Clean up
        DB::connection('mysql')->table('user_search_audit')->whereIn('id', [$id1, $id2])->delete();
    }

    public function test_chat_response_index_renders_sno_and_checkbox_columns(): void
    {
        // Insert a record to display
        $id = DB::connection('mysql')->table('user_search_audit')->insertGetId([
            'company_connection' => 'mysql',
            'user_id' => 1,
            'username' => 'index_view_user@test.com',
            'action_type' => 'search',
            'question' => 'view test question',
            'response' => 'view test response',
            'response_payload' => json_encode(['message' => 'Hello test! How can I help you?']),
            'is_checked' => 1,
            'searched_at' => now(),
            'created_at' => now(),
        ]);

        $user = \App\User::first() ?? new \App\User(['id' => 1]);
        $response = $this->actingAs($user)->withSession([
            'key' => 'test-session-key',
            'uid' => 1,
            'role' => 1,
            'name' => 'test',
            'username' => 'index_view_user@test.com',
            'comp_db_conn_name' => 'mysql',
            'is_superadmin' => true,
        ])->get(route('chat.response'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('S.No', $content);
        $this->assertStringContainsString('Checked', $content);
        $this->assertStringContainsString('chat-response-checkbox', $content);
        $this->assertStringContainsString('data-id="' . $id . '"', $content);

        // Clean up
        DB::connection('mysql')->table('user_search_audit')->where('id', $id)->delete();
    }
}
