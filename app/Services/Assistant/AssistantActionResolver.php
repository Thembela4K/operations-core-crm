<?php

namespace App\Services\Assistant;

use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\Department;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Models\User;
use Carbon\CarbonInterface;

class AssistantActionResolver
{
    private const MODULES = [
        'dashboard',
        'clients',
        'client_activities',
        'documents',
        'tender_proposals',
        'quotation_requests',
        'requisitions',
        'tasks',
        'sales_quotations',
        'invoices',
        'expenses',
        'approvals',
        'attendance',
        'suppliers',
        'reports',
        'notifications',
    ];

    public function __construct(private readonly AssistantAccessService $access)
    {
    }

    public function resolve(User $user, string $message, ?array $aiIntent = null): array
    {
        $local = $this->localInterpret($user, $message);

        if (in_array($local['intent'], ['greeting', 'wellbeing_redirect'], true)) {
            return $this->directReply($user, $local['intent']);
        }

        $ai = $this->sanitizeAiIntent($user, $aiIntent);
        $module = $local['module'] ?? $ai['module'] ?? 'dashboard';
        $filters = array_filter(array_merge($ai['filters'] ?? [], $local['filters'] ?? []), fn ($value): bool => $value !== null && $value !== '');

        if (($local['intent'] ?? null) === 'help' || ($ai['intent'] ?? null) === 'help') {
            return [
                'intent' => 'help',
                'reply' => $this->helpReply($module),
                'action' => null,
                'suggestions' => $this->suggestions($user),
                'filters' => [],
            ];
        }

        $url = $this->urlFor($module, $filters);
        $count = $this->countFor($user, $module, $filters);
        $label = $this->moduleLabel($module);
        $reply = $count === null
            ? "Opening {$label}."
            : "I found {$count} {$label}. Opening the filtered view.";

        return [
            'intent' => $local['intent'] ?? $ai['intent'] ?? 'navigate',
            'reply' => $reply,
            'action' => [
                'type' => 'navigate',
                'url' => $url,
                'label' => "Open {$label}",
                'auto' => true,
            ],
            'suggestions' => $this->suggestions($user),
            'filters' => $filters,
        ];
    }

    public function suggestions(User $user): array
    {
        $suggestions = [
            'Show overdue tender proposals',
            'Show quotation requests due next 5 days',
            'Show last month submitted tender documents',
            'Show my assigned tasks',
            'Show unread notifications',
        ];

        if ($user->canViewRequisitions()) {
            $suggestions[] = 'What requisitions need approval?';
        }

        if ($user->canViewReports()) {
            $suggestions[] = 'Open unpaid invoices';
            $suggestions[] = 'Show sales quotations awaiting approval';
        }

        return $suggestions;
    }

    private function directReply(User $user, string $intent): array
    {
        $reply = $intent === 'wellbeing_redirect'
            ? "I am sorry you are feeling that way, {$this->firstName($user)}. I can help reduce work pressure by finding CRM records, deadlines, documents, approvals, and reports for you."
            : "Hi {$this->firstName($user)}. Ask me to find tenders, quotation requests, requisitions, documents, invoices, tasks, approvals, or deadlines.";

        return [
            'intent' => $intent,
            'reply' => $reply,
            'action' => null,
            'suggestions' => $this->suggestions($user),
            'filters' => [],
        ];
    }

    private function localInterpret(User $user, string $message): array
    {
        $lower = mb_strtolower(trim($message));

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)\b/', $lower)) {
            return ['intent' => 'greeting', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(feel down|sad|depressed|stressed|anxious|not okay|not ok)\b/', $lower)) {
            return ['intent' => 'wellbeing_redirect', 'module' => null, 'filters' => []];
        }

        $module = $this->moduleFromText($lower);
        $filters = $this->filtersFromText($user, $lower, $module);

        if (str_contains($lower, 'how do i') || str_contains($lower, 'how can i') || str_contains($lower, 'explain')) {
            return ['intent' => 'help', 'module' => $module, 'filters' => $filters];
        }

        return ['intent' => 'navigate', 'module' => $module, 'filters' => $filters];
    }

    private function moduleFromText(string $lower): ?string
    {
        if (preg_match('/\b(document|documents|file|files|attachment|attachments)\b/', $lower)) {
            return 'documents';
        }

        if (str_contains($lower, 'quotation request')) {
            return 'quotation_requests';
        }

        if (str_contains($lower, 'sales quotation') || str_contains($lower, 'client quotation') || str_contains($lower, 'client quote')) {
            return 'sales_quotations';
        }

        if (str_contains($lower, 'tender') || str_contains($lower, 'proposal')) {
            return 'tender_proposals';
        }

        if (str_contains($lower, 'requisition')) {
            return 'requisitions';
        }

        if (str_contains($lower, 'invoice') || str_contains($lower, 'unpaid')) {
            return 'invoices';
        }

        if (str_contains($lower, 'approval') || str_contains($lower, 'approve')) {
            return 'approvals';
        }

        if (str_contains($lower, 'task')) {
            return 'tasks';
        }

        if (str_contains($lower, 'attendance') || str_contains($lower, 'clock')) {
            return 'attendance';
        }

        if (str_contains($lower, 'supplier') || str_contains($lower, 'purchase')) {
            return 'suppliers';
        }

        if (str_contains($lower, 'client') || str_contains($lower, 'customer')) {
            return 'clients';
        }

        if (str_contains($lower, 'notification') || str_contains($lower, 'unread')) {
            return 'notifications';
        }

        if (str_contains($lower, 'report')) {
            return 'reports';
        }

        return null;
    }

    private function filtersFromText(User $user, string $lower, ?string $module): array
    {
        $filters = [];
        $range = $this->dateRangeFromText($lower);

        if ($range) {
            $filters['date_from'] = $range[0]->toDateString();
            $filters['date_to'] = $range[1]->toDateString();
        }

        if (str_contains($lower, 'overdue')) {
            $filters['deadline_window'] = 'overdue';
        } elseif (str_contains($lower, 'next 5') || str_contains($lower, 'due soon') || str_contains($lower, 'upcoming')) {
            $filters['deadline_window'] = 'next_5';
        } elseif (preg_match('/\btoday\b/', $lower)) {
            $filters['deadline_window'] = 'today';
        }

        if ($module === 'documents') {
            if (str_contains($lower, 'submitted') && (str_contains($lower, 'tender') || str_contains($lower, 'proposal'))) {
                $filters['module'] = 'submission';
                $filters['linked_type'] = 'tender_proposal';
            } elseif (str_contains($lower, 'submitted') && str_contains($lower, 'quotation')) {
                $filters['module'] = 'submission';
                $filters['linked_type'] = 'quotation_request';
            } elseif (str_contains($lower, 'tender')) {
                $filters['module'] = 'tender_proposal';
            } elseif (str_contains($lower, 'quotation request')) {
                $filters['module'] = 'quotation_request';
            } elseif (str_contains($lower, 'requisition')) {
                $filters['module'] = 'requisition';
            }

            if (str_contains($lower, 'technical')) {
                $filters['category'] = 'technical_proposal';
            } elseif (str_contains($lower, 'financial')) {
                $filters['category'] = 'financial_proposal';
            }
        }

        if ($module === 'requisitions' && (str_contains($lower, 'approval') || str_contains($lower, 'approve') || str_contains($lower, 'submitted'))) {
            $filters['status'] = Requisition::STATUS_SUBMITTED;
        }

        if ($module === 'sales_quotations' && (str_contains($lower, 'approval') || str_contains($lower, 'submitted'))) {
            $filters['status'] = SalesQuotation::STATUS_SUBMITTED;
        }

        if ($module === 'invoices' && (str_contains($lower, 'unpaid') || str_contains($lower, 'outstanding'))) {
            $filters['payment_state'] = 'unpaid';
        }

        if ($module === 'tasks' && (str_contains($lower, 'my ') || str_contains($lower, 'assigned to me'))) {
            $filters['assigned_to'] = $user->id;
        }

        if ($module === 'notifications' && str_contains($lower, 'unread')) {
            $filters['state'] = 'unread';
        }

        return $filters;
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null
     */
    private function dateRangeFromText(string $lower): ?array
    {
        if (str_contains($lower, 'last month')) {
            $start = now()->subMonthNoOverflow()->startOfMonth();

            return [$start, $start->copy()->endOfMonth()];
        }

        if (str_contains($lower, 'this month')) {
            return [now()->startOfMonth(), now()->endOfMonth()];
        }

        if (str_contains($lower, 'last week')) {
            return [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()];
        }

        if (str_contains($lower, 'today')) {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        return null;
    }

    private function sanitizeAiIntent(User $user, ?array $aiIntent): array
    {
        if (! $aiIntent) {
            return ['intent' => null, 'module' => null, 'filters' => []];
        }

        $module = $this->normalizeModule((string) ($aiIntent['module'] ?? ''));
        $filters = [];

        foreach ((array) ($aiIntent['filters'] ?? []) as $key => $value) {
            if (! is_scalar($value) || blank($value)) {
                continue;
            }

            $filters[$key] = trim((string) $value);
        }

        if (isset($filters['department_name']) && $user->canViewPortfolio()) {
            $department = Department::query()
                ->where('name', 'like', '%'.$filters['department_name'].'%')
                ->first();

            if ($department) {
                $filters['department_id'] = $department->id;
            }
        }

        unset($filters['department_name']);

        if (($filters['assigned_to_me'] ?? null) === 'true') {
            $filters['assigned_to'] = $user->id;
        }

        unset($filters['assigned_to_me']);

        return [
            'intent' => in_array(($aiIntent['intent'] ?? null), ['navigate', 'summary', 'help', 'unknown'], true) ? $aiIntent['intent'] : null,
            'module' => $module,
            'filters' => $this->allowedFilters($filters),
        ];
    }

    private function normalizeModule(string $module): ?string
    {
        $module = str_replace('-', '_', mb_strtolower($module));
        $aliases = [
            'tenders' => 'tender_proposals',
            'tender' => 'tender_proposals',
            'quotation' => 'quotation_requests',
            'quotations' => 'quotation_requests',
            'quotes' => 'sales_quotations',
            'sales_quotes' => 'sales_quotations',
            'files' => 'documents',
            'approvals_center' => 'approvals',
        ];

        $module = $aliases[$module] ?? $module;

        return in_array($module, self::MODULES, true) ? $module : null;
    }

    private function allowedFilters(array $filters): array
    {
        $allowed = ['search', 'status', 'priority', 'department_id', 'deadline_window', 'workflow_status', 'assignment_state', 'date_from', 'date_to', 'category', 'module', 'linked_type', 'payment_state', 'assigned_to', 'state'];

        return collect($filters)
            ->only($allowed)
            ->map(fn ($value) => is_numeric($value) ? $value : trim((string) $value))
            ->filter(fn ($value): bool => $value !== '')
            ->all();
    }

    private function urlFor(?string $module, array $filters): string
    {
        $route = match ($module) {
            'clients' => 'clients.index',
            'client_activities' => 'client-activities.index',
            'documents' => 'documents.index',
            'tender_proposals' => 'tender-proposals.index',
            'quotation_requests' => 'quotations.index',
            'requisitions' => 'requisitions.index',
            'tasks' => 'tasks.index',
            'sales_quotations' => 'sales-quotations.index',
            'invoices' => 'invoices.index',
            'expenses' => 'expenses.index',
            'approvals' => 'approvals.index',
            'attendance' => 'attendance.index',
            'suppliers' => 'suppliers.index',
            'reports' => 'reports.index',
            'notifications' => 'notifications.index',
            default => 'dashboard',
        };

        return route($route, $this->queryForModule($module, $filters));
    }

    private function queryForModule(?string $module, array $filters): array
    {
        $allowed = match ($module) {
            'documents' => ['search', 'category', 'date_from', 'date_to', 'module', 'linked_type'],
            'tender_proposals', 'quotation_requests' => ['search', 'status', 'department_id', 'workflow_status', 'assignment_state', 'deadline_window', 'date_from', 'date_to'],
            'requisitions' => ['search', 'status', 'priority', 'category', 'department_id', 'date_from', 'date_to'],
            'tasks' => ['search', 'status', 'priority', 'department_id', 'assigned_to'],
            'sales_quotations' => ['search', 'status'],
            'invoices' => ['search', 'status', 'payment_state'],
            'attendance' => ['department_id', 'status', 'date_from', 'date_to'],
            'notifications' => ['state'],
            default => ['search'],
        };

        return collect($filters)->only($allowed)->all();
    }

    private function countFor(User $user, ?string $module, array $filters): ?int
    {
        return match ($module) {
            'documents' => $this->documentCount($user, $filters),
            'tender_proposals' => $this->tenderQuery($user, $filters)->count(),
            'quotation_requests' => $this->quotationQuery($user, $filters)->count(),
            'requisitions' => $this->requisitionQuery($user, $filters)->count(),
            'tasks' => $this->taskQuery($user, $filters)->count(),
            'sales_quotations' => $this->salesQuotationQuery($user, $filters)->count(),
            'invoices' => $this->invoiceQuery($user, $filters)->count(),
            'notifications' => $this->notificationQuery($user, $filters)->count(),
            'approvals' => $user->canViewReports() ? $this->approvalCount() : 0,
            default => null,
        };
    }

    private function documentCount(User $user, array $filters): int
    {
        return $this->documentQuery($filters)
            ->get()
            ->filter(fn (Document $document): bool => $this->access->canAccessDocument($user, $document))
            ->count();
    }

    private function documentQuery(array $filters)
    {
        return Document::query()
            ->with(['documentable', 'textIndex'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('original_name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('tags', 'like', "%{$search}%")
                        ->orWhereHas('textIndex', fn ($text) => $text->where('content', 'like', "%{$search}%"));
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['module'] ?? null, fn ($query, string $module) => $this->applyDocumentModuleFilter($query, $module))
            ->when($filters['linked_type'] ?? null, fn ($query, string $linkedType) => $this->applyDocumentLinkedTypeFilter($query, $linkedType));
    }

    private function applyDocumentModuleFilter($query, string $module): void
    {
        $type = match ($module) {
            'tender_proposal' => TenderProposal::class,
            'quotation_request' => Quotation::class,
            'submission' => Submission::class,
            'requisition' => Requisition::class,
            'task' => CrmTask::class,
            'sales_quotation' => SalesQuotation::class,
            'invoice' => Invoice::class,
            default => null,
        };

        if ($type) {
            $query->where('documentable_type', $type);
        }
    }

    private function applyDocumentLinkedTypeFilter($query, string $linkedType): void
    {
        $type = match ($linkedType) {
            'tender_proposal' => TenderProposal::class,
            'quotation_request' => Quotation::class,
            default => null,
        };

        if (! $type) {
            return;
        }

        $query->whereHasMorph('documentable', [Submission::class], fn ($submission) => $submission->where('submittable_type', $type));
    }

    private function tenderQuery(User $user, array $filters)
    {
        return TenderProposal::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['deadline_window'] ?? null, fn ($query, string $window) => $this->applyDeadlineWindow($query, 'closing_date', $window))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('closing_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('closing_date', '<=', $date));
    }

    private function quotationQuery(User $user, array $filters)
    {
        return Quotation::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['deadline_window'] ?? null, fn ($query, string $window) => $this->applyDeadlineWindow($query, 'valid_until', $window))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('valid_until', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('valid_until', '<=', $date));
    }

    private function requisitionQuery(User $user, array $filters)
    {
        return Requisition::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('needed_by', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('needed_by', '<=', $date));
    }

    private function taskQuery(User $user, array $filters)
    {
        return CrmTask::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['assigned_to'] ?? null, fn ($query, int|string $id) => $query->where('assigned_to', $id));
    }

    private function salesQuotationQuery(User $user, array $filters)
    {
        return SalesQuotation::query()
            ->visibleTo($user)
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
    }

    private function invoiceQuery(User $user, array $filters)
    {
        return Invoice::query()
            ->visibleTo($user)
            ->when(($filters['payment_state'] ?? null) === 'unpaid', fn ($query) => $query->where('balance_due', '>', 0)->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED]))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));
    }

    private function notificationQuery(User $user, array $filters)
    {
        return CrmNotification::query()
            ->where('user_id', $user->id)
            ->when(($filters['state'] ?? null) === 'unread', fn ($query) => $query->whereNull('read_at'));
    }

    private function approvalCount(): int
    {
        return SalesQuotation::query()->where('status', SalesQuotation::STATUS_SUBMITTED)->count()
            + Requisition::query()->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])->count();
    }

    private function applyDeadlineWindow($query, string $column, string $window): void
    {
        match ($window) {
            'overdue' => $query->whereDate($column, '<', now()->toDateString()),
            'today' => $query->whereDate($column, now()->toDateString()),
            'next_5' => $query->whereBetween($column, [now()->toDateString(), now()->addDays(5)->toDateString()]),
            'future' => $query->whereDate($column, '>', now()->addDays(5)->toDateString()),
            default => null,
        };
    }

    private function helpReply(?string $module): string
    {
        return match ($module) {
            'requisitions' => 'Use Requisitions to request funds, attach supporting documents, submit for director approval, and track release of funds.',
            'documents' => 'Use Document Registry to search uploaded and generated documents by date, category, module, tags, and indexed text.',
            'tender_proposals' => 'Tender Proposals track tender intake, documents, department assignment, due dates, and returned submissions.',
            'quotation_requests' => 'Quotation Requests track incoming quotation work assigned to departments, including due dates and returned responses.',
            'invoices' => 'Invoices track issued client invoices, balances, payments, and unpaid work.',
            default => 'I can help you find records, deadlines, documents, approvals, reports, tasks, and notifications in this CRM.',
        };
    }

    private function moduleLabel(?string $module): string
    {
        return match ($module) {
            'documents' => 'documents',
            'tender_proposals' => 'tender proposals',
            'quotation_requests' => 'quotation requests',
            'requisitions' => 'requisitions',
            'tasks' => 'tasks',
            'sales_quotations' => 'sales quotations',
            'invoices' => 'invoices',
            'notifications' => 'notifications',
            'approvals' => 'approval items',
            default => 'results',
        };
    }

    private function firstName(User $user): string
    {
        return strtok($user->name, ' ') ?: $user->name;
    }
}
