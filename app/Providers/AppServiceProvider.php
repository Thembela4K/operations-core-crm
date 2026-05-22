<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\Quotation;
use App\Models\TenderProposal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            if (! Auth::check()) {
                return;
            }

            $user = Auth::user();
            $baseAssignments = Assignment::query()
                ->whereIn('workflow_status', ['Assigned', 'In Progress']);

            if ($user->canViewPortfolio()) {
                $baseAssignments->whereRaw('1 = 0');
            } else {
                $baseAssignments->where('department_id', $user->department_id);
            }

            $unreadAssignments = (clone $baseAssignments)->whereNull('read_at');
            $unreadTenderAssignments = (clone $unreadAssignments)
                ->where('assignable_type', TenderProposal::class);
            $unreadQuotationAssignments = (clone $unreadAssignments)
                ->where('assignable_type', Quotation::class);
            $nextUnreadTenderAssignment = (clone $unreadTenderAssignments)
                ->orderByRaw('due_date is null, due_date asc, assigned_at asc')
                ->first();
            $nextUnreadQuotationAssignment = (clone $unreadQuotationAssignments)
                ->orderByRaw('due_date is null, due_date asc, assigned_at asc')
                ->first();

            $view->with([
                'layoutUnreadAssignments' => (clone $unreadAssignments)->count(),
                'layoutUnreadTenderAssignments' => (clone $unreadTenderAssignments)->count(),
                'layoutUnreadQuotationAssignments' => (clone $unreadQuotationAssignments)->count(),
                'layoutNextUnreadTenderUrl' => $nextUnreadTenderAssignment
                    ? route('tender-proposals.show', $nextUnreadTenderAssignment->assignable_id)
                    : route('tender-proposals.index'),
                'layoutNextUnreadQuotationUrl' => $nextUnreadQuotationAssignment
                    ? route('quotations.show', $nextUnreadQuotationAssignment->assignable_id)
                    : route('quotations.index'),
                'layoutDueSoonAssignments' => (clone $baseAssignments)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', now()->addDays(5)->toDateString())
                    ->count(),
            ]);
        });
    }
}
