<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\ScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request, ScoringService $scoring): View
    {
        $quotations = Quotation::query()
            ->visibleTo($request->user())
            ->with(['latestAssignment.department'])
            ->withCount('documents')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('quotation_code', 'like', "%{$search}%")
                        ->orWhere('client', 'like', "%{$search}%")
                        ->orWhere('opportunity', 'like', "%{$search}%")
                        ->orWhere('owner', 'like', "%{$search}%")
                        ->orWhere('owner_email', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('risk'), fn ($query) => $query->where('risk', $request->string('risk')))
            ->latest('valid_until')
            ->paginate(12)
            ->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'scoring' => $scoring,
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
            'risks' => Quotation::RISKS,
        ]);
    }

    public function create(): View
    {
        return view('quotations.create', [
            'quotation' => new Quotation([
                'quotation_code' => $this->nextQuotationCode(),
                'status' => 'Draft',
                'priority' => 'Medium',
                'rating' => 0,
                'risk' => 'Medium',
                'win_probability_percent' => 0,
                'quoted_amount' => 0,
                'expected_cost' => 0,
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addMonth()->toDateString(),
            ]),
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
            'risks' => Quotation::RISKS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateQuotation($request);
        $data['created_by'] = $request->user()->id;

        $quotation = Quotation::query()->create($data);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created.');
    }

    public function show(Request $request, Quotation $quotation, ScoringService $scoring): View
    {
        $this->authorizeQuotationAccess($request, $quotation);

        return view('quotations.show', [
            'quotation' => $quotation->load(['latestAssignment.department', 'assignments.department', 'documents.uploader', 'emailLogs']),
            'score' => $scoring->quotationScore($quotation),
        ]);
    }

    public function edit(Request $request, Quotation $quotation): View
    {
        $this->authorizeQuotationAccess($request, $quotation);

        return view('quotations.edit', [
            'quotation' => $quotation,
            'statuses' => Quotation::STATUSES,
            'priorities' => Quotation::PRIORITIES,
            'risks' => Quotation::RISKS,
        ]);
    }

    public function update(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorizeQuotationAccess($request, $quotation);

        $data = $request->user()->canManage()
            ? $this->validateQuotation($request, $quotation)
            : $request->validate([
                'status' => ['required', Rule::in(Quotation::STATUSES)],
                'win_probability_percent' => ['required', 'integer', 'min:0', 'max:100'],
                'notes' => ['nullable', 'string'],
            ]);

        $quotation->update($data);

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated.');
    }

    public function destroy(Request $request, Quotation $quotation): RedirectResponse
    {
        if (! $request->user()->canManage()) {
            abort(403);
        }

        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation deleted.');
    }

    private function validateQuotation(Request $request, ?Quotation $quotation = null): array
    {
        return $request->validate([
            'quotation_code' => ['required', 'string', 'max:30', Rule::unique('quotations', 'quotation_code')->ignore($quotation)],
            'client' => ['required', 'string', 'max:255'],
            'opportunity' => ['required', 'string', 'max:255'],
            'owner' => ['required', 'string', 'max:255'],
            'owner_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(Quotation::STATUSES)],
            'priority' => ['required', Rule::in(Quotation::PRIORITIES)],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'risk' => ['required', Rule::in(Quotation::RISKS)],
            'win_probability_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'quoted_amount' => ['required', 'numeric', 'min:0'],
            'expected_cost' => ['required', 'numeric', 'min:0'],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function authorizeQuotationAccess(Request $request, Quotation $quotation): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        if (! $quotation->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }

    private function nextQuotationCode(): string
    {
        $lastCode = Quotation::query()
            ->where('quotation_code', 'like', 'QTN-%')
            ->orderByDesc('id')
            ->value('quotation_code');

        $number = $lastCode ? ((int) substr($lastCode, 4)) + 1 : 1;

        return sprintf('QTN-%03d', $number);
    }
}
