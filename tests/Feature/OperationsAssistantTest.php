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
            && $request['stream'] === false);
    }

    public function test_assistant_answers_count_questions_from_ai_context_without_navigation(): void
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

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Alpha Supplies')
            && str_contains($request->body(), '\\"suppliers\\":2'));
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
        config()->set('services.assistant_ai.provider', 'nvidia');
        config()->set('services.assistant_ai.remote_enabled', true);
        config()->set('services.assistant_ai.nvidia.api_key', 'testing-key');
        config()->set('services.assistant_ai.nvidia.base_url', 'https://integrate.api.nvidia.com/v1');
        config()->set('services.assistant_ai.nvidia.model', 'nvidia/llama-3.3-nemotron-super-49b-v1');

        Http::fake([
            'https://integrate.api.nvidia.com/v1/chat/completions' => Http::response(
                $status === 200
                    ? ['choices' => [['message' => ['content' => json_encode($payload)]]]]
                    : ['error' => ['message' => 'quota exceeded']],
                $status,
            ),
        ]);
    }
}
