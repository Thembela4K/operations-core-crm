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
        <span class="label">Tender Due Date</span>
        <input class="input" type="date" name="closing_date" value="{{ old('closing_date', $tenderProposal->closing_date ? \Illuminate\Support\Carbon::parse($tenderProposal->closing_date)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}" required>
    </label>
    <label class="lg:col-span-2">
        <span class="label">Tender Document</span>
        <input class="input" type="file" name="tender_document" @required(! $tenderProposal->exists)>
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
