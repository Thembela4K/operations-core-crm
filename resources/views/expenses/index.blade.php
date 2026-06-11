@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Expenses</h1>
            <p class="page-subtitle">Company expenses by category, department, VAT amount, and supplier.</p>
        </div>
        @if(auth()->user()->canManageFinance())
            <a class="btn-primary" href="{{ route('expenses.create') }}">New Expense</a>
        @endif
    </div>

    <form class="panel mt-6 grid gap-3 md:grid-cols-[1fr_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search expense, payee, or category">
        <button class="btn-secondary" type="submit">Search</button>
    </form>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Expense</th><th>Payee</th><th>Supplier</th><th>Department</th><th>Amount</th><th>VAT</th><th>Total</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td><strong>{{ $expense->expense_number }}</strong><br><span class="text-xs text-neutral-500">{{ $expense->category }}</span></td>
                            <td>{{ $expense->payee }}</td>
                            <td>{{ $expense->supplier?->name ?: '-' }}</td>
                            <td>{{ $expense->department?->name ?? 'Company-wide' }}</td>
                            <td>E{{ number_format((float) $expense->amount, 2) }}</td>
                            <td>E{{ number_format((float) $expense->vat_amount, 2) }}</td>
                            <td>E{{ number_format((float) $expense->total_amount, 2) }}</td>
                            <td>{{ $expense->expense_date->toFormattedDateString() }}</td>
                            <td class="text-right">@if(auth()->user()->canManageFinance())<a class="link" href="{{ route('expenses.edit', $expense) }}">Edit</a>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><p class="empty">No expenses yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $expenses->links() }}</div>
    </section>
@endsection
