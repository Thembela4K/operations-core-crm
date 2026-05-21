<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Project;
use App\Models\Quotation;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function exportProjects(Request $request): StreamedResponse
    {
        $headers = [
            'Project ID',
            'Project Name',
            'Owner',
            'Owner Email',
            'Assigned Department',
            'Assigned To',
            'Assignee Email',
            'Assignment Status',
            'Assignment Date',
            'Status',
            'Priority',
            'Rating',
            'Risk',
            'Progress %',
            'Budget',
            'Start Date',
            'Deadline',
            'Notes',
        ];

        $projects = Project::query()
            ->visibleTo($request->user())
            ->with('latestAssignment.department')
            ->orderBy('project_code')
            ->get();

        return response()->streamDownload(function () use ($headers, $projects): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($projects as $project) {
                $assignment = $project->latestAssignment;
                fputcsv($out, [
                    $project->project_code,
                    $project->name,
                    $project->owner,
                    $project->owner_email,
                    $assignment?->department?->name,
                    $assignment?->assignee_name,
                    $assignment?->assignee_email,
                    $assignment?->status ?? 'Unassigned',
                    $assignment?->assigned_at?->toDateString(),
                    $project->status,
                    $project->priority,
                    $project->rating,
                    $project->risk,
                    $project->progress_percent,
                    $project->budget,
                    $project->start_date->toDateString(),
                    $project->deadline->toDateString(),
                    $project->notes,
                ]);
            }
            fclose($out);
        }, 'projects.csv');
    }

    public function importProjects(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt']]);
        $count = 0;

        foreach ($this->readCsv($request->file('csv')->getRealPath()) as $row) {
            $project = Project::query()->updateOrCreate(
                ['project_code' => $this->value($row, ['Project ID', 'project_code']) ?: $this->nextCode(Project::class, 'project_code', 'PRJ')],
                [
                    'name' => $this->value($row, ['Project Name', 'name']) ?: 'Untitled Project',
                    'owner' => $this->value($row, ['Owner', 'owner']) ?: 'Unassigned',
                    'owner_email' => $this->value($row, ['Owner Email', 'owner_email']),
                    'status' => $this->value($row, ['Status', 'status']) ?: 'Not Started',
                    'priority' => $this->value($row, ['Priority', 'priority']) ?: 'Medium',
                    'rating' => (float) ($this->value($row, ['Rating', 'rating']) ?: 0),
                    'risk' => $this->value($row, ['Risk', 'risk']) ?: 'Medium',
                    'progress_percent' => (int) ($this->value($row, ['Progress %', 'progress_percent']) ?: 0),
                    'budget' => (float) ($this->value($row, ['Budget', 'budget']) ?: 0),
                    'start_date' => $this->dateValue($this->value($row, ['Start Date', 'start_date'])),
                    'deadline' => $this->dateValue($this->value($row, ['Deadline', 'deadline']), now()->addMonth()),
                    'notes' => $this->value($row, ['Notes', 'notes']),
                    'created_by' => $request->user()->id,
                ],
            );

            $this->importAssignment($project, $row, $request->user()->id);
            $count++;
        }

        return back()->with('success', "{$count} projects imported.");
    }

    public function exportQuotations(Request $request): StreamedResponse
    {
        $headers = [
            'Quotation ID',
            'Client',
            'Opportunity',
            'Owner',
            'Owner Email',
            'Assigned Department',
            'Assigned To',
            'Assignee Email',
            'Assignment Status',
            'Assignment Date',
            'Status',
            'Priority',
            'Rating',
            'Risk',
            'Win Probability %',
            'Quoted Amount',
            'Expected Cost',
            'Issue Date',
            'Valid Until',
            'Notes',
        ];

        $quotations = Quotation::query()
            ->visibleTo($request->user())
            ->with('latestAssignment.department')
            ->orderBy('quotation_code')
            ->get();

        return response()->streamDownload(function () use ($headers, $quotations): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($quotations as $quotation) {
                $assignment = $quotation->latestAssignment;
                fputcsv($out, [
                    $quotation->quotation_code,
                    $quotation->client,
                    $quotation->opportunity,
                    $quotation->owner,
                    $quotation->owner_email,
                    $assignment?->department?->name,
                    $assignment?->assignee_name,
                    $assignment?->assignee_email,
                    $assignment?->status ?? 'Unassigned',
                    $assignment?->assigned_at?->toDateString(),
                    $quotation->status,
                    $quotation->priority,
                    $quotation->rating,
                    $quotation->risk,
                    $quotation->win_probability_percent,
                    $quotation->quoted_amount,
                    $quotation->expected_cost,
                    $quotation->issue_date->toDateString(),
                    $quotation->valid_until->toDateString(),
                    $quotation->notes,
                ]);
            }
            fclose($out);
        }, 'quotations.csv');
    }

    public function importQuotations(Request $request): RedirectResponse
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt']]);
        $count = 0;

        foreach ($this->readCsv($request->file('csv')->getRealPath()) as $row) {
            $quotation = Quotation::query()->updateOrCreate(
                ['quotation_code' => $this->value($row, ['Quotation ID', 'quotation_code']) ?: $this->nextCode(Quotation::class, 'quotation_code', 'QTN')],
                [
                    'client' => $this->value($row, ['Client', 'client']) ?: 'Unknown Client',
                    'opportunity' => $this->value($row, ['Opportunity', 'opportunity']) ?: 'Untitled Opportunity',
                    'owner' => $this->value($row, ['Owner', 'owner']) ?: 'Unassigned',
                    'owner_email' => $this->value($row, ['Owner Email', 'owner_email']),
                    'status' => $this->value($row, ['Status', 'status']) ?: 'Draft',
                    'priority' => $this->value($row, ['Priority', 'priority']) ?: 'Medium',
                    'rating' => (float) ($this->value($row, ['Rating', 'rating']) ?: 0),
                    'risk' => $this->value($row, ['Risk', 'risk']) ?: 'Medium',
                    'win_probability_percent' => (int) ($this->value($row, ['Win Probability %', 'win_probability_percent']) ?: 0),
                    'quoted_amount' => (float) ($this->value($row, ['Quoted Amount', 'quoted_amount']) ?: 0),
                    'expected_cost' => (float) ($this->value($row, ['Expected Cost', 'expected_cost']) ?: 0),
                    'issue_date' => $this->dateValue($this->value($row, ['Issue Date', 'issue_date'])),
                    'valid_until' => $this->dateValue($this->value($row, ['Valid Until', 'valid_until']), now()->addMonth()),
                    'notes' => $this->value($row, ['Notes', 'notes']),
                    'created_by' => $request->user()->id,
                ],
            );

            $this->importAssignment($quotation, $row, $request->user()->id);
            $count++;
        }

        return back()->with('success', "{$count} quotations imported.");
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle) ?: [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $rows[] = array_combine($headers, array_pad($line, count($headers), ''));
        }

        fclose($handle);

        return $rows;
    }

    private function value(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }

        return null;
    }

    private function dateValue(?string $value, ?Carbon $fallback = null): string
    {
        if (! $value) {
            return ($fallback ?? now())->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }

    private function importAssignment(Project|Quotation $record, array $row, int $createdBy): void
    {
        $departmentName = $this->value($row, ['Assigned Department', 'assigned_department']);
        $assigneeName = $this->value($row, ['Assigned To', 'assigned_to']);
        $assigneeEmail = $this->value($row, ['Assignee Email', 'assignee_email']);

        if (! $departmentName || ! $assigneeName || ! $assigneeEmail) {
            return;
        }

        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug($departmentName)],
            ['name' => $departmentName, 'is_active' => true],
        );

        $record->assignments()->create([
            'department_id' => $department->id,
            'assignee_name' => $assigneeName,
            'assignee_email' => $assigneeEmail,
            'status' => $this->value($row, ['Assignment Status', 'assignment_status']) ?: 'Assigned',
            'assigned_at' => $this->dateValue($this->value($row, ['Assignment Date', 'assignment_date'])),
            'created_by' => $createdBy,
        ]);
    }

    private function nextCode(string $model, string $column, string $prefix): string
    {
        $lastCode = $model::query()->where($column, 'like', "{$prefix}-%")->orderByDesc('id')->value($column);
        $number = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        return sprintf('%s-%03d', $prefix, $number);
    }
}
