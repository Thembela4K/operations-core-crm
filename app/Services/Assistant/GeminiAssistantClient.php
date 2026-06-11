<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;

class GeminiAssistantClient
{
    public function interpret(string $message, array $context): ?array
    {
        if (app()->environment('testing')) {
            return null;
        }

        $key = config('services.ai.gemini_key');
        $model = config('services.ai.gemini_model', 'gemini-3.5-flash');

        if (config('services.ai.provider') !== 'gemini' || blank($key)) {
            return null;
        }

        try {
            $response = Http::timeout(18)
                ->acceptJson()
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $this->prompt($message, $context)],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                return null;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $decoded = json_decode($this->cleanJson($text), true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function prompt(string $message, array $context): string
    {
        return json_encode([
            'instruction' => 'You are Operations Assistant for the Datamatics Eswatini Business Operations Portal. Classify the user request into CRM module, intent, filters, and a concise reply. Return strict JSON only. You may not create, edit, approve, send, or delete records. If the user asks for emotional support or general therapy, be kind but redirect to CRM help.',
            'allowed_intents' => ['greeting', 'wellbeing_redirect', 'navigate', 'summary', 'help', 'unknown'],
            'allowed_modules' => ['dashboard', 'clients', 'client_activities', 'documents', 'tender_proposals', 'quotation_requests', 'requisitions', 'tasks', 'sales_quotations', 'invoices', 'expenses', 'approvals', 'attendance', 'suppliers', 'reports', 'notifications'],
            'allowed_filters' => ['search', 'status', 'priority', 'department_name', 'deadline_window', 'workflow_status', 'assignment_state', 'date_from', 'date_to', 'category', 'module', 'linked_type', 'payment_state', 'assigned_to_me', 'state'],
            'filter_rules' => [
                'deadline_window' => ['overdue', 'today', 'next_5', 'future'],
                'linked_type' => ['tender_proposal', 'quotation_request'],
                'payment_state' => ['unpaid'],
                'date_format' => 'YYYY-MM-DD',
            ],
            'json_schema' => [
                'intent' => 'string',
                'module' => 'string|null',
                'filters' => 'object',
                'reply' => 'short string|null',
                'confidence' => 'number 0..1',
            ],
            'context' => $context,
            'user_message' => $message,
        ], JSON_PRETTY_PRINT);
    }

    private function cleanJson(string $text): string
    {
        $text = trim($text);
        $text = preg_replace('/^```json\s*/i', '', $text) ?? $text;
        $text = preg_replace('/^```\s*/', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        return trim($text);
    }
}
