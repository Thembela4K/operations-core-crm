@extends('layouts.app')

@section('content')
    @php
        $awaitingQuotationApproval = (int) ($salesQuotationStatusCounts['Submitted for Approval'] ?? 0);
        $awaitingTotal = $pendingRequisitionApprovals + $awaitingQuotationApproval;
        $deadlineMax = max(1, $deadlineBands->max() ?? 0);
        $deadlineColors = ['Overdue' => 'bg-rose-700', 'Today' => 'bg-amber-600', 'Next 5 Days' => 'bg-[#087aa5]'];
        $departmentRows = $departmentWorkload->take(5);
    @endphp

    <div class="dashboard-header">
        <div>
            <h1 class="page-title">Director Overview</h1>
            <p class="page-subtitle">The decisions waiting on you, the money that matters, and where the work stands.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <div class="cat-clock">
                <span class="cat-clock__time" id="cat-clock">{{ now()->format('H:i:s') }}</span>
                <span class="cat-clock__date" id="cat-date">{{ now()->format('l, j F Y') }}</span>
            </div>
            @if($sppraUrl)
                <a class="btn-primary" href="{{ $sppraUrl }}" target="_blank" rel="noopener noreferrer">SPPRA Tender Site</a>
            @endif
        </div>
    </div>

    {{-- 1. What needs a decision --}}
    <section class="panel mt-6">
        <div class="panel-heading">
            <h2 class="section-title">Waiting On You</h2>
            <span>{{ $awaitingTotal }} decision{{ $awaitingTotal === 1 ? '' : 's' }}</span>
        </div>

        @if($awaitingTotal === 0)
            <p class="mt-4 text-sm text-neutral-600">Nothing is waiting for a decision. The queues are clear.</p>
        @else
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <a class="rounded border border-neutral-200 px-4 py-3 text-sm hover:border-[#087aa5]" href="{{ route('approvals.index') }}">
                    <span class="block text-xs uppercase tracking-wide text-neutral-500">Requisitions</span>
                    <strong class="mt-1 block text-2xl text-neutral-900">{{ $pendingRequisitionApprovals }}</strong>
                    <span class="text-xs text-neutral-500">submitted, awaiting approval</span>
                </a>
                <a class="rounded border border-neutral-200 px-4 py-3 text-sm hover:border-[#087aa5]" href="{{ route('approvals.index') }}">
                    <span class="block text-xs uppercase tracking-wide text-neutral-500">From the IT desk</span>
                    <strong class="mt-1 block text-2xl text-neutral-900">{{ $itRequisitionsAwaiting }}</strong>
                    <span class="text-xs text-neutral-500">IT needs money approved</span>
                </a>
                <a class="rounded border border-neutral-200 px-4 py-3 text-sm hover:border-[#087aa5]" href="{{ route('sales-quotations.index') }}">
                    <span class="block text-xs uppercase tracking-wide text-neutral-500">Sales quotations</span>
                    <strong class="mt-1 block text-2xl text-neutral-900">{{ $awaitingQuotationApproval }}</strong>
                    <span class="text-xs text-neutral-500">submitted for approval</span>
                </a>
            </div>
        @endif
    </section>

    {{-- 2. Grouped summary: money, operations, team, service --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Money</h2>
                <span>All figures in E</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Invoiced</dt>
                    <dd class="text-xl font-semibold">{{ number_format((float) $invoiceTotal, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Outstanding</dt>
                    <dd class="text-xl font-semibold text-rose-700">{{ number_format((float) $outstandingTotal, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Expenses</dt>
                    <dd class="text-xl font-semibold">{{ number_format((float) $expenseTotal, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Sales pipeline</dt>
                    <dd class="text-xl font-semibold">{{ number_format((float) $salesQuotationPipeline, 2) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-neutral-500">
                {{ $invoiceCount }} invoices | {{ $requisitionCount }} requisitions | {{ $pendingRequisitionApprovals }} awaiting approval
            </p>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Operations</h2>
                <span>{{ $tenderCount + $quotationCount }} opportunities</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Open requisitions</dt>
                    <dd class="text-xl font-semibold">{{ $openRequisitions }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Open tenders</dt>
                    <dd class="text-xl font-semibold">{{ $openTenders }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Open quotation requests</dt>
                    <dd class="text-xl font-semibold">{{ $openQuotations }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Submissions</dt>
                    <dd class="text-xl font-semibold">{{ $submissionCount }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-neutral-500">
                {{ $clientCount }} clients | {{ $supplierCount }} suppliers | {{ $documentCount }} documents
            </p>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Team</h2>
                <span>Workload and delivery</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Open tasks</dt>
                    <dd class="text-xl font-semibold">{{ $openTaskCount }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Overdue tasks</dt>
                    <dd class="text-xl font-semibold {{ $overdueTaskCount > 0 ? 'text-rose-700' : '' }}">{{ $overdueTaskCount }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Open assignments</dt>
                    <dd class="text-xl font-semibold">{{ $openAssignments }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Overdue assignments</dt>
                    <dd class="text-xl font-semibold {{ $overdueAssignments > 0 ? 'text-rose-700' : '' }}">{{ $overdueAssignments }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-neutral-500">
                {{ $attendanceHours }} attendance hours recorded | {{ $unreadCrmNotifications }} unread alerts
            </p>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Service Desk</h2>
                <span>Your requests to IT</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">Your open tickets</dt>
                    <dd class="text-xl font-semibold">{{ $myOpenTickets }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-neutral-500">IT awaiting your approval</dt>
                    <dd class="text-xl font-semibold">{{ $itRequisitionsAwaiting }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-neutral-500">
                Tickets go to the IT Officer. Money for a fix reaches you as a requisition.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a class="btn-secondary" href="{{ route('tickets.create') }}">Raise a ticket</a>
                <a class="btn-secondary" href="{{ route('tickets.index') }}">My tickets</a>
                <a class="btn-secondary" href="{{ route('assignments.index', ['scope' => 'all']) }}">Assignments</a>
            </div>
        </section>
    </div>

    {{-- 3. Today's meetings --}}
    @if($todayMeetings->isNotEmpty())
        <section class="panel mt-6">
            <div class="panel-heading">
                <h2 class="section-title">Today's Meetings</h2>
                <span>{{ $todayMeetings->count() }} scheduled</span>
            </div>
            <ul class="mt-3 space-y-2">
                @foreach($todayMeetings as $meeting)
                    <li class="flex flex-wrap items-center justify-between gap-2 rounded border border-neutral-200 px-3 py-2 text-sm">
                        <span>
                            <strong>{{ $meeting->starts_at?->format('H:i') }}</strong>
                            @if($meeting->ends_at) &ndash; {{ $meeting->ends_at?->format('H:i') }} @endif
                            {{ $meeting->label() }}
                            @if($meeting->location)<span class="text-neutral-500">| {{ $meeting->location }}</span>@endif
                        </span>
                        <a class="link" href="{{ route('assignments.show', $meeting) }}">Open</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 4. Deadlines and workload, summarised side by side --}}
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Deadlines</h2>
                <span>{{ $deadlineBands->sum() }} in the next {{ \App\Services\ReminderService::DASHBOARD_WINDOW_DAYS }} days</span>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3">
                @foreach($deadlineBands as $label => $count)
                    <div class="rounded border border-neutral-200 px-3 py-2">
                        <span class="block text-xs uppercase tracking-wide text-neutral-500">{{ $label }}</span>
                        <strong class="text-lg">{{ $count }}</strong>
                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded bg-neutral-100">
                            <div class="h-1.5 {{ $deadlineColors[$label] }}" style="width: {{ max(6, ($count / $deadlineMax) * 100) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <ul class="mt-4 divide-y divide-neutral-100">
                @forelse($upcomingItems->take(5) as $item)
                    <li class="py-2 text-sm">
                        <strong>{{ $item['title'] }}</strong>
                        <span class="block text-xs text-neutral-500">
                            {{ $item['type'] }} | {{ $item['due'] ?? '' }}
                            @if(($item['days_left'] ?? 0) < 0)
                                | <span class="font-semibold text-rose-700">overdue</span>
                            @endif
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-sm text-neutral-600">No deadlines in the window.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-heading">
                <h2 class="section-title">Department Workload</h2>
                <span>Top {{ $departmentRows->count() }}</span>
            </div>

            <div class="mt-4 space-y-3">
                @forelse($departmentRows as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span>{{ $row['department'] }}</span>
                            <strong>{{ $row['count'] }}</strong>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded bg-neutral-100">
                            <div class="h-2 bg-[#087aa5]" style="width: {{ max(4, ($row['count'] / $maxDepartmentWorkload) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="empty">No active workload.</p>
                @endforelse
            </div>

            @if($overdueFollowUps > 0)
                <p class="mt-4 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    {{ $overdueFollowUps }} client follow-up{{ $overdueFollowUps === 1 ? '' : 's' }} overdue.
                </p>
            @endif
        </section>
    </div>

    <script>
        (function () {
            var clock = document.getElementById('cat-clock');
            var dateLine = document.getElementById('cat-date');
            if (!clock || !dateLine) return;

            var timeFormat = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Africa/Johannesburg',
                hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
            });
            var dateFormat = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Africa/Johannesburg',
                weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
            });

            function tick() {
                var now = new Date();
                clock.textContent = timeFormat.format(now);
                dateLine.textContent = dateFormat.format(now);
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
@endsection
