@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Portfolio status, assignment health, and deadline pressure.</p>
        </div>
    </div>

    <div class="mt-6 grid gap-4 md:grid-cols-3 lg:grid-cols-6">
        <div class="metric"><span>Projects</span><strong>{{ $projectCount }}</strong></div>
        <div class="metric"><span>Quotations</span><strong>{{ $quotationCount }}</strong></div>
        <div class="metric"><span>Open Projects</span><strong>{{ $openProjects }}</strong></div>
        <div class="metric"><span>Open Quotes</span><strong>{{ $openQuotations }}</strong></div>
        <div class="metric"><span>Project Score</span><strong>{{ $averageProjectScore }}</strong></div>
        <div class="metric"><span>Quote Score</span><strong>{{ $averageQuotationScore }}</strong></div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="panel">
            <h2 class="section-title">Project Status</h2>
            <div class="mt-4 space-y-2">
                @forelse($projectStatusCounts as $status => $count)
                    <div class="flex items-center justify-between border-b border-zinc-100 py-2 text-sm">
                        <span>{{ $status }}</span><strong>{{ $count }}</strong>
                    </div>
                @empty
                    <p class="empty">No projects yet.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">Quotation Status</h2>
            <div class="mt-4 space-y-2">
                @forelse($quotationStatusCounts as $status => $count)
                    <div class="flex items-center justify-between border-b border-zinc-100 py-2 text-sm">
                        <span>{{ $status }}</span><strong>{{ $count }}</strong>
                    </div>
                @empty
                    <p class="empty">No quotations yet.</p>
                @endforelse
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="panel">
            <h2 class="section-title">High-Risk Projects</h2>
            <div class="mt-4 space-y-3">
                @forelse($highRiskProjects as $project)
                    <a class="block rounded-md border border-zinc-200 p-3 hover:border-zinc-400" href="{{ route('projects.show', $project) }}">
                        <div class="font-medium">{{ $project->project_code }} - {{ $project->name }}</div>
                        <div class="text-xs text-zinc-500">Deadline {{ $project->deadline->toDateString() }}</div>
                    </a>
                @empty
                    <p class="empty">No high-risk projects.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">High-Risk Quotations</h2>
            <div class="mt-4 space-y-3">
                @forelse($highRiskQuotations as $quotation)
                    <a class="block rounded-md border border-zinc-200 p-3 hover:border-zinc-400" href="{{ route('quotations.show', $quotation) }}">
                        <div class="font-medium">{{ $quotation->quotation_code }} - {{ $quotation->opportunity }}</div>
                        <div class="text-xs text-zinc-500">Valid until {{ $quotation->valid_until->toDateString() }}</div>
                    </a>
                @empty
                    <p class="empty">No high-risk quotations.</p>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">Upcoming and Overdue</h2>
            <div class="mt-4 space-y-3">
                @forelse($upcomingItems as $item)
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="text-xs font-semibold uppercase text-zinc-500">{{ $item['type'] }} - {{ $item['reference'] }}</div>
                        <div class="font-medium">{{ $item['title'] }}</div>
                        <div class="text-xs text-zinc-500">{{ $item['due_label'] }} {{ $item['due_on']->toDateString() }} ({{ $item['days_left'] }} days)</div>
                    </div>
                @empty
                    <p class="empty">No urgent deadlines.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
