<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientActivityController extends Controller
{
    public function index(Request $request): View
    {
        $activities = ClientActivity::query()
            ->visibleTo($request->user())
            ->with(['client', 'responsibleUser', 'creator'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('subject', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw('next_follow_up_date is null, next_follow_up_date asc')
            ->latest('activity_date')
            ->paginate(12)
            ->withQueryString();

        return view('client_activities.index', [
            'activities' => $activities,
            'types' => ClientActivity::TYPES,
            'statuses' => ClientActivity::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('client_activities.create', [
            'activity' => new ClientActivity([
                'client_id' => $request->integer('client_id') ?: null,
                'activity_date' => now()->toDateString(),
                'responsible_user_id' => $request->user()->id,
                'status' => 'Open',
                'type' => 'Follow-up',
            ]),
            ...$this->formData(),
        ]);
    }

    public function store(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $activity = ClientActivity::query()->create($data);
        $audit->record('created', $activity, "Client activity created for {$activity->client->name}.");

        return redirect()->route('clients.show', $activity->client)->with('success', 'Client activity saved.');
    }

    public function edit(ClientActivity $clientActivity): View
    {
        return view('client_activities.edit', [
            'activity' => $clientActivity,
            ...$this->formData(),
        ]);
    }

    public function update(Request $request, ClientActivity $clientActivity, AuditLogService $audit): RedirectResponse
    {
        $before = $clientActivity->only(['status', 'next_follow_up_date', 'responsible_user_id']);
        $clientActivity->update($this->validated($request));
        $audit->record('updated', $clientActivity, 'Client activity updated.', $before, $clientActivity->only(['status', 'next_follow_up_date', 'responsible_user_id']));

        return redirect()->route('clients.show', $clientActivity->client)->with('success', 'Client activity updated.');
    }

    public function destroy(ClientActivity $clientActivity, AuditLogService $audit): RedirectResponse
    {
        $client = $clientActivity->client;
        $audit->record('deleted', $clientActivity, 'Client activity deleted.');
        $clientActivity->delete();

        return redirect()->route('clients.show', $client)->with('success', 'Client activity deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', Rule::in(ClientActivity::TYPES)],
            'subject' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'activity_date' => ['nullable', 'date'],
            'next_follow_up_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(ClientActivity::STATUSES)],
        ]);
    }

    private function formData(): array
    {
        return [
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'types' => ClientActivity::TYPES,
            'statuses' => ClientActivity::STATUSES,
        ];
    }
}
