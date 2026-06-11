<?php

namespace App\Http\Controllers;

use App\Models\Requisition;
use App\Models\SalesQuotation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('approvals.index', [
            'salesQuotations' => SalesQuotation::query()
                ->with(['client', 'department', 'creator'])
                ->where('status', SalesQuotation::STATUS_SUBMITTED)
                ->latest('submitted_at')
                ->paginate(8, ['*'], 'sales_page'),
            'requisitions' => Requisition::query()
                ->with(['department', 'requester'])
                ->whereIn('status', [Requisition::STATUS_SUBMITTED, Requisition::STATUS_IN_REVIEW])
                ->latest('submitted_at')
                ->paginate(8, ['*'], 'req_page'),
            'fundsRelease' => Requisition::query()
                ->with(['department', 'requester', 'approver'])
                ->where('status', Requisition::STATUS_APPROVED)
                ->latest('approved_at')
                ->paginate(8, ['*'], 'funds_page'),
            'canApprove' => $user->canApproveFinance() || $user->canApproveRequisitions(),
            'canReleaseFunds' => $user->canReleaseRequisitionFunds(),
        ]);
    }
}
