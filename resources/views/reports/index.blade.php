@extends('layouts.app')

@section('content')
    @php
        $maxRevenue = max(1, $departmentRevenue->max('total') ?? 0);
        $maxExpense = max(1, $departmentExpenses->max('total') ?? 0);
    @endphp

    <div class="dashboard-header">
        <div>
            <h1 class="page-title">Company Reports</h1>
            <p class="page-subtitle">Visual performance, workload, and operations trends across the company.</p>
        </div>
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card">
            <span>Quotation Pipeline</span>
            <strong>E{{ number_format($salesQuotationTotal, 2) }}</strong>
            <div class="metric-foot">All sales quotations</div>
        </div>
        <div class="metric-card">
            <span>Approved Pipeline</span>
            <strong>E{{ number_format($approvedQuotationTotal, 2) }}</strong>
            <div class="metric-foot">Approved, sent, accepted, converted</div>
        </div>
        <div class="metric-card">
            <span>Invoices</span>
            <strong>E{{ number_format($invoiceTotal, 2) }}</strong>
            <div class="metric-foot">Non-cancelled invoices</div>
        </div>
        <div class="metric-card">
            <span>Outstanding</span>
            <strong>E{{ number_format($outstandingTotal, 2) }}</strong>
            <div class="metric-foot">Unpaid invoice balances</div>
        </div>
        <div class="metric-card">
            <span>Collection Rate</span>
            <strong>{{ $collectionRate }}%</strong>
            <div class="metric-line"><i style="width: {{ $collectionRate }}%"></i></div>
        </div>
        <div class="metric-card">
            <span>Expense Ratio</span>
            <strong>{{ $expenseRatio }}%</strong>
            <div class="metric-line metric-line-teal"><i style="width: {{ $expenseRatio }}%"></i></div>
        </div>
        <div class="metric-card">
            <span>Tasks</span>
            <strong>{{ $taskCount }}</strong>
            <div class="metric-foot">{{ $overdueTaskCount }} overdue</div>
        </div>
        <div class="metric-card">
            <span>Attendance</span>
            <strong>{{ $attendanceHours }}h</strong>
            <div class="metric-foot">Recorded hours</div>
        </div>
    </div>

    <section class="panel chart-panel mt-6">
        <div class="panel-heading">
            <h2 class="section-title">Financial Trend</h2>
            <span>Last 6 months</span>
        </div>
        <div class="trend-legend">
            <span><i class="bg-[#087aa5]"></i>Revenue</span>
            <span><i class="bg-[#0f766e]"></i>Payments</span>
            <span><i class="bg-[#c27803]"></i>Expenses</span>
            <span><i class="bg-neutral-900"></i>Quotations</span>
        </div>
        <div class="trend-chart" aria-label="Financial trend chart">
            @foreach($monthlyTrend as $month)
                @php
                    $revenueHeight = $month['revenue'] > 0 ? max(6, ($month['revenue'] / $maxTrendAmount) * 100) : 0;
                    $paymentsHeight = $month['payments'] > 0 ? max(6, ($month['payments'] / $maxTrendAmount) * 100) : 0;
                    $expensesHeight = $month['expenses'] > 0 ? max(6, ($month['expenses'] / $maxTrendAmount) * 100) : 0;
                    $quoteHeight = $month['quotations'] > 0 ? max(8, ($month['quotations'] / $maxTrendQuotes) * 100) : 0;
                @endphp
                <div class="trend-month stat-tooltip" tabindex="0" data-tooltip="{{ $month['label'] }}: Revenue E{{ number_format($month['revenue'], 2) }}, Payments E{{ number_format($month['payments'], 2) }}, Expenses E{{ number_format($month['expenses'], 2) }}, Quotations {{ $month['quotations'] }}">
                    <div class="trend-bars">
                        <i class="trend-bar revenue" style="height: {{ $revenueHeight }}%"></i>
                        <i class="trend-bar payments" style="height: {{ $paymentsHeight }}%"></i>
                        <i class="trend-bar expenses" style="height: {{ $expensesHeight }}%"></i>
                    </div>
                    <div class="trend-quote" style="height: {{ $quoteHeight }}%"><span>{{ $month['quotations'] }}</span></div>
                    <strong>{{ $month['label'] }}</strong>
                </div>
            @endforeach
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Department Revenue</h2>
                <span>Invoice totals</span>
            </div>
            <div class="bar-stack mt-5">
                @forelse($departmentRevenue as $row)
                    <div class="bar-row stat-tooltip" data-tooltip="{{ $row['department'] }}: E{{ number_format($row['total'], 2) }}" tabindex="0">
                        <div class="bar-row-meta"><span>{{ $row['department'] }}</span><strong>E{{ number_format($row['total'], 2) }}</strong></div>
                        <div class="bar-track"><i style="width: {{ $row['total'] > 0 ? max(4, ($row['total'] / $maxRevenue) * 100) : 0 }}%; background: #087aa5"></i></div>
                    </div>
                @empty
                    <p class="empty">No invoice revenue yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Department Expenses</h2>
                <span>Expense totals</span>
            </div>
            <div class="bar-stack mt-5">
                @forelse($departmentExpenses as $row)
                    <div class="bar-row stat-tooltip" data-tooltip="{{ $row['department'] }}: E{{ number_format($row['total'], 2) }}" tabindex="0">
                        <div class="bar-row-meta"><span>{{ $row['department'] }}</span><strong>E{{ number_format($row['total'], 2) }}</strong></div>
                        <div class="bar-track"><i style="width: {{ $row['total'] > 0 ? max(4, ($row['total'] / $maxExpense) * 100) : 0 }}%; background: #0f766e"></i></div>
                    </div>
                @empty
                    <p class="empty">No expenses yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 2xl:grid-cols-3">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Sales Quotation Status</h2>
                <span>Pipeline</span>
            </div>
            <div class="progress-list">
                @forelse($quotationStatusCounts as $status => $count)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $status }}: {{ $count }}">
                        <div class="progress-meta"><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                        <div class="progress-track"><i style="width: {{ $count > 0 ? max(5, ($count / $maxQuotationStatus) * 100) : 0 }}%; background: #087aa5"></i></div>
                    </div>
                @empty
                    <p class="empty">No sales quotations.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Invoice Status</h2>
                <span>Billing</span>
            </div>
            <div class="progress-list">
                @forelse($invoiceStatusCounts as $status => $count)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $status }}: {{ $count }}">
                        <div class="progress-meta"><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                        <div class="progress-track"><i style="width: {{ $count > 0 ? max(5, ($count / $maxInvoiceStatus) * 100) : 0 }}%; background: #0f766e"></i></div>
                    </div>
                @empty
                    <p class="empty">No invoices.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Approval Backlog</h2>
                <span>Pending actions</span>
            </div>
            <div class="progress-list">
                @foreach($approvalCounts as $label => $count)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $label }}: {{ $count }}">
                        <div class="progress-meta"><span>{{ $label }}</span><strong>{{ $count }}</strong></div>
                        <div class="progress-track"><i style="width: {{ $count > 0 ? max(5, ($count / $approvalTotal) * 100) : 0 }}%; background: #c27803"></i></div>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2 2xl:grid-cols-4">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Operations Mix</h2>
                <span>Workflow volume</span>
            </div>
            <div class="compact-kpi-list">
                @foreach($operationCounts as $label => $count)
                    <div class="compact-kpi stat-tooltip" tabindex="0" data-tooltip="{{ $label }}: {{ $count }}">
                        <span>{{ $label }}</span>
                        <strong>{{ $count }}</strong>
                        <div class="kpi-track"><i style="width: {{ $count > 0 ? max(5, ($count / $operationTotal) * 100) : 0 }}%"></i></div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Task Status</h2>
                <span>Delivery</span>
            </div>
            <div class="progress-list">
                @forelse($taskStatusCounts as $status => $count)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $status }}: {{ $count }}">
                        <div class="progress-meta"><span>{{ $status }}</span><strong>{{ $count }}</strong></div>
                        <div class="progress-track"><i style="width: {{ $count > 0 ? max(5, ($count / $maxTaskStatus) * 100) : 0 }}%; background: #087aa5"></i></div>
                    </div>
                @empty
                    <p class="empty">No tasks.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Open Task Workload</h2>
                <span>By department</span>
            </div>
            <div class="progress-list">
                @forelse($departmentTaskWorkload as $row)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $row['department'] }}: {{ $row['count'] }} open">
                        <div class="progress-meta"><span>{{ $row['department'] }}</span><strong>{{ $row['count'] }}</strong></div>
                        <div class="progress-track"><i style="width: {{ $row['count'] > 0 ? max(5, ($row['count'] / $maxDepartmentTaskWorkload) * 100) : 0 }}%; background: #c27803"></i></div>
                    </div>
                @empty
                    <p class="empty">No open task workload.</p>
                @endforelse
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Attendance Hours</h2>
                <span>By department</span>
            </div>
            <div class="progress-list">
                @forelse($departmentAttendance as $row)
                    <div class="progress-row stat-tooltip" tabindex="0" data-tooltip="{{ $row['department'] }}: {{ $row['hours'] }}h">
                        <div class="progress-meta"><span>{{ $row['department'] }}</span><strong>{{ $row['hours'] }}h</strong></div>
                        <div class="progress-track"><i style="width: {{ $row['hours'] > 0 ? max(5, ($row['hours'] / $maxDepartmentAttendance) * 100) : 0 }}%; background: #0f766e"></i></div>
                    </div>
                @empty
                    <p class="empty">No attendance records.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2 2xl:grid-cols-4">
        <div class="metric-card"><span>Payments Received</span><strong>E{{ number_format($paymentsTotal, 2) }}</strong><div class="metric-foot">Recorded payments</div></div>
        <div class="metric-card"><span>Expenses</span><strong>E{{ number_format($expenseTotal, 2) }}</strong><div class="metric-foot">Recorded expenses</div></div>
        <div class="metric-card"><span>Suppliers</span><strong>{{ $supplierCount }}</strong><div class="metric-foot">E{{ number_format($purchaseTotal, 2) }} purchases</div></div>
        <div class="metric-card"><span>Documents</span><strong>{{ $documentCount }}</strong><div class="metric-foot">{{ $openFollowUps }} open follow-ups, {{ $overdueFollowUps }} overdue</div></div>
    </div>
@endsection
