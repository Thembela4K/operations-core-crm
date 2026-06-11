@extends('layouts.app')

@section('content')
    @php
        $operationTotal = max(1, $tenderCount + $quotationCount + $requisitionCount);
        $openOperations = $openTenders + $openQuotations + $openRequisitions;
        $deadlineColors = ['Overdue' => 'bg-rose-700', 'Today' => 'bg-amber-600', 'Next 5 Days' => 'bg-[#087aa5]'];
        $deadlineMax = max(1, $deadlineBands->max() ?? 0);
        $assignmentTotal = max(1, $assignmentStatusCounts->sum());
        $salesTotal = max(1, $salesQuotationStatusCounts->sum());
        $invoiceStatusTotal = max(1, $invoiceStatusCounts->sum());
    @endphp

    <div class="dashboard-header">
        <div>
            <h1 class="page-title">Datamatics Eswatini Dashboard</h1>
            <p class="page-subtitle">Company view across clients, finance, operations, assignments, and deadlines.</p>
        </div>
        @if($sppraUrl)
            <a class="btn-primary" href="{{ $sppraUrl }}" target="_blank" rel="noopener noreferrer">Open SPPRA Tender Site</a>
        @endif
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card stat-tooltip" data-tooltip="{{ $clientCount }} active and inactive clients in the CRM" tabindex="0">
            <span>Clients</span>
            <strong>{{ $clientCount }}</strong>
            <div class="metric-foot">Client register</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="Sales quotation pipeline total: E{{ number_format($salesQuotationPipeline, 2) }}" tabindex="0">
            <span>Sales Pipeline</span>
            <strong>E{{ number_format($salesQuotationPipeline, 0) }}</strong>
            <div class="metric-foot">{{ $salesQuotationCount }} sales quotations</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="Invoice total: E{{ number_format($invoiceTotal, 2) }}" tabindex="0">
            <span>Invoices</span>
            <strong>E{{ number_format($invoiceTotal, 0) }}</strong>
            <div class="metric-foot">{{ $invoiceCount }} invoices</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="Outstanding invoice balances: E{{ number_format($outstandingTotal, 2) }}" tabindex="0">
            <span>Outstanding</span>
            <strong>E{{ number_format($outstandingTotal, 0) }}</strong>
            <div class="metric-foot">Unpaid balances</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="Recorded expenses: E{{ number_format($expenseTotal, 2) }}" tabindex="0">
            <span>Expenses</span>
            <strong>E{{ number_format($expenseTotal, 0) }}</strong>
            <div class="metric-foot">Company costs</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $pendingRequisitionApprovals }} requisitions waiting for review or approval" tabindex="0">
            <span>Requisitions</span>
            <strong>{{ $requisitionCount }}</strong>
            <div class="metric-foot">{{ $openRequisitions }} open requests</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $overdueTaskCount }} overdue open tasks" tabindex="0">
            <span>Tasks</span>
            <strong>{{ $taskCount }}</strong>
            <div class="metric-foot">{{ $openTaskCount }} open</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $attendanceHours }} recorded attendance hours" tabindex="0">
            <span>Attendance</span>
            <strong>{{ $attendanceHours }}h</strong>
            <div class="metric-foot">Clocked hours</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $overdueFollowUps }} overdue client follow-ups" tabindex="0">
            <span>Follow-ups</span>
            <strong>{{ $openFollowUps }}</strong>
            <div class="metric-foot">Open client actions</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $unreadCrmNotifications }} unread CRM notifications" tabindex="0">
            <span>Notifications</span>
            <strong>{{ $unreadCrmNotifications }}</strong>
            <div class="metric-foot">{{ $documentCount }} documents | {{ $supplierCount }} suppliers</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_0.85fr]">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Finance Workflow</h2>
                <span>Approval and invoice status</span>
            </div>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                <div>
                    <div class="chart-label">Sales Quotations</div>
                    <div class="workflow-grid">
                        @forelse($salesQuotationStatusCounts as $status => $count)
                            <div class="stat-tooltip" data-tooltip="{{ $status }}: {{ $count }} sales quotations, {{ round(($count / $salesTotal) * 100) }}%" tabindex="0">
                                <span>{{ $status }}</span>
                                <strong>{{ $count }}</strong>
                                <div class="metric-line"><i style="width: {{ max(4, ($count / $salesTotal) * 100) }}%"></i></div>
                            </div>
                        @empty
                            <p class="empty">No sales quotations yet.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <div class="chart-label">Invoices</div>
                    <div class="workflow-grid">
                        @forelse($invoiceStatusCounts as $status => $count)
                            <div class="stat-tooltip" data-tooltip="{{ $status }}: {{ $count }} invoices, {{ round(($count / $invoiceStatusTotal) * 100) }}%" tabindex="0">
                                <span>{{ $status }}</span>
                                <strong>{{ $count }}</strong>
                                <div class="metric-line metric-line-teal"><i style="width: {{ max(4, ($count / $invoiceStatusTotal) * 100) }}%"></i></div>
                            </div>
                        @empty
                            <p class="empty">No invoices yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Operations Snapshot</h2>
                <span>{{ $tenderCount + $quotationCount + $requisitionCount }} operation records</span>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div class="metric-card">
                    <span>Tender Proposals</span>
                    <strong>{{ $tenderCount }}</strong>
                    <div class="metric-line"><i style="width: {{ ($tenderCount / $operationTotal) * 100 }}%"></i></div>
                </div>
                <div class="metric-card">
                    <span>Quotation Requests</span>
                    <strong>{{ $quotationCount }}</strong>
                    <div class="metric-line metric-line-teal"><i style="width: {{ ($quotationCount / $operationTotal) * 100 }}%"></i></div>
                </div>
                <div class="metric-card">
                    <span>Requisitions</span>
                    <strong>{{ $requisitionCount }}</strong>
                    <div class="metric-line"><i style="width: {{ ($requisitionCount / $operationTotal) * 100 }}%"></i></div>
                </div>
                <div class="metric-card">
                    <span>Open Operations</span>
                    <strong>{{ $openOperations }}</strong>
                    <div class="metric-foot">Tender, quotation request, and requisition work</div>
                </div>
                <div class="metric-card">
                    <span>Unread Assignments</span>
                    <strong>{{ $unreadAssignments }}</strong>
                    <div class="metric-foot">{{ $failedAssignments }} email failures</div>
                </div>
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Deadline Pressure</h2>
                <span>{{ $deadlineBands->sum() }} urgent</span>
            </div>
            <div class="deadline-chart">
                @foreach($deadlineBands as $label => $count)
                    @php($height = max(8, ($count / $deadlineMax) * 100))
                    <div class="deadline-column stat-tooltip" data-tooltip="{{ $label }}: {{ $count }} deadline items" tabindex="0">
                        <div class="deadline-bar">
                            <i class="{{ $deadlineColors[$label] }}" style="height: {{ $height }}%"></i>
                        </div>
                        <strong>{{ $count }}</strong>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Department Workload</h2>
                <span>Active assignments</span>
            </div>
            <div class="bar-stack mt-5">
                @forelse($departmentWorkload as $row)
                    <div class="bar-row stat-tooltip" data-tooltip="{{ $row['department'] }}: {{ $row['count'] }} active assignments" tabindex="0">
                        <div class="bar-row-meta">
                            <span>{{ $row['department'] }}</span>
                            <strong>{{ $row['count'] }}</strong>
                        </div>
                        <div class="bar-track">
                            <i style="width: {{ max(4, ($row['count'] / $maxDepartmentWorkload) * 100) }}%; background: #087aa5"></i>
                        </div>
                    </div>
                @empty
                    <p class="empty">No assignments yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Assignment Workflow</h2>
                <span>{{ $assignmentStatusCounts->sum() }} assignments</span>
            </div>
            <div class="workflow-grid">
                @forelse($assignmentStatusCounts as $status => $count)
                    <div class="stat-tooltip" data-tooltip="{{ $status ?: 'Unspecified' }}: {{ $count }} assignments, {{ round(($count / $assignmentTotal) * 100) }}%" tabindex="0">
                        <span>{{ $status ?: 'Unspecified' }}</span>
                        <strong>{{ $count }}</strong>
                        <div class="metric-line metric-line-teal"><i style="width: {{ max(4, ($count / $assignmentTotal) * 100) }}%"></i></div>
                    </div>
                @empty
                    <p class="empty">No assignment activity yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel compact-list" id="unread-assignments">
            <div class="panel-heading">
                <h2 class="section-title">Unread Assignments</h2>
                <span>{{ $unreadAssignments }}</span>
            </div>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($unreadAssignmentList as $assignment)
                    @php($record = $assignment->assignable)
                    @php($route = $record instanceof \App\Models\TenderProposal ? route('tender-proposals.show', $record) : route('quotations.show', $record))
                    <a class="list-row" href="{{ $route }}">
                        <span>
                            <strong>{{ $record->tender_reference ?? $record->quotation_code }}</strong>
                            <small>{{ $record->title ?? $record->opportunity }}</small>
                        </span>
                        <em>{{ $assignment->department->name }}</em>
                    </a>
                @empty
                    <p class="empty">No unread assignments.</p>
                @endforelse
            </div>
        </section>

        <section class="panel compact-list">
            <div class="panel-heading">
                <h2 class="section-title">Upcoming Deadlines</h2>
                <span>{{ $upcomingItems->count() }}</span>
            </div>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($upcomingItems as $item)
                    <div class="list-row">
                        <span>
                            <strong>{{ $item['reference'] }}</strong>
                            <small>{{ $item['title'] }}</small>
                        </span>
                        <em>{{ $item['days_left'] }} days</em>
                    </div>
                @empty
                    <p class="empty">No urgent deadlines.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
