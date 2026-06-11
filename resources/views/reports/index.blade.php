@extends('layouts.app')

@section('content')
    @php
        $maxRevenue = max(1, $departmentRevenue->max('total') ?? 0);
        $maxExpense = max(1, $departmentExpenses->max('total') ?? 0);
    @endphp

    <div>
        <h1 class="page-title">Company Reports</h1>
        <p class="page-subtitle">Director, reception, and business analyst visibility across finance and operations.</p>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card"><span>Quotation Pipeline</span><strong>E{{ number_format($salesQuotationTotal, 2) }}</strong><div class="metric-foot">All sales quotations</div></div>
        <div class="metric-card"><span>Approved Pipeline</span><strong>E{{ number_format($approvedQuotationTotal, 2) }}</strong><div class="metric-foot">Approved, sent, accepted, converted</div></div>
        <div class="metric-card"><span>Invoices</span><strong>E{{ number_format($invoiceTotal, 2) }}</strong><div class="metric-foot">Non-cancelled invoices</div></div>
        <div class="metric-card"><span>Outstanding</span><strong>E{{ number_format($outstandingTotal, 2) }}</strong><div class="metric-foot">Unpaid invoice balances</div></div>
        <div class="metric-card"><span>Expenses</span><strong>E{{ number_format($expenseTotal, 2) }}</strong><div class="metric-foot">Recorded expenses</div></div>
        <div class="metric-card"><span>Tasks</span><strong>{{ $taskCount }}</strong><div class="metric-foot">{{ $overdueTaskCount }} overdue</div></div>
        <div class="metric-card"><span>Attendance</span><strong>{{ $attendanceHours }}h</strong><div class="metric-foot">Recorded hours</div></div>
        <div class="metric-card"><span>Follow-ups</span><strong>{{ $openFollowUps }}</strong><div class="metric-foot">{{ $overdueFollowUps }} overdue</div></div>
        <div class="metric-card"><span>Suppliers</span><strong>{{ $supplierCount }}</strong><div class="metric-foot">E{{ number_format($purchaseTotal, 2) }} purchases</div></div>
        <div class="metric-card"><span>Documents</span><strong>{{ $documentCount }}</strong><div class="metric-foot">Registry files</div></div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel chart-panel">
            <div class="panel-heading"><h2 class="section-title">Department Revenue</h2><span>Invoice totals</span></div>
            <div class="bar-stack mt-5">
                @forelse($departmentRevenue as $row)
                    <div class="bar-row stat-tooltip" data-tooltip="{{ $row['department'] }}: E{{ number_format($row['total'], 2) }}" tabindex="0">
                        <div class="bar-row-meta"><span>{{ $row['department'] }}</span><strong>E{{ number_format($row['total'], 2) }}</strong></div>
                        <div class="bar-track"><i style="width: {{ max(4, ($row['total'] / $maxRevenue) * 100) }}%; background: #087aa5"></i></div>
                    </div>
                @empty
                    <p class="empty">No invoice revenue yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading"><h2 class="section-title">Department Expenses</h2><span>Expense totals</span></div>
            <div class="bar-stack mt-5">
                @forelse($departmentExpenses as $row)
                    <div class="bar-row stat-tooltip" data-tooltip="{{ $row['department'] }}: E{{ number_format($row['total'], 2) }}" tabindex="0">
                        <div class="bar-row-meta"><span>{{ $row['department'] }}</span><strong>E{{ number_format($row['total'], 2) }}</strong></div>
                        <div class="bar-track"><i style="width: {{ max(4, ($row['total'] / $maxExpense) * 100) }}%; background: #0f766e"></i></div>
                    </div>
                @empty
                    <p class="empty">No expenses yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel">
            <h2 class="section-title">Sales Quotation Status</h2>
            <div class="workflow-grid">
                @forelse($quotationStatusCounts as $status => $count)
                    <div><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                @empty
                    <p class="empty">No sales quotations.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Invoice Status</h2>
            <div class="workflow-grid">
                @forelse($invoiceStatusCounts as $status => $count)
                    <div><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                @empty
                    <p class="empty">No invoices.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Operations</h2>
            <div class="workflow-grid">
                @foreach($operationCounts as $label => $count)
                    <div><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section class="panel">
            <h2 class="section-title">Task Status</h2>
            <div class="workflow-grid">
                @forelse($taskStatusCounts as $status => $count)
                    <div><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                @empty
                    <p class="empty">No tasks.</p>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Approval Backlog</h2>
            <div class="workflow-grid">
                @foreach($approvalCounts as $label => $count)
                    <div><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                @endforeach
            </div>
        </section>
        <section class="panel">
            <h2 class="section-title">Task Workload</h2>
            <div class="workflow-grid">
                @forelse($departmentTaskWorkload as $row)
                    <div><span>{{ $row['department'] }}</span><strong>{{ $row['count'] }}</strong></div>
                @empty
                    <p class="empty">No open task workload.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
