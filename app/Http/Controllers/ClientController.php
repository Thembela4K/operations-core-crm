<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientContact;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->withCount(['contacts', 'salesQuotations', 'invoices'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('client_code', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(FinanceNumberService $numbers): View
    {
        $this->authorizeManageClients();

        return view('clients.create', [
            'client' => new Client([
                'client_code' => $numbers->clientCode(),
                'country' => 'Eswatini',
                'is_active' => true,
            ]),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers): RedirectResponse
    {
        $this->authorizeManageClients();

        $data = $this->validated($request);
        $data['client_code'] = $data['client_code'] ?: $numbers->clientCode();
        $data['created_by'] = $request->user()->id;
        $data = $this->clientData($data);

        $client = Client::query()->create($data);
        $this->syncPrimaryContact($client, $request);

        return redirect()->route('clients.show', $client)->with('success', 'Client created.');
    }

    public function show(Client $client): View
    {
        return view('clients.show', [
            'client' => $client->load(['contacts', 'activities.responsibleUser', 'salesQuotations.department', 'invoices.payments']),
        ]);
    }

    public function edit(Client $client): View
    {
        $this->authorizeManageClients();

        return view('clients.edit', compact('client'));
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeManageClients();

        $client->update($this->clientData($this->validated($request, $client)));
        $this->syncPrimaryContact($client, $request);

        return redirect()->route('clients.show', $client)->with('success', 'Client updated.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $this->authorizeManageClients();

        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client deleted.');
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        return $request->validate([
            'client_code' => ['nullable', 'string', 'max:30', Rule::unique('clients', 'client_code')->ignore($client)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_position' => ['nullable', 'string', 'max:120'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function syncPrimaryContact(Client $client, Request $request): void
    {
        if (! $request->filled('contact_name')) {
            return;
        }

        ClientContact::query()->updateOrCreate(
            ['client_id' => $client->id, 'is_primary' => true],
            [
                'name' => $request->string('contact_name')->toString(),
                'email' => $request->input('contact_email'),
                'phone' => $request->input('contact_phone'),
                'position' => $request->input('contact_position'),
            ],
        );
    }

    private function clientData(array $data): array
    {
        unset($data['contact_name'], $data['contact_email'], $data['contact_phone'], $data['contact_position']);

        return $data;
    }

    private function authorizeManageClients(): void
    {
        if (! request()->user()->canManageFinance()) {
            abort(403);
        }
    }
}
