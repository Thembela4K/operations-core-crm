<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\CrmTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates assignments and moves them through their workflow. Every assignment
 * also becomes a Week Plan task, so assigned work shows up where the work is
 * planned.
 */
class AssignmentService
{
    /**
     * Transitions the workflow understands.
     */
    public const TRANSITIONS = [
        'accept' => 'Accepted',
        'start' => 'In Progress',
        'draft' => 'Draft Submitted',
        'finish' => 'Finished Submitted',
        'complete' => 'Completed',
        'cancel' => 'Cancelled',
    ];

    public function __construct(private readonly FinanceNumberService $numbers)
    {
    }

    /**
     * Persist a new assignment and its Week Plan task.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes, User $actor): Assignment
    {
        return DB::transaction(function () use ($attributes, $actor): Assignment {
            $assignment = Assignment::query()->create($attributes + [
                'status' => 'Assigned',
                'workflow_status' => 'Assigned',
                'assigned_at' => now(),
                'created_by' => $actor->id,
            ]);

            $this->syncTask($assignment, $actor);
            $this->log($assignment, 'assigned', null, 'Assigned', $actor);

            return $assignment;
        });
    }

    /**
     * Move an assignment to the next workflow state.
     */
    public function transition(Assignment $assignment, string $action, User $actor, ?string $notes = null): Assignment
    {
        $target = self::TRANSITIONS[$action] ?? null;

        if ($target === null) {
            throw new \InvalidArgumentException("Unknown assignment action [{$action}].");
        }

        $from = $assignment->workflow_status;

        DB::transaction(function () use ($assignment, $target, $actor, $notes, $from, $action): void {
            $attributes = ['workflow_status' => $target];

            if ($target === 'Accepted' && $assignment->accepted_at === null) {
                $attributes['accepted_at'] = now();
            }

            if ($target === 'Completed') {
                $attributes['completed_at'] = now();
                $attributes['read_at'] = $assignment->read_at ?? now();
            }

            $assignment->update($attributes);
            $assignment->refresh();

            $this->syncTask($assignment, $actor);
            $this->log($assignment, $action, $from, $target, $actor, $notes);
        });

        return $assignment->refresh();
    }

    /**
     * Keep the Week Plan task in step with the assignment.
     */
    public function syncTask(Assignment $assignment, ?User $actor = null): CrmTask
    {
        $task = CrmTask::query()
            ->where('taskable_type', Assignment::class)
            ->where('taskable_id', $assignment->id)
            ->first();

        $attributes = [
            'department_id' => $assignment->department_id,
            'assigned_to' => $assignment->assigned_user_id,
            'created_by' => $actor?->id ?? $assignment->created_by,
            'title' => $assignment->label(),
            'description' => $assignment->instructions,
            'due_date' => $assignment->due_date?->toDateString(),
            'priority' => 'Medium',
        ];

        if ($task === null) {
            $attributes['taskable_type'] = Assignment::class;
            $attributes['taskable_id'] = $assignment->id;
            $attributes['task_number'] = $this->numbers->taskNumber();
            $attributes['status'] = CrmTask::STATUS_TO_DO;

            return CrmTask::query()->create($attributes);
        }

        $attributes['status'] = match ($assignment->workflow_status) {
            'In Progress', 'Draft Submitted', 'Finished Submitted' => CrmTask::STATUS_IN_PROGRESS,
            'Completed' => CrmTask::STATUS_DONE,
            'Cancelled' => CrmTask::STATUS_CANCELLED,
            default => $task->status,
        };

        $attributes['completed_at'] = $assignment->workflow_status === 'Completed' ? now() : null;

        $task->update($attributes);

        return $task;
    }

    /**
     * Record one step in the assignment's history.
     */
    public function log(Assignment $assignment, string $action, ?string $from, ?string $to, ?User $actor, ?string $notes = null): void
    {
        $assignment->events()->create([
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Hand the assignment to someone else, keeping the trail.
     */
    public function reassign(Assignment $assignment, array $attributes, User $actor, ?string $notes = null): Assignment
    {
        $from = $assignment->assignee_name;

        DB::transaction(function () use ($assignment, $attributes, $actor, $notes, $from): void {
            $assignment->update($attributes + ['workflow_status' => 'Assigned', 'accepted_at' => null]);
            $assignment->refresh();

            $this->syncTask($assignment, $actor);
            $this->log($assignment, 'reassigned', 'Assigned', 'Assigned', $actor, $notes ?? "Reassigned from {$from}.");
        });

        return $assignment->refresh();
    }
}
