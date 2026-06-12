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
            $this->contextForMessage($user, $message),
        );

        $result = $this->assistantResult($completion, $message);

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

    private function assistantResult(array $completion, string $message): array
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

        $requestedNavigation = $this->allowsNavigation($message);
        $action = $requestedNavigation
            ? $this->actionExecutor->execute($completion['action'] ?? null)
            : null;

        return [
            'intent' => $action ? 'ai_action' : 'ai_answer',
            'reply' => $completion['reply'],
            'action' => $action,
            'filters' => is_array($completion['action']['filters'] ?? null) ? $completion['action']['filters'] : [],
            'assistant_mode' => 'remote_ai',
        ];
    }

    private function allowsNavigation(string $message): bool
    {
        $text = Str::of($message)->lower()->squish()->toString();

        if ($this->isAnalysisQuestion($text)) {
            return false;
        }

        if ((bool) preg_match('/\b(open|go to|navigate to|take me to|bring up|pull up|send me to)\b/i', $text)) {
            return true;
        }

        if (! (bool) preg_match('/\b(show|list|view)\b/i', $text)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(tender|proposal|quotation request|request quotation|rfq|requisition|task|document|file|invoice|sales quotation|quote|client|customer|supplier|approval|attendance|report|notification|overdue|pending|submitted|unpaid|paid|due|approved|rejected|draft|finished|active|inactive|last month|this month|today|tomorrow|yesterday)\b/i',
            $text,
        );
    }

    private function isAnalysisQuestion(string $text): bool
    {
        return (bool) preg_match(
            '/\b(how are|how is|how do|how did|why|where are we|where do we|falling short|fall short|improve|performance|doing|trend|trends|analyse|analyze|summary|summarize|explain|what is|what are|what should|what can)\b/i',
            $text,
        );
    }

    private function contextForMessage(User $user, string $message): array
    {
        $sections = $this->recordSectionsForMessage($message);

        if ($sections === []) {
            return $this->crmContext->buildLight($user);
        }

        return $this->crmContext->buildScoped($user, $sections);
    }

    /**
     * @return array<int, string>
     */
    private function recordSectionsForMessage(string $message): array
    {
        $text = Str::of($message)->lower()->squish()->toString();

        if ($this->isLightAssistantQuestion($text)) {
            return [];
        }

        $sections = [];
        $map = [
            'clients' => ['client', 'customer', 'contact'],
            'suppliers' => ['supplier', 'vendor', 'procurement'],
            'tender_proposals' => ['tender', 'proposal', 'sppra', 'esppra'],
            'quotation_requests' => ['quotation request', 'request quotation', 'rfq', 'quote request'],
            'sales_quotations' => ['sales quotation', 'estimate', 'quote', 'quotation'],
            'invoices' => ['invoice', 'payment', 'paid', 'unpaid', 'overdue invoice'],
            'requisitions' => ['requisition', 'funds', 'cash request', 'approval money'],
            'tasks' => ['task', 'workload', 'assignment'],
            'documents' => ['document', 'file', 'attachment', 'download', 'submitted document'],
            'notifications' => ['notification', 'alert', 'unread'],
        ];

        foreach ($map as $section => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $sections[] = $section;
                    break;
                }
            }
        }

        if (str_contains($text, 'approval') || str_contains($text, 'approve')) {
            $sections[] = 'sales_quotations';
            $sections[] = 'requisitions';
        }

        if (str_contains($text, 'submitted') || str_contains($text, 'submission')) {
            $sections[] = 'documents';
            $sections[] = 'tender_proposals';
            $sections[] = 'quotation_requests';
        }

        if ($this->isCountQuestion($text) && ! $this->hasStatusModifier($text)) {
            return [];
        }

        return array_values(array_unique($sections));
    }

    private function isLightAssistantQuestion(string $text): bool
    {
        return $text === 'help'
            || (bool) preg_match('/\b(hi|hello|hey|how are you|who are you|what are you|your name|tell me who you are|what can you do|today|date|time)\b/i', $text);
    }

    private function isCountQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(how many|count|total number|number of)\b/i', $text);
    }

    private function hasStatusModifier(string $text): bool
    {
        return (bool) preg_match('/\b(overdue|pending|submitted|unpaid|paid|due|approved|rejected|draft|finished|active|inactive|late|closed|open)\b/i', $text);
    }
}
