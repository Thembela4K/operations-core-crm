<?php

namespace App\Services\Assistant;

use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\TenderProposal;
use App\Models\User;

class AssistantContextBuilder
{
    public function __construct(private readonly AssistantAccessService $access)
    {
    }

    public function build(User $user, string $message): array
    {
        return [
            'current_date' => now()->toDateString(),
            'user' => [
                'name' => $user->name,
                'role' => $user->role,
                'department' => $user->department?->name,
                'can_view_reports' => $user->canViewReports(),
                'can_manage_operations' => $user->canManage(),
            ],
            'modules' => $this->modules(),
            'counts' => $this->counts($user),
            'record_content_enabled' => (bool) config('services.ai.send_record_content'),
            'document_snippets' => $this->documentSnippets($user, $message),
        ];
    }

    private function modules(): array
    {
        return [
            'tender_proposals' => ['route' => 'tender-proposals.index', 'date_filter' => 'closing_date'],
            'quotation_requests' => ['route' => 'quotations.index', 'date_filter' => 'valid_until'],
            'documents' => ['route' => 'documents.index', 'filters' => ['module', 'linked_type', 'category', 'date_from', 'date_to', 'search']],
            'requisitions' => ['route' => 'requisitions.index', 'date_filter' => 'needed_by'],
            'tasks' => ['route' => 'tasks.index', 'filters' => ['status', 'priority', 'department_id', 'assigned_to']],
            'sales_quotations' => ['route' => 'sales-quotations.index', 'filters' => ['status', 'search']],
            'invoices' => ['route' => 'invoices.index', 'filters' => ['status', 'payment_state', 'search']],
            'approvals' => ['route' => 'approvals.index'],
            'notifications' => ['route' => 'notifications.index', 'filters' => ['state']],
        ];
    }

    private function counts(User $user): array
    {
        return [
            'tender_proposals' => TenderProposal::query()->visibleTo($user)->count(),
            'quotation_requests' => Quotation::query()->visibleTo($user)->count(),
            'requisitions' => Requisition::query()->visibleTo($user)->count(),
            'tasks' => CrmTask::query()->visibleTo($user)->count(),
            'sales_quotations' => SalesQuotation::query()->visibleTo($user)->count(),
            'invoices' => Invoice::query()->visibleTo($user)->count(),
            'unread_notifications' => CrmNotification::query()->where('user_id', $user->id)->whereNull('read_at')->count(),
        ];
    }

    private function documentSnippets(User $user, string $message): array
    {
        if (! (bool) config('services.ai.send_record_content')) {
            return [];
        }

        $terms = $this->terms($message);

        if ($terms === []) {
            return [];
        }

        return Document::query()
            ->with(['documentable', 'textIndex'])
            ->where(function ($query) use ($terms): void {
                foreach ($terms as $term) {
                    $query->orWhere('original_name', 'like', "%{$term}%")
                        ->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('tags', 'like', "%{$term}%")
                        ->orWhereHas('textIndex', fn ($text) => $text->where('content', 'like', "%{$term}%"));
                }
            })
            ->latest()
            ->limit(30)
            ->get()
            ->filter(fn (Document $document): bool => $this->access->canAccessDocument($user, $document))
            ->take(5)
            ->map(fn (Document $document): array => [
                'title' => $document->title ?: $document->original_name,
                'category' => $document->category,
                'module' => class_basename((string) $document->documentable_type),
                'uploaded_at' => $document->created_at?->toDateString(),
                'snippet' => mb_substr((string) $document->textIndex?->content, 0, 900),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function terms(string $message): array
    {
        $words = preg_split('/[^a-z0-9]+/i', mb_strtolower($message)) ?: [];
        $stopWords = ['show', 'open', 'find', 'me', 'the', 'and', 'for', 'with', 'last', 'this', 'next', 'month', 'documents', 'document', 'files', 'file'];

        return collect($words)
            ->filter(fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, $stopWords, true))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }
}
