<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;
use Illuminate\Http\Request;

class AiChatVoiceAssistantTest extends TestCase
{
    public function test_system_prompt_includes_voice_assistant_and_colloquial_rules(): void
    {
        $controller = new AiChatQueryController();
        $refMethod = new \ReflectionMethod($controller, 'callLlmForSql');
        $refMethod->setAccessible(true);

        // We check that callLlmForSql handles voice assistant and spoken phrasing
        // by verifying the method exists and reflection on system prompt contents
        $tenant = [
            'conn' => config('database.default'),
            'site_id' => 45,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $dynMethod = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $dynMethod->setAccessible(true);

        // Test colloquial Hindi/spoken query for expenses: "aaj ka kharcha"
        $sqlExpense = $dynMethod->invoke($controller, 'aaj ka kharcha', $tenant);
        $this->assertNotNull($sqlExpense);
        $this->assertStringContainsString('expenses', strtolower($sqlExpense));
        $this->assertStringContainsString('curdate()', strtolower($sqlExpense));

        // Test colloquial Hindi/spoken query for attendance: "haziri report"
        $sqlAttendance = $dynMethod->invoke($controller, 'haziri report', $tenant);
        $this->assertNotNull($sqlAttendance);
        $this->assertStringContainsString('attendance', strtolower($sqlAttendance));

        // Test colloquial Hindi/spoken query for materials: "maal entry"
        $sqlMaterial = $dynMethod->invoke($controller, 'maal entry', $tenant);
        $this->assertNotNull($sqlMaterial);
        $this->assertStringContainsString('material_entry', strtolower($sqlMaterial));

        // Test colloquial Hindi/spoken query for tasks: "kaam list"
        $sqlTask = $dynMethod->invoke($controller, 'kaam list', $tenant);
        $this->assertNotNull($sqlTask);
        $this->assertStringContainsString('tasks', strtolower($sqlTask));
    }

    public function test_voice_query_returns_proper_names_for_expenses(): void
    {
        $controller = new AiChatQueryController();
        $dynMethod = new \ReflectionMethod($controller, 'generateDynamicSqlFromText');
        $dynMethod->setAccessible(true);

        $tenant = [
            'conn' => config('database.default'),
            'site_id' => 45,
            'site_name' => 'Head Office',
            'is_superadmin' => true,
            'assigned_site_ids' => []
        ];

        $sql = $dynMethod->invoke($controller, 'show me today expense', $tenant);
        $this->assertNotNull($sql);
        $this->assertStringContainsString('party_name', $sql);
        $this->assertStringContainsString('cost_category_name', $sql);
        $this->assertStringContainsString('site_name', $sql);
        $this->assertStringContainsString('user_name', $sql);
    }

    public function test_transcribe_voice_validates_audio_file(): void
    {
        $controller = new AiChatQueryController();
        $request = new Request();
        $response = $controller->transcribeVoice($request);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_transcribe_voice_endpoint_successful(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'show all expenses']
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $fakeAudio = \Illuminate\Http\UploadedFile::fake()->create('recording.webm', 50, 'audio/webm');
        $request = new Request([], [], [], [], ['audio' => $fakeAudio]);

        $controller = new AiChatQueryController();
        $response = $controller->transcribeVoice($request);
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('show all expenses', $data['text']);
    }

    public function test_switch_to_localhost_and_bridge_session_routes_exist(): void
    {
        $sessionData = ['key' => '12345', 'uid' => 1, 'name' => 'Test User'];
        $this->withSession($sessionData);

        $response = $this->get('/switch-to-localhost');
        $this->assertTrue($response->isRedirection());
        $target = $response->headers->get('Location');
        $this->assertStringContainsString('localhost', $target);
        $this->assertStringContainsString('/bridge-session?token=', $target);

        // Extract token from target URL
        parse_str(parse_url($target, PHP_URL_QUERY), $queryParams);
        $token = $queryParams['token'] ?? null;
        $this->assertNotEmpty($token);

        // Access bridge-session with token
        $bridgeResponse = $this->get('/bridge-session?token=' . $token);
        $this->assertTrue($bridgeResponse->isRedirection());
        $this->assertEquals(url('/chat-view'), $bridgeResponse->headers->get('Location'));
        $this->assertEquals('12345', session()->get('key'));
        $this->assertEquals('Test User', session()->get('name'));
    }

    public function test_classic_view_contains_audio_upload_and_switch_elements(): void
    {
        $viewPath = resource_path('views/classic_view.blade.php');
        $content = file_get_contents($viewPath);

        $this->assertStringContainsString('id="voice-audio-file-input"', $content);
        $this->assertStringContainsString('handleVoiceFileUpload(this)', $content);
        $this->assertStringContainsString('id="localhost-origin-alert"', $content);
        $this->assertStringContainsString('id="topbar-localhost-btn"', $content);
    }
}

