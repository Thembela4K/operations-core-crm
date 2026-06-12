<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.assistant_ai.nvidia.api_key', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_use_assistant(): void
    {
        $this->postJson(route('assistant.message'), [
            'message' => 'show overdue tender proposals',
        ])->assertUnauthorized();
    }

    public function test_assistant_returns_clear_message_when_ai_is_not_configured(): void
    {
        $user = $this->user('Thembela Mthimkhulu', User::ROLE_DEPARTMENT_USER);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk();

        $response->assertJsonPath('action', null);
        $this->assertStringContainsString('MIS AI service is unavailable', $response->json('reply'));
    }

    public function test_assistant_returns_quota_message_when_provider_rate_limits(): void
    {
        $this->configureAi(null, 429);
        $user = $this->user('Thembela Mthimkhulu', User::ROLE_DEPARTMENT_USER);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk();

        $response->assertJsonPath('action', null);
        $this->assertStringContainsString('quota has been exceeded', $response->json('reply'));
    }

    public function test_assistant_uses_remote_ai_for_conversation(): void
    {
        $this->configureAi([
            'reply' => 'Yes, I know of ChatGPT. I am MIS, focused on helping with this portal.',
            'action' => null,
        ]);

        $user = $this->user('Temnotfo Malinga', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'do you know Chat GPT?',
        ])->assertOk();

        $response->assertJsonPath('action', null);
        $this->assertSame('Yes, I know of ChatGPT. I am MIS, focused on helping with this portal.', $response->json('reply'));

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer testing-key')
            && $request['model'] === 'nvidia/llama-3.3-nemotron-super-49b-v1'
            && $request['response_format']['type'] === 'json_object'
            && $request['stream'] === false);
    }

    public function test_assistant_cleans_model_meta_text_from_replies(): void
    {
        $this->configureAi([
            'reply' => 'Hello Temnotfo Malinga (super_admin, Admin - Reception)! How can I assist you today? **Response Format Reminder:** tell me what to do next.',
            'action' => null,
        ]);

        $user = $this->user('Temnotfo Malinga', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk();

        $response->assertJsonPath('reply', 'Hello Temnotfo Malinga! How can I assist you today?');
        $response->assertJsonPath('action', null);
    }

    public function test_assistant_strips_embedded_raw_json_from_mixed_model_output(): void
    {
        $this->configureAiRaw(
            "A brief escape from work! Here is a short story for you.\n{\"reply\":\"A brief escape from work! Here is a short story for you.\\n\\nBack to work?\",\"action\":null}"
        );

        $user = $this->user('Temnotfo Malinga', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'tell me a story',
        ])->assertOk();

        $response->assertJsonPath('reply', 'A brief escape from work! Here is a short story for you.');
        $response->assertJsonPath('action', null);
    }

    public function test_assistant_recovers_reply_from_malformed_json_only_output(): void
    {
        $this->configureAiRaw("{\"reply\":\"Hello Temnotfo\nHow can I assist?\",\"action\":null}");

        $user = $this->user('Temnotfo Malinga', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk();

        $response->assertJsonPath('reply', "Hello Temnotfo\nHow can I assist?");
        $response->assertJsonPath('action', null);
    }

    public function test_assistant_answers_count_questions_from_light_ai_context_without_navigation(): void
    {
        $this->configureAi([
            'reply' => 'We have 2 suppliers in the CRM.',
            'action' => null,
        ]);
        $user = $this->user('Admin User', User::ROLE_SUPER_ADMIN);

        Supplier::query()->create([
            'supplier_code' => 'SUP-001',
            'name' => 'Alpha Supplies',
            'is_active' => true,
        ]);
        Supplier::query()->create([
            'supplier_code' => 'SUP-002',
            'name' => 'Beta Supplies',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'how many suppliers do we have?',
        ])->assertOk();

        $response->assertJsonPath('action', null);
        $this->assertSame('We have 2 suppliers in the CRM.', $response->json('reply'));

        Http::assertSent(fn ($request): bool => str_contains($request->body(), '\\"context_mode\\":\\"light\\"')
            && ! str_contains($request->body(), 'Alpha Supplies')
            && str_contains($request->body(), '\\"suppliers\\":2'));
    }

    public function test_assistant_uses_light_context_for_identity_questions(): void
    {
        $this->configureAi([
            'reply' => 'I am MIS, the assistant for this portal. I help you find records, answer CRM questions, and open the right pages.',
            'action' => null,
        ]);

        $user = $this->user('Temnotfo Malinga', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'first tell me who you are',
        ])->assertOk();

        $response->assertJsonPath('action', null);

        Http::assertSent(fn ($request): bool => str_contains($request->body(), '\\"context_mode\\":\\"light\\"')
            && str_contains($request->body(), 'first tell me who you are'));
    }

    public function test_assistant_scopes_record_context_for_record_search_questions(): void
    {
        $this->configureAi([
            'reply' => 'I found Alpha Supplies in the supplier register.',
            'action' => null,
        ]);

        $user = $this->user('Admin User', User::ROLE_SUPER_ADMIN);

        Supplier::query()->create([
            'supplier_code' => 'SUP-001',
            'name' => 'Alpha Supplies',
            'is_active' => true,
        ]);

        $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'find supplier Alpha Supplies',
        ])->assertOk();

        Http::assertSent(fn ($request): bool => str_contains($request->body(), '\\"context_mode\\":\\"scoped\\"')
            && str_contains($request->body(), 'Alpha Supplies')
            && str_contains($request->body(), '\\"suppliers\\":['));
    }

    public function test_assistant_validates_ai_navigation_action(): void
    {
        $this->configureAi([
            'reply' => 'Opening unpaid invoices.',
            'action' => [
                'type' => 'navigate',
                'module' => 'invoices',
                'filters' => [
                    'payment_state' => 'unpaid',
                ],
                'auto' => true,
            ],
        ]);
        $user = $this->user('Admin User', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'open unpaid invoices',
        ])->assertOk();

        $response->assertJsonPath('reply', 'Opening unpaid invoices.');
        $response->assertJsonPath('action.type', 'navigate');
        $this->assertStringContainsString('/invoices?', $response->json('action.url'));
        $this->assertStringContainsString('payment_state=unpaid', $response->json('action.url'));
    }

    public function test_assistant_does_not_navigate_for_analysis_questions(): void
    {
        $this->configureAi([
            'reply' => 'Sales are strong in quotation volume, but conversion is weak because only one invoice has been generated.',
            'action' => [
                'type' => 'navigate',
                'module' => 'sales_quotations',
                'filters' => [],
                'auto' => true,
            ],
        ]);
        $user = $this->user('Admin User', User::ROLE_SUPER_ADMIN);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'how are our sales? where are we falling short?',
        ])->assertOk();

        $response->assertJsonPath('reply', 'Sales are strong in quotation volume, but conversion is weak because only one invoice has been generated.');
        $response->assertJsonPath('action', null);
    }

    public function test_assistant_history_returns_latest_conversation_messages(): void
    {
        $this->configureAi([
            'reply' => 'Hi Thembela, how can I assist you today?',
            'action' => null,
        ]);
        $user = $this->user('Thembela Mthimkhulu', User::ROLE_DEPARTMENT_USER);

        $conversationId = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk()->json('conversation_id');

        $response = $this->actingAs($user)->getJson(route('assistant.history'))->assertOk();

        $response->assertJsonPath('conversation_id', $conversationId);
        $this->assertCount(2, $response->json('messages'));
        $this->assertSame('user', $response->json('messages.0.role'));
        $this->assertSame('assistant', $response->json('messages.1.role'));
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'username' => Str::slug($name, '.'),
            'email' => Str::slug($name, '.').'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function configureAi(?array $payload, int $status = 200): void
    {
        $this->configureAiRaw(
            $status === 200 ? json_encode($payload) : null,
            $status,
        );
    }

    private function configureAiRaw(?string $content, int $status = 200): void
    {
        config()->set('services.assistant_ai.provider', 'nvidia');
        config()->set('services.assistant_ai.remote_enabled', true);
        config()->set('services.assistant_ai.nvidia.api_key', 'testing-key');
        config()->set('services.assistant_ai.nvidia.base_url', 'https://integrate.api.nvidia.com/v1');
        config()->set('services.assistant_ai.nvidia.model', 'nvidia/llama-3.3-nemotron-super-49b-v1');

        Http::fake([
            'https://integrate.api.nvidia.com/v1/chat/completions' => Http::response(
                $status === 200
                    ? ['choices' => [['message' => ['content' => $content]]]]
                    : ['error' => ['message' => 'quota exceeded']],
                $status,
            ),
        ]);
    }
}
