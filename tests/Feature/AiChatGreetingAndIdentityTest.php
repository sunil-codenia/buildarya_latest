<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\api\AiChatQueryController;
use Illuminate\Http\Request;

class AiChatGreetingAndIdentityTest extends TestCase
{
    private function getController(): AiChatQueryController
    {
        return new AiChatQueryController();
    }

    public function test_detect_conversational_intent_recognizes_greetings(): void
    {
        $controller = $this->getController();
        $refMethod = new \ReflectionMethod($controller, 'detectConversationalIntent');
        $refMethod->setAccessible(true);

        $greetings = [
            'hi',
            'hi!',
            'hello',
            'hello?',
            'hellow',
            'hellow!',
            'helo',
            'hey',
            'hiya',
            'hlo',
            'good morning',
            'good afternoon',
            'good evening',
            'namaste',
            'namaskar',
            'hi there',
            'hello there',
            'hellow there',
            'hey there',
            'hello buildarya',
        ];

        foreach ($greetings as $input) {
            $intent = $refMethod->invoke($controller, $input);
            $this->assertSame('greeting', $intent, "Failed asserting that '{$input}' is detected as 'greeting'");
        }
    }

    public function test_detect_conversational_intent_recognizes_identity(): void
    {
        $controller = $this->getController();
        $refMethod = new \ReflectionMethod($controller, 'detectConversationalIntent');
        $refMethod->setAccessible(true);

        $identityInputs = [
            'who are you',
            'who are you?',
            'who are you ?',
            'who r u',
            'who r u?',
            'who ru',
            'who is this',
            'what are you',
            'what is your name',
            'tell me who you are',
            'tell me who are you',
            'tell me about yourself',
            'introduce yourself',
            'about yourself',
            'aap kaun ho',
            'aap koun ho',
            'tum kaun ho',
            'koun ho tum',
            'kaun ho tum',
            'who made you',
            'what is buildarya ai',
            'identify yourself'
        ];

        foreach ($identityInputs as $input) {
            $intent = $refMethod->invoke($controller, $input);
            $this->assertSame('identity', $intent, "Failed asserting that '{$input}' is detected as 'identity'");
        }
    }

    public function test_detect_conversational_intent_recognizes_small_talk(): void
    {
        $controller = $this->getController();
        $refMethod = new \ReflectionMethod($controller, 'detectConversationalIntent');
        $refMethod->setAccessible(true);

        // Well-being
        $this->assertSame('how_are_you', $refMethod->invoke($controller, 'how are you'));
        $this->assertSame('how_are_you', $refMethod->invoke($controller, 'how are you?'));
        $this->assertSame('how_are_you', $refMethod->invoke($controller, 'how r u'));
        $this->assertSame('how_are_you', $refMethod->invoke($controller, 'kaise ho'));
        $this->assertSame('how_are_you', $refMethod->invoke($controller, 'kya haal hai'));

        // Gratitude
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'thank you'));
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'thank you so much'));
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'thanks'));
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'thank u'));
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'shukriya'));
        $this->assertSame('thank_you', $refMethod->invoke($controller, 'dhanyawad'));

        // Help
        $this->assertSame('help', $refMethod->invoke($controller, 'what can you do'));
        $this->assertSame('help', $refMethod->invoke($controller, 'help'));
        $this->assertSame('help', $refMethod->invoke($controller, 'help me'));
        $this->assertSame('help', $refMethod->invoke($controller, 'kya kar sakte ho'));

        // Goodbye
        $this->assertSame('goodbye', $refMethod->invoke($controller, 'bye'));
        $this->assertSame('goodbye', $refMethod->invoke($controller, 'goodbye'));
        $this->assertSame('goodbye', $refMethod->invoke($controller, 'alvida'));
    }

    public function test_database_and_form_queries_are_not_intercepted_as_conversational(): void
    {
        $controller = $this->getController();
        $refMethod = new \ReflectionMethod($controller, 'detectConversationalIntent');
        $refMethod->setAccessible(true);

        $queries = [
            'who added this expense',
            'who created user John',
            'who is supervisor for site 1',
            'show attendance for today',
            'add new material',
            'add new expense',
            'material stock list',
            'petty cash report',
            'download attendance pdf',
        ];

        foreach ($queries as $q) {
            $intent = $refMethod->invoke($controller, $q);
            $this->assertNull($intent, "Query '{$q}' should not be intercepted as conversational");
        }
    }

    public function test_process_query_greeting_returns_proper_greeting_message_and_html(): void
    {
        $controller = $this->getController();

        $request = Request::create('/api/ai/query', 'POST', [
            'query' => 'hellow',
            'conn' => config('database.default'),
            'uid' => 1,
            'site_id' => 45,
        ]);

        $response = $controller->processQuery($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Ok', $data['status']);
        $this->assertSame('greeting', $data['data']['intent']);
        $this->assertStringContainsString('How can I help you', $data['message']);
        $this->assertStringContainsString('Hello', $data['data']['html']);
        $this->assertStringContainsString('How can I help you?', $data['data']['html']);
        $this->assertStringContainsString('How can I help you?', $data['data']['summary']);
    }

    public function test_process_query_identity_returns_from_buildarya_message_and_html(): void
    {
        $controller = $this->getController();

        $request = Request::create('/api/ai/query', 'POST', [
            'query' => 'who are you?',
            'conn' => config('database.default'),
            'uid' => 1,
            'site_id' => 45,
        ]);

        $response = $controller->processQuery($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertSame('Ok', $data['status']);
        $this->assertSame('identity', $data['data']['intent']);
        $this->assertStringContainsString('Buildarya', $data['message']);
        $this->assertStringContainsString('I am your AI Assistant from', $data['data']['html']);
        $this->assertStringContainsString('Buildarya', $data['data']['html']);
        $this->assertStringContainsString('I am your AI Assistant from Buildarya', $data['data']['summary']);
    }
}
