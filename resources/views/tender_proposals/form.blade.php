@php($existingDates = $tenderProposal->relationLoaded('importantDates') ? $tenderProposal->importantDates : collect())
@php($existingDateRows = $existingDates->mapWithKeys(fn ($date) => [$date->label => ['label' => $date->label, 'due_date' => $date->due_date->format('Y-m-d'), 'notes' => $date->notes]]))
@php($importantDateRows = collect(old('important_dates'))->filter(fn ($row) => filled($row['label'] ?? null))->mapWithKeys(fn ($row) => [$row['label'] => $row]))
@php($importantDateRows = $importantDateRows->isNotEmpty() ? $importantDateRows : $existingDateRows)

<div class="grid gap-4 lg:grid-cols-3">
    <label>
        <span class="label">Tender Reference</span>
        <input class="input" name="tender_reference" value="{{ old('tender_reference', $tenderProposal->tender_reference) }}" required>
    </label>
    <label class="lg:col-span-2">
        <span class="label">Tender Title</span>
        <input class="input" name="title" value="{{ old('title', $tenderProposal->title) }}" required>
    </label>
    <label>
        <span class="label">Procurement Type</span>
        <select class="input" name="procurement_type">
            <option value="">Not classified</option>
            @foreach(\App\Models\TenderProposal::PROCUREMENT_TYPES as $type)
                <option value="{{ $type }}" @selected(old('procurement_type', $tenderProposal->procurement_type) === $type)>{{ $type }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-neutral-500">The actual notice type — a tender and a request for quotation are different.</span>
    </label>
    <label>
        <span class="label">Notice Source</span>
        <select class="input" name="notice_source">
            <option value="">Not recorded</option>
            @foreach(\App\Models\TenderProposal::NOTICE_SOURCES as $source)
                <option value="{{ $source }}" @selected(old('notice_source', $tenderProposal->notice_source) === $source)>{{ $source }}</option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-neutral-500">Where it was advertised, e.g. ESPPRA.</span>
    </label>
    <label>
        <span class="label">Tender Due Date</span>
        <input class="input" type="date" name="closing_date" value="{{ old('closing_date', $tenderProposal->closing_date ? \Illuminate\Support\Carbon::parse($tenderProposal->closing_date)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}" required>
    </label>
    <label class="lg:col-span-2">
        <span class="label">Tender Document</span>
        <input class="input" type="file" name="tender_document" @required(! $tenderProposal->exists)>
    </label>

    <label>
        <span class="label">Received Date</span>
        <input class="input" type="date" name="received_date" value="{{ old('received_date', $tenderProposal->received_date ? \Illuminate\Support\Carbon::parse($tenderProposal->received_date)->format('Y-m-d') : now()->format('Y-m-d')) }}">
    </label>
    <label>
        <span class="label">Status</span>
        <select class="input" name="status">
            @foreach(\App\Models\TenderProposal::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $tenderProposal->status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Priority</span>
        <select class="input" name="priority">
            @foreach(\App\Models\TenderProposal::PRIORITIES as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $tenderProposal->priority) === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="label">Risk</span>
        <select class="input" name="risk">
            @foreach(\App\Models\TenderProposal::RISKS as $risk)
                <option value="{{ $risk }}" @selected(old('risk', $tenderProposal->risk) === $risk)>{{ $risk }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Progress (%)</span>
        <input class="input" type="number" name="progress_percent" min="0" max="100" step="1" value="{{ old('progress_percent', $tenderProposal->progress_percent ?? 0) }}">
    </label>
    <label>
        <span class="label">Budget</span>
        <input class="input" type="number" name="budget" min="0" step="0.01" value="{{ old('budget', $tenderProposal->budget ?? 0) }}">
    </label>

    <label>
        <span class="label">Rating (0-5)</span>
        <input class="input" type="number" name="rating" min="0" max="5" step="0.1" value="{{ old('rating', $tenderProposal->rating ?? 0) }}">
    </label>
    <label>
        <span class="label">Owner</span>
        <input class="input" name="owner" value="{{ old('owner', $tenderProposal->owner) }}">
    </label>
    <label>
        <span class="label">Owner Email</span>
        <input class="input" type="email" name="owner_email" value="{{ old('owner_email', $tenderProposal->owner_email) }}">
    </label>

    <label class="lg:col-span-3">
        <span class="label">Brief Description</span>
        <textarea class="input min-h-32" name="brief">{{ old('brief', $tenderProposal->brief) }}</textarea>
    </label>
</div>

<div class="mt-8">
    <h2 class="section-title">Optional Tender Dates</h2>
    <div class="mt-4 space-y-3">
        @foreach($dateTypes as $i => $dateType)
            @php($row = $importantDateRows->get($dateType, ['label' => $dateType]))
            <div class="grid gap-3 lg:grid-cols-[240px_190px_1fr]">
                <div class="date-label">
                    <span>{{ $dateType }}</span>
                    <small>Optional</small>
                </div>
                <input type="hidden" name="important_dates[{{ $i }}][label]" value="{{ $dateType }}">
                <input class="input" type="date" name="important_dates[{{ $i }}][due_date]" value="{{ $row['due_date'] ?? '' }}">
                <input class="input" name="important_dates[{{ $i }}][notes]" placeholder="Notes" value="{{ $row['notes'] ?? '' }}">
            </div>
        @endforeach
    </div>
</div>
