<?php

namespace App\Services\Assistant;

use App\Models\Invoice;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LocalAssistantResponder
{
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function answer(User $user, string $message, array $crmContext, array $history = []): ?array
    {
        $text = Str::of($message)->lower()->squish()->toString();
        $conversationText = $this->conversationText($history, $text);

        $reply = match (true) {
            $this->asksDateOrTime($text) => $this->dateTimeReply(),
            $this->isGreeting($text) => "I'm here, {$this->firstName($user)}. What would you like to check?",
            $this->asksSupplierCount($text) => $this->countReply('suppliers', 'supplier', $crmContext),
            $this->asksClientCount($text) => $this->countReply('clients', 'client', $crmContext),
            $this->asksTaskCount($text) => $this->countReply('tasks', 'task', $crmContext),
            $this->asksDocumentCount($text) => $this->countReply('documents', 'document', $crmContext),
            $this->asksInvoiceGeneration($conversationText) => $this->invoiceGenerationReply($user),
            $this->asksSalesHealth($conversationText) => $this->salesHealthReply($user),
            default => null,
        };

        if (! $reply) {
            return null;
        }

        return [
            'intent' => 'local_fallback',
            'reply' => $reply,
            'action' => null,
            'filters' => [],
            'assistant_mode' => 'local_fallback',
        ];
    }

    private function asksDateOrTime(string $text): bool
    {
        return (bool) preg_match('/\b(today|date|time|day is it)\b/i', $text);
    }

    private function isGreeting(string $text): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|morning|good morning|good afternoon|good evening)\b/i', $text);
    }

    private function asksSupplierCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'supplier');
    }

    private function asksClientCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'client') || str_contains($text, 'customer'));
    }

    private function asksTaskCount(string $text): bool
    {
        return $this->isCountQuestion($text) && str_contains($text, 'task');
    }

    private function asksDocumentCount(string $text): bool
    {
        return $this->isCountQuestion($text) && (str_contains($text, 'document') || str_contains($text, 'file'));
    }

    private function asksSalesHealth(string $text): bool
    {
        return (bool) preg_match('/\b(sales|quotation|quotations|pipeline|finance|revenue)\b/i', $text)
            && (bool) preg_match('/\b(how are|how is|performance|falling short|doing|gap|weak|short)\b/i', $text);
    }

    private function asksInvoiceGeneration(string $text): bool
    {
        return str_contains($text, 'invoice')
            && (bool) preg_match('/\b(why|not generated|not created|generated|created|converted|conversion|falling short|gap)\b/i', $text);
    }

    private function isCountQuestion(string $text): bool
    {
        return (bool) preg_match('/\b(how many|count|total number|number of)\b/i', $text);
    }

    private function dateTimeReply(): string
    {
        return 'Today is '.now()->format('l, F j, Y').', and the current time is '.now()->format('H:i').'.';
    }

    private function countReply(string $key, string $singular, array $crmContext): string
    {
        $count = (int) Arr::get($crmContext, "counts.{$key}", 0);
        $label = Str::plural($singular, $count);

        return "There ".($count === 1 ? 'is' : 'are')." {$count} {$label} in the CRM.";
    }

    private function salesHealthReply(User $user): string
    {
        $sales = SalesQuotation::query()->visibleTo($user);
        $invoices = Invoice::query()->visibleTo($user);

        $salesCount = (clone $sales)->count();
        $invoiceCount = (clone $invoices)->count();
        $salesTotal = (float) (clone $sales)->sum('total');
        $invoiceTotal = (float) (clone $invoices)->sum('total');
        $unpaidInvoices = (clone $invoices)->where('balance_due', '>', 0)->count();
        $accepted = (clone $sales)->where('status', SalesQuotation::STATUS_ACCEPTED)->count();
        $converted = (clone $sales)->where('status', SalesQuotation::STATUS_CONVERTED)->count();
        $drafts = (clone $sales)->where('status', SalesQuotation::STATUS_DRAFT)->count();

        if ($salesCount === 0) {
            return 'There are no sales quotations recorded yet, so the sales pipeline has not started in the CRM.';
        }

        return "Sales show {$salesCount} {$this->plural('quotation', $salesCount)} worth {$this->money($salesTotal)}, "
            ."but only {$invoiceCount} {$this->plural('invoice', $invoiceCount)} worth {$this->money($invoiceTotal)}. "
            ."The shortfall is conversion: {$drafts} {$this->plural('quotation', $drafts)} are still drafts, "
            ."{$accepted} accepted {$this->plural('quotation', $accepted)} need follow-through, and {$converted} have been converted. "
            ."Unpaid invoices are {$unpaidInvoices}, so collections are not the main issue right now.";
    }

    private function invoiceGenerationReply(User $user): string
    {
        $sales = SalesQuotation::query()->visibleTo($user);
        $invoices = Invoice::query()->visibleTo($user);

        $salesCount = (clone $sales)->count();
        $invoiceCount = (clone $invoices)->count();
        $withoutInvoice = (clone $sales)->whereDoesntHave('invoice')->count();
        $drafts = (clone $sales)->where('status', SalesQuotation::STATUS_DRAFT)->count();
        $approvedOrAccepted = (clone $sales)->whereIn('status', [
            SalesQuotation::STATUS_APPROVED,
            SalesQuotation::STATUS_SENT,
            SalesQuotation::STATUS_ACCEPTED,
        ])->count();
        $converted = (clone $sales)->where('status', SalesQuotation::STATUS_CONVERTED)->count();

        if ($salesCount === 0) {
            return 'No invoices have been generated because there are no sales quotations recorded yet.';
        }

        return "Invoices are low because quotation conversion is low: the CRM has {$salesCount} sales {$this->plural('quotation', $salesCount)} "
            ."but {$invoiceCount} {$this->plural('invoice', $invoiceCount)}. {$withoutInvoice} {$this->plural('quotation', $withoutInvoice)} "
            ."still have no linked invoice. Usually the next step is to move approved or accepted quotations into invoicing; "
            ."right now {$approvedOrAccepted} are in that follow-up zone, {$drafts} are still drafts, and {$converted} are already converted.";
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function conversationText(array $history, string $message): string
    {
        $previous = collect($history)
            ->take(-4)
            ->pluck('content')
            ->implode(' ');

        return Str::of($previous.' '.$message)->lower()->squish()->toString();
    }

    private function firstName(User $user): string
    {
        return Str::of($user->name)->before(' ')->toString();
    }

    private function money(float $amount): string
    {
        return 'E'.number_format($amount, 2);
    }

    private function plural(string $word, int $count): string
    {
        return Str::plural($word, $count);
    }
}
