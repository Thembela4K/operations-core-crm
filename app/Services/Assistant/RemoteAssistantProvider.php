<?php

namespace App\Services\Assistant;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RemoteAssistantProvider
{
    public function isConfigured(): bool
    {
        return config('services.assistant_ai.remote_enabled') === true
            && config('services.assistant_ai.provider') === 'nvidia'
            && filled(config('services.assistant_ai.nvidia.api_key'));
    }

    public function unavailableReply(?int $status = null): string
    {
        if ($status === 429) {
            return 'MIS AI quota has been exceeded. Please try again later.';
        }

        return 'MIS AI service is unavailable right now, or the quota may be exceeded. Please try again later.';
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function complete(User $user, array $history, array $crmContext): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'reply' => $this->unavailableReply(),
                'status' => null,
            ];
        }

        try {
            $response = Http::withToken(config('services.assistant_ai.nvidia.api_key'))
                ->acceptJson()
                ->timeout(config('services.assistant_ai.nvidia.timeout'))
                ->post(rtrim(config('services.assistant_ai.nvidia.base_url'), '/').'/chat/completions', [
                    'model' => config('services.assistant_ai.nvidia.model'),
                    'messages' => $this->messages($user, $history, $crmContext),
                    'temperature' => config('services.assistant_ai.nvidia.temperature'),
                    'top_p' => config('services.assistant_ai.nvidia.top_p'),
                    'max_tokens' => config('services.assistant_ai.nvidia.max_tokens'),
                    'chat_template_kwargs' => [
                        'thinking' => false,
                    ],
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning('MIS remote assistant request failed.', [
                    'status' => $response->status(),
                    'provider' => config('services.assistant_ai.provider'),
                ]);

                return [
                    'ok' => false,
                    'reply' => $this->unavailableReply($response->status()),
                    'status' => $response->status(),
                ];
            }

            $content = data_get($response->json(), 'choices.0.message.content');

            if (! is_string($content) || trim($content) === '') {
                return [
                    'ok' => false,
                    'reply' => $this->unavailableReply(),
                    'status' => null,
                ];
            }

            return [
                'ok' => true,
                ...$this->parseContent($content),
            ];
        } catch (Throwable $exception) {
            Log::warning('MIS remote assistant unavailable.', [
                'provider' => config('services.assistant_ai.provider'),
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'reply' => $this->unavailableReply(),
                'status' => null,
            ];
        }
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function messages(User $user, array $history, array $crmContext): array
    {
        return [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($user, $crmContext),
            ],
            ...array_map(
                fn (array $message): array => [
                    'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                    'content' => $message['content'],
                ],
                $history,
            ),
        ];
    }

    private function systemPrompt(User $user, array $crmContext): string
    {
        $department = $user->department?->name ?? 'No department';
        $contextJson = json_encode($crmContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are MIS, the assistant inside the Datamatics Eswatini Business Operations Portal.
User: {$user->name}
Role: {$user->role}
Department: {$department}

You are the primary AI for the system.
- Answer with real CRM facts from CRM_CONTEXT when the user asks about records, totals, statuses, deadlines, documents, clients, suppliers, finance, operations, tasks, approvals, or notifications.
- For count/fact questions, answer directly and set action to null.
- For clear navigation requests such as open, show, list, view, take me to, or go to, set action.type to "navigate".
- Do not invent CRM facts that are not in CRM_CONTEXT.
- Do not browse the internet.
- Do not claim an action was completed unless it is represented by the returned action object.
- If the user asks to create, edit, approve, delete, send, or release funds, explain that this action needs a supported CRM action workflow before execution, then offer to open the correct module.
- Keep answers professional and concise.

Return ONLY valid JSON with this shape:
{"reply":"natural answer for the user","action":null}
or:
{"reply":"natural answer for the user","action":{"type":"navigate","module":"suppliers","filters":{},"auto":true}}

Supported navigate modules:
dashboard, clients, suppliers, documents, tender_proposals, quotation_requests, requisitions, tasks, sales_quotations, invoices, approvals, attendance, reports, notifications.

Allowed common filters:
search, status, priority, category, department_id, date_from, date_to, deadline_window, payment_state, state, module, linked_type.

CRM_CONTEXT:
{$contextJson}
PROMPT;
    }

    private function parseContent(string $content): array
    {
        $content = trim($content);
        $json = $this->extractJson($content);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [
                'reply' => $content,
                'action' => null,
            ];
        }

        return [
            'reply' => is_string($decoded['reply'] ?? null) && trim($decoded['reply']) !== ''
                ? trim($decoded['reply'])
                : 'I reviewed the CRM context, but I could not form a clear response.',
            'action' => is_array($decoded['action'] ?? null) ? $decoded['action'] : null,
        ];
    }

    private function extractJson(string $content): string
    {
        if (preg_match('/```json\s*(.*?)```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/```\s*(.*?)```/s', $content, $matches)) {
            return trim($matches[1]);
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($content, $start, $end - $start + 1);
        }

        return $content;
    }
}
