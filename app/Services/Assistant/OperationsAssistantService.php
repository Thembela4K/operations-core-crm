<?php

namespace App\Services\Assistant;

use App\Models\AiActionLog;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Str;

class OperationsAssistantService
{
    public function __construct(
        private readonly RemoteAssistantProvider $remoteAssistantProvider,
        private readonly AssistantCrmContextService $crmContext,
        private readonly AssistantActionExecutor $actionExecutor,
    ) {
    }

    public function handle(User $user, string $message, ?int $conversationId = null): array
    {
        $conversation = $this->conversation($user, $message, $conversationId);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => $message,
        ]);

        $completion = $this->remoteAssistantProvider->complete(
            $user,
            $this->conversationContext($conversation),
            $this->crmContext->build($user),
        );

        $result = $this->assistantResult($completion);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $result['reply'],
            'metadata' => [
                'action' => $result['action'],
                'filters' => $result['filters'] ?? [],
                'assistant_mode' => $result['assistant_mode'] ?? 'local',
            ],
        ]);
        $conversation->touch();

        AiActionLog::query()->create([
            'user_id' => $user->id,
            'ai_conversation_id' => $conversation->id,
            'intent' => $result['intent'] ?? null,
            'action_type' => $result['action']['type'] ?? 'reply',
            'status' => 'completed',
            'input' => ['message' => $message],
            'output' => $result,
            'route' => $result['action']['url'] ?? null,
        ]);

        return [
            'conversation_id' => $conversation->id,
            'reply' => $result['reply'],
            'action' => $result['action'],
        ];
    }

    public function startConversation(User $user): array
    {
        $conversation = AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => 'New MIS chat',
            'metadata' => [
                'provider' => 'local',
                'assistant' => 'mis',
            ],
        ]);

        return [
            'conversation_id' => $conversation->id,
            'messages' => [],
        ];
    }

    public function conversations(User $user): array
    {
        return [
            'conversations' => AiConversation::query()
                ->where('user_id', $user->id)
                ->with('latestMessage')
                ->withCount('messages')
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map(function (AiConversation $conversation): array {
                    $latestMessage = $conversation->latestMessage;
                    $title = $conversation->title;

                    if ($latestMessage && $title === 'New MIS chat') {
                        $title = Str::limit($latestMessage->content, 70);
                    }

                    return [
                        'id' => $conversation->id,
                        'title' => $title,
                        'message_count' => $conversation->messages_count,
                        'latest_message' => $latestMessage?->content,
                        'updated_at' => $conversation->updated_at?->toIso8601String(),
                    ];
                })
                ->all(),
        ];
    }

    public function history(User $user, ?int $conversationId = null): array
    {
        $query = AiConversation::query()->where('user_id', $user->id);
        $conversation = $conversationId
            ? $query->find($conversationId)
            : $query->latest('updated_at')->first();

        if (! $conversation) {
            return [
                'conversation_id' => null,
                'messages' => [],
            ];
        }

        $messages = $conversation->messages()
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'conversation_id' => $conversation->id,
            'messages' => $messages,
        ];
    }

    private function conversation(User $user, string $message, ?int $conversationId): AiConversation
    {
        if ($conversationId) {
            $conversation = AiConversation::query()
                ->where('user_id', $user->id)
                ->find($conversationId);

            if ($conversation) {
                return $conversation;
            }
        }

        return AiConversation::query()->create([
            'user_id' => $user->id,
            'title' => Str::limit($message, 90),
            'metadata' => [
                'provider' => 'local',
            ],
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function conversationContext(AiConversation $conversation): array
    {
        return $conversation->messages()
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->all();
    }

    private function assistantResult(array $completion): array
    {
        if (! ($completion['ok'] ?? false)) {
            return [
                'intent' => 'ai_unavailable',
                'reply' => $completion['reply'],
                'action' => null,
                'filters' => [],
                'assistant_mode' => 'remote_unavailable',
            ];
        }

        $action = $this->actionExecutor->execute($completion['action'] ?? null);

        return [
            'intent' => $action ? 'ai_action' : 'ai_answer',
            'reply' => $completion['reply'],
            'action' => $action,
            'filters' => is_array($completion['action']['filters'] ?? null) ? $completion['action']['filters'] : [],
            'assistant_mode' => 'remote_ai',
        ];
    }
}
