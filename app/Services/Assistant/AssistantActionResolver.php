<?php

namespace App\Services\Assistant;

use App\Models\Client;
use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\Supplier;
use App\Models\TenderProposal;
use App\Models\User;
use Carbon\CarbonInterface;

class AssistantActionResolver
{
    public function __construct(private readonly AssistantAccessService $access)
    {
    }

    public function resolve(User $user, string $message): array
    {
        $local = $this->localInterpret($user, $message);

        if (in_array($local['intent'], ['greeting', 'wellbeing_redirect', 'date', 'thanks', 'capabilities', 'identity', 'unknown'], true)) {
            return $this->directReply($user, $local['intent']);
        }

        $module = $local['module'] ?? 'dashboard';
        $filters = array_filter($local['filters'] ?? [], fn ($value): bool => $value !== null && $value !== '');

        if (($local['intent'] ?? null) === 'help') {
            return [
                'intent' => 'help',
                'reply' => $this->helpReply($module),
                'action' => null,
                'filters' => [],
            ];
        }

        $count = $this->countFor($user, $module, $filters);
        $label = $this->moduleLabel($module);

        if (($local['intent'] ?? null) === 'count') {
            return [
                'intent' => 'count',
                'reply' => $this->countReply($module, $label, $count, $filters),
                'action' => null,
                'filters' => $filters,
            ];
        }

        $url = $this->urlFor($module, $filters);
        $reply = $this->navigationReply($label, $count);

        return [
            'intent' => $local['intent'] ?? 'navigate',
            'reply' => $reply,
            'action' => [
                'type' => 'navigate',
                'url' => $url,
                'label' => "Open {$label}",
                'auto' => true,
            ],
            'filters' => $filters,
        ];
    }

    private function directReply(User $user, string $intent): array
    {
        $reply = match ($intent) {
            'wellbeing_redirect' => "I am sorry you are feeling that way, {$this->firstName($user)}. I am not a counsellor, but I can help reduce the work pressure by finding the exact CRM records, deadlines, documents, approvals, and reports you need.",
            'date' => $this->dateReply(),
            'thanks' => "You are welcome, {$this->firstName($user)}. Send me a record type, a date window, a status, or a department, and I will open the relevant CRM view for you.",
            'capabilities' => $this->capabilitiesReply($user),
            'identity' => "I am MIS, the local operations helper built into this portal. I can read the CRM structure, interpret common work requests, search indexed records and documents, and open filtered pages. I do not use an external AI service and I do not change records.",
            'unknown' => "I did not get a clear CRM action from that. Try wording it like: show overdue tenders, find unpaid invoices, open submitted tender documents from last month, or show my assigned tasks.",
            default => "Hi {$this->firstName($user)}. I can help you find tenders, quotation requests, requisitions, documents, invoices, tasks, approvals, notifications, and deadlines. Ask naturally, for example: show me quotation requests due this week.",
        };

        return [
            'intent' => $intent,
            'reply' => $reply,
            'action' => null,
            'filters' => [],
        ];
    }

    private function localInterpret(User $user, string $message): array
    {
        $lower = $this->normalizeText($message);

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)\b/', $lower)) {
            return ['intent' => 'greeting', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(what(?:\'s| is) today(?:\'s)? date|today(?:\'s)? date|current date|date today|what day is it|which day is it)\b/', $lower)) {
            return ['intent' => 'date', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(thanks|thank you|appreciate it|great thanks|okay thanks)\b/', $lower)) {
            return ['intent' => 'thanks', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(what can you do|help me|assist me|how can you help)\b/', $lower)) {
            return ['intent' => 'capabilities', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(who are you|what are you|your name|are you ai|are you chatgpt)\b/', $lower)) {
            return ['intent' => 'identity', 'module' => null, 'filters' => []];
        }

        if (preg_match('/\b(feel down|sad|depressed|stressed|anxious|not okay|not ok)\b/', $lower)) {
            return ['intent' => 'wellbeing_redirect', 'module' => null, 'filters' => []];
        }

        $module = $this->moduleFromText($lower);
        $filters = $this->filtersFromText($user, $lower, $module);

        if ($this->isCountQuestion($lower)) {
            return ['intent' => 'count', 'module' => $module, 'filters' => $filters];
        }

        if (str_contains($lower, 'how do i') || str_contains($lower, 'how can i') || str_contains($lower, 'explain')) {
            return ['intent' => 'help', 'module' => $module, 'filters' => $filters];
        }

        if (! $module && ! $this->hasActionLanguage($lower)) {
            return ['intent' => 'unknown', 'module' => null, 'filters' => []];
        }

        return ['intent' => 'navigate', 'module' => $module, 'filters' => $filters];
    }

    private function moduleFromText(string $lower): ?string
    {
        if (str_contains($lower, 'dashboard') || str_contains($lower, 'home page') || str_contains($lower, 'overview')) {
            return 'dashboard';
        }

        if (preg_match('/\b(document|documents|file|files|attachment|attachments)\b/', $lower)) {
            return 'documents';
        }

        if (str_contains($lower, 'quotation request') || str_contains($lower, 'quotation requests') || str_contains($lower, 'request for quotation')) {
            return 'quotation_requests';
        }

        if (str_contains($lower, 'sales quotation') || str_contains($lower, 'client quotation') || str_contains($lower, 'client quote')) {
            return 'sales_quotations';
        }

        if (str_contains($lower, 'tender') || str_contains($lower, 'proposal') || str_contains($lower, 'bid')) {
            return 'tender_proposals';
        }

        if (str_contains($lower, 'requisition')) {
            return 'requisitions';
        }

        if (str_contains($lower, 'invoice') || str_contains($lower, 'unpaid') || str_contains($lower, 'outstanding balance') || str_contains($lower, 'money owed')) {
            return 'invoices';
        }

        if (str_contains($lower, 'approval') || str_contains($lower, 'approve') || str_contains($lower, 'pending decision')) {
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

        if (str_contains($lower, 'client') || str_contains($lower, 'customer') || str_contains($lower, 'account')) {
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
        $search = $this->searchTermFromText($lower, $module);

        if ($range) {
            $filters['date_from'] = $range[0]->toDateString();
            $filters['date_to'] = $range[1]->toDateString();
        }

        if ($search) {
            $filters['search'] = $search;
        }

        if ($departmentId = $this->departmentIdFromText($user, $lower)) {
            $filters['department_id'] = $departmentId;
        }

        if (str_contains($lower, 'overdue') || str_contains($lower, 'late')) {
            $filters['deadline_window'] = 'overdue';
        } elseif (str_contains($lower, 'next 5') || str_contains($lower, 'next five') || str_contains($lower, 'due soon') || str_contains($lower, 'upcoming')) {
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

        if ($module === 'requisitions' && (str_contains($lower, 'approval') || str_contains($lower, 'approve') || str_contains($lower, 'submitted') || str_contains($lower, 'pending'))) {
            $filters['status'] = Requisition::STATUS_SUBMITTED;
        }

        if ($module === 'sales_quotations' && (str_contains($lower, 'approval') || str_contains($lower, 'submitted') || str_contains($lower, 'pending'))) {
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
        if (str_contains($lower, 'yesterday')) {
            return [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()];
        }

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

        if (str_contains($lower, 'this week')) {
            return [now()->startOfWeek(), now()->endOfWeek()];
        }

        if (str_contains($lower, 'today')) {
            return [now()->startOfDay(), now()->endOfDay()];
        }

        return null;
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
            'clients' => $this->clientQuery($filters)->count(),
            'documents' => $this->documentCount($user, $filters),
            'tender_proposals' => $this->tenderQuery($user, $filters)->count(),
            'quotation_requests' => $this->quotationQuery($user, $filters)->count(),
            'requisitions' => $this->requisitionQuery($user, $filters)->count(),
            'tasks' => $this->taskQuery($user, $filters)->count(),
            'sales_quotations' => $this->salesQuotationQuery($user, $filters)->count(),
            'invoices' => $this->invoiceQuery($user, $filters)->count(),
            'notifications' => $this->notificationQuery($user, $filters)->count(),
            'approvals' => $user->canViewReports() ? $this->approvalCount() : 0,
            'suppliers' => $this->supplierQuery($user, $filters)->count(),
            default => null,
        };
    }

    private function clientQuery(array $filters)
    {
        return Client::query()
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('client_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
    }

    private function supplierQuery(User $user, array $filters)
    {
        return Supplier::query()
            ->visibleTo($user)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('supplier_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            });
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
            'requisitions' => 'Requisitions are for internal fund requests. A department prepares the request, adds line items and supporting documents, submits it for director approval, and then tracks approval, rejection, or funds release.',
            'documents' => 'The Document Registry searches uploaded and generated files across the CRM. You can ask for documents by module, category, date window, filename, tags, or indexed document text.',
            'tender_proposals' => 'Tender Proposals track tender intake from the first uploaded document through assignment, due dates, department submissions, and final returned work.',
            'quotation_requests' => 'Quotation Requests track incoming quotation work assigned to departments, including request documents, due dates, response uploads, and submitted drafts or finished responses.',
            'invoices' => 'Invoices track client billing, status, due dates, payments, and outstanding balances. Ask for unpaid invoices, overdue invoices, or invoices by client/reference.',
            'tasks' => 'Tasks track work, owners, departments, priorities, deadlines, comments, and completion status. Ask for your tasks, overdue tasks, or work assigned to a department.',
            default => 'I can help you find records, open filtered pages, explain CRM workflows, show deadlines, locate documents, check approvals, and answer simple system questions such as today\'s date.',
        };
    }

    private function moduleLabel(?string $module): string
    {
        return match ($module) {
            'clients' => 'clients',
            'documents' => 'documents',
            'tender_proposals' => 'tender proposals',
            'quotation_requests' => 'quotation requests',
            'requisitions' => 'requisitions',
            'tasks' => 'tasks',
            'sales_quotations' => 'sales quotations',
            'invoices' => 'invoices',
            'notifications' => 'notifications',
            'approvals' => 'approval items',
            'suppliers' => 'suppliers',
            default => 'results',
        };
    }

    private function firstName(User $user): string
    {
        return strtok($user->name, ' ') ?: $user->name;
    }

    private function normalizeText(string $message): string
    {
        $lower = mb_strtolower(trim($message));
        $replacements = [
            'todays' => "today's",
            'qoutation' => 'quotation',
            'quotaton' => 'quotation',
            'qotation' => 'quotation',
            'tendar' => 'tender',
            'documet' => 'document',
            'documnet' => 'document',
            'requisiton' => 'requisition',
            'aproval' => 'approval',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $lower);
    }

    private function hasActionLanguage(string $lower): bool
    {
        return preg_match('/\b(show|open|find|search|list|take me|go to|display|check|view|where|which|what)\b/', $lower) === 1;
    }

    private function isCountQuestion(string $lower): bool
    {
        return preg_match('/\b(how many|how much|count|number of|total number of|what(?:\'s| is) the total|what(?:\'s| is) our total)\b/', $lower) === 1;
    }

    private function dateReply(): string
    {
        return 'Today is '.now()->format('l, F j, Y').'. The current system time is '.now()->format('H:i').' in the '.config('app.timezone').' timezone.';
    }

    private function capabilitiesReply(User $user): string
    {
        $extra = $user->canViewReports()
            ? ' You can also ask for company-wide reports, unpaid invoices, pending approvals, and department workload.'
            : ' I will keep results limited to records your profile is allowed to view.';

        return 'I can understand common work requests in plain English, keep a short conversation history, search indexed document text, count matching records, explain workflows, and open filtered CRM pages automatically.'.$extra;
    }

    private function navigationReply(string $label, ?int $count): string
    {
        if ($count === null) {
            return "I have understood the request. I am opening {$label} now.";
        }

        if ($count === 0) {
            return "I did not find any {$label} matching that request. I am opening the filtered view anyway so you can confirm the result or adjust the filters.";
        }

        $noun = $count === 1 ? rtrim($label, 's') : $label;

        return "I found {$count} {$noun} matching your request. I am opening the filtered view now with the relevant filters already applied.";
    }

    private function countReply(?string $module, string $label, ?int $count, array $filters): string
    {
        if (! $module) {
            return 'I can count records for you, but I need the record type first. Ask something like: how many suppliers do we have, how many clients are registered, or how many unpaid invoices are there?';
        }

        if ($count === null) {
            return "I understood that you want a count for {$label}, but I do not have a direct database count for that area yet.";
        }

        $noun = $count === 1 ? rtrim($label, 's') : $label;

        if ($filters !== []) {
            return "I found {$count} {$noun} matching that question.";
        }

        return "We have {$count} {$noun} in the CRM.";
    }

    private function searchTermFromText(string $lower, ?string $module): ?string
    {
        if (! $module) {
            return null;
        }

        if (preg_match('/\b(?:search|find|look for)\b.*?\b(?:for|called|named|about|containing)\s+(.+)$/', $lower, $matches)) {
            return $this->cleanSearchTerm($matches[1]);
        }

        if (preg_match('/\b(?:client|customer|invoice|task|document|file|tender|requisition)\s+([a-z0-9][a-z0-9 ._\/-]{2,})$/', $lower, $matches)) {
            return $this->cleanSearchTerm($matches[1]);
        }

        return null;
    }

    private function cleanSearchTerm(string $term): ?string
    {
        $term = trim(preg_replace('/\b(from|for|today|this week|this month|last week|last month|overdue|unpaid|submitted|pending)\b.*/', '', $term) ?? $term);
        $term = trim($term, " \t\n\r\0\x0B'\".,");
        $genericTerms = ['document', 'documents', 'file', 'files', 'tender', 'tenders', 'proposal', 'proposals', 'quotation', 'quotations', 'request', 'requests', 'requisition', 'requisitions', 'invoice', 'invoices', 'task', 'tasks', 'client', 'clients', 'customer', 'customers'];

        return mb_strlen($term) >= 3 && ! in_array($term, $genericTerms, true) ? $term : null;
    }

    private function departmentIdFromText(User $user, string $lower): ?int
    {
        if (! $user->canViewPortfolio()) {
            return null;
        }

        $aliases = [
            'mis' => 'MIS Department',
            'it' => 'IT Department',
            'gis' => 'GIS Department',
            'admin' => 'Admin (Reception)',
            'reception' => 'Admin (Reception)',
        ];

        foreach ($aliases as $needle => $name) {
            if (! preg_match('/\b'.preg_quote($needle, '/').'\b/', $lower)) {
                continue;
            }

            $department = \App\Models\Department::query()
                ->where('name', 'like', '%'.$name.'%')
                ->orWhere('name', $name)
                ->first();

            return $department?->id;
        }

        return null;
    }
}
