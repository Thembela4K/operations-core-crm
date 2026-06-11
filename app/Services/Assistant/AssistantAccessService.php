<?php

namespace App\Services\Assistant;

use App\Models\CrmTask;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AssistantAccessService
{
    public function canAccessDocument(User $user, Document $document): bool
    {
        $record = $document->documentable;

        return $record instanceof Model && $this->canAccessRecord($user, $record);
    }

    public function canAccessRecord(User $user, Model $record): bool
    {
        if ($record instanceof CrmTask) {
            return $user->canViewReports()
                || $record->department_id === $user->department_id
                || $record->assigned_to === $user->id
                || $record->created_by === $user->id;
        }

        if ($record instanceof SalesQuotation || $record instanceof Invoice) {
            return $user->canViewReports() || $record->department_id === $user->department_id;
        }

        if ($record instanceof Payment) {
            return $user->canViewReports() || $user->canManageFinance();
        }

        if ($record instanceof Requisition) {
            return $user->canViewRequisitions() || $record->department_id === $user->department_id;
        }

        if ($record instanceof Expense) {
            return $user->canViewReports() || $user->canManageFinance();
        }

        if ($record instanceof Submission) {
            return $user->canReviewSubmissions()
                || $user->canManage()
                || $record->department_id === $user->department_id;
        }

        if ($record instanceof TenderProposal || $record instanceof Quotation) {
            return $user->canViewPortfolio()
                || $record->assignments()->where('department_id', $user->department_id)->exists();
        }

        return true;
    }
}
