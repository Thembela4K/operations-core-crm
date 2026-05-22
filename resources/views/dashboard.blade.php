@extends('layouts.app')

@section('content')
    @php
        $totalRecords = max(1, $tenderCount + $quotationCount);
        $actualTotalRecords = $tenderCount + $quotationCount;
        $openRecords = $openTenders + $openQuotations;
        $openPercent = (int) round(($openRecords / $totalRecords) * 100);
        $tenderMixPercent = $actualTotalRecords ? round(($tenderCount / $actualTotalRecords) * 100, 1) : 0;
        $quotationMixPercent = $actualTotalRecords ? round(($quotationCount / $actualTotalRecords) * 100, 1) : 0;
        $tenderMixDegrees = $actualTotalRecords ? round(($tenderCount / $actualTotalRecords) * 360, 2) : 0;
        $quotationMixDegrees = $actualTotalRecords ? round(($quotationCount / $actualTotalRecords) * 360, 2) : 0;
        $quotationMixEnd = $tenderMixDegrees + $quotationMixDegrees;
        $portfolioGradient = $actualTotalRecords
            ? "conic-gradient(#087aa5 0deg {$tenderMixDegrees}deg, #0f766e {$tenderMixDegrees}deg {$quotationMixEnd}deg)"
            : 'conic-gradient(#e5e7eb 0deg 360deg)';
        $statusColors = ['#087aa5', '#0f766e', '#525252', '#b45309', '#be123c', '#334155'];
        $deadlineColors = ['Overdue' => 'bg-rose-700', 'Today' => 'bg-amber-600', 'Next 5 Days' => 'bg-[#087aa5]'];
        $deadlineMax = max(1, $deadlineBands->max() ?? 0);
        $assignmentTotal = max(1, $assignmentStatusCounts->sum());
    @endphp

    <div class="dashboard-header">
        <div>
            <h1 class="page-title">Operations Dashboard</h1>
            <p class="page-subtitle">Tender proposals, quotation requests, assignments, and deadline pressure.</p>
        </div>
        @if($sppraUrl)
            <a class="btn-primary" href="{{ $sppraUrl }}" target="_blank" rel="noopener noreferrer">Open SPPRA Tender Site</a>
        @endif
    </div>

    <div class="dashboard-metrics">
        <div class="metric-card stat-tooltip" data-tooltip="{{ $tenderCount }} tender proposals, {{ $openTenders }} open" tabindex="0">
            <span>Tender Proposals</span>
            <strong>{{ $tenderCount }}</strong>
            <div class="metric-line"><i style="width: {{ $totalRecords ? ($tenderCount / $totalRecords) * 100 : 0 }}%"></i></div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $quotationCount }} quotations, {{ $openQuotations }} open" tabindex="0">
            <span>Quotations</span>
            <strong>{{ $quotationCount }}</strong>
            <div class="metric-line metric-line-teal"><i style="width: {{ $totalRecords ? ($quotationCount / $totalRecords) * 100 : 0 }}%"></i></div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $openRecords }} open records, {{ $openPercent }}% of the register" tabindex="0">
            <span>Open Work</span>
            <strong>{{ $openRecords }}</strong>
            <div class="metric-foot">{{ $openPercent }}% of active register</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $unreadAssignments }} unread assignments, {{ $failedAssignments }} email failures" tabindex="0">
            <span>Unread Assignments</span>
            <strong>{{ $unreadAssignments }}</strong>
            <div class="metric-foot">{{ $failedAssignments }} email failures</div>
        </div>
        <div class="metric-card stat-tooltip" data-tooltip="{{ $submissionCount }} returned department submissions" tabindex="0">
            <span>Submissions</span>
            <strong>{{ $submissionCount }}</strong>
            <div class="metric-foot">Returned work</div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Register Status</h2>
                <span>{{ $tenderCount + $quotationCount }} total records</span>
            </div>
            <div class="mt-5 grid gap-6 lg:grid-cols-2">
                <div>
                    <div class="chart-label">Tender Proposals</div>
                    <div class="bar-stack">
                        @forelse($tenderStatusCounts as $status => $count)
                            @php($percent = $tenderCount ? max(4, ($count / $tenderCount) * 100) : 0)
                            <div class="bar-row stat-tooltip" data-tooltip="{{ $status }}: {{ $count }} tender proposals, {{ round(($count / max(1, $tenderCount)) * 100) }}%" tabindex="0">
                                <div class="bar-row-meta">
                                    <span>{{ $status }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                                <div class="bar-track">
                                    <i style="width: {{ $percent }}%; background: {{ $statusColors[$loop->index % count($statusColors)] }}"></i>
                                </div>
                            </div>
                        @empty
                            <p class="empty">No tender proposals yet.</p>
                        @endforelse
                    </div>
                </div>

                <div>
                    <div class="chart-label">Quotations</div>
                    <div class="bar-stack">
                        @forelse($quotationStatusCounts as $status => $count)
                            @php($percent = $quotationCount ? max(4, ($count / $quotationCount) * 100) : 0)
                            <div class="bar-row stat-tooltip" data-tooltip="{{ $status }}: {{ $count }} quotations, {{ round(($count / max(1, $quotationCount)) * 100) }}%" tabindex="0">
                                <div class="bar-row-meta">
                                    <span>{{ $status }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                                <div class="bar-track">
                                    <i style="width: {{ $percent }}%; background: {{ $statusColors[$loop->index % count($statusColors)] }}"></i>
                                </div>
                            </div>
                        @empty
                            <p class="empty">No quotations yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>

        <section class="panel chart-panel">
            <div class="panel-heading">
                <h2 class="section-title">Portfolio Mix</h2>
                <span>{{ $actualTotalRecords }} records</span>
            </div>
            <div class="donut-wrap">
                <div class="donut-stat-target stat-tooltip" data-tooltip="Tenders: {{ $tenderCount }} ({{ $tenderMixPercent }}%) | Quotations: {{ $quotationCount }} ({{ $quotationMixPercent }}%)" tabindex="0">
                    <div class="donut-chart" style="background: {{ $portfolioGradient }}">
                        <span>{{ $actualTotalRecords }}</span>
                    </div>
                </div>
                <div class="donut-legend">
                    <div><i class="bg-[#087aa5]"></i>Tender Proposals <strong>{{ $tenderCount }} | {{ $tenderMixPercent }}%</strong></div>
                    <div><i class="bg-teal-700"></i>Quotations <strong>{{ $quotationCount }} | {{ $quotationMixPercent }}%</strong></div>
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
                <span>Top {{ $departmentWorkload->count() }}</span>
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
