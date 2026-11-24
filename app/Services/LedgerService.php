<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place journal entries are written.
 *
 * Guarantees: every entry balances, every line carries a debit or a credit but
 * never both, and posted entries are never edited — only reversed.
 */
class LedgerService
{
    public function __construct(private readonly FinanceNumberService $numbers) {}

    public function alreadyPosted(string $sourceType, int $sourceId): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * @param  array<int, array{account_id:int, debit?:float, credit?:float, description?:?string, contact_type?:?string, contact_id?:?int}>  $lines
     * @param  array<string, mixed>  $meta
     *
     * @throws InvalidArgumentException
     */
    public function post(array $lines, array $meta = []): JournalEntry
    {
        $normalised = $this->normalise($lines);
        $this->assertBalanced($normalised);

        return DB::transaction(function () use ($normalised, $meta): JournalEntry {
            $entry = JournalEntry::query()->create([
                'entry_number' => $meta['entry_number'] ?? $this->numbers->journalEntryNumber(),
                'entry_date' => $meta['entry_date'] ?? now()->toDateString(),
                'memo' => $meta['memo'] ?? null,
                'source_type' => $meta['source_type'] ?? null,
                'source_id' => $meta['source_id'] ?? null,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'posted_by' => $meta['posted_by'] ?? auth()->id(),
                'created_by' => $meta['created_by'] ?? auth()->id(),
            ]);

            foreach ($normalised as $line) {
                $entry->lines()->create($line);
            }

            return $entry->load('lines');
        });
    }

    /**
     * Corrections are made by posting the mirror image, never by editing.
     */
    public function reverse(JournalEntry $entry, ?string $reason = null, ?User $by = null): JournalEntry
    {
        if (! $entry->isPosted()) {
            throw new InvalidArgumentException('Only a posted entry can be reversed.');
        }

        $mirrored = $entry->lines->map(fn ($line): array => [
            'account_id' => $line->account_id,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'description' => $line->description,
            'contact_type' => $line->contact_type,
            'contact_id' => $line->contact_id,
        ])->all();

        $reversal = $this->post($mirrored, [
            'entry_date' => now()->toDateString(),
            'memo' => 'Reversal of '.$entry->entry_number.($reason ? ': '.$reason : ''),
            'posted_by' => $by?->id,
            'created_by' => $by?->id,
        ]);

        $entry->update([
            'status' => JournalEntry::STATUS_REVERSED,
            'reversed_by_id' => $reversal->id,
        ]);

        return $reversal;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function normalise(array $lines): array
    {
        $normalised = [];

        foreach ($lines as $line) {
            $accountId = $line['account_id'] ?? null;

            if (! $accountId) {
                throw new InvalidArgumentException('Every journal line needs an account.');
            }

            $debit = round(max(0, (float) ($line['debit'] ?? 0)), 2);
            $credit = round(max(0, (float) ($line['credit'] ?? 0)), 2);

            if ($debit > 0 && $credit > 0) {
                throw new InvalidArgumentException('A journal line cannot be both a debit and a credit.');
            }

            if ($debit === 0.0 && $credit === 0.0) {
                throw new InvalidArgumentException('A journal line must carry a debit or a credit.');
            }

            $normalised[] = [
                'account_id' => (int) $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $line['description'] ?? null,
                'contact_type' => $line['contact_type'] ?? null,
                'contact_id' => $line['contact_id'] ?? null,
            ];
        }

        if (count($normalised) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        return $normalised;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function assertBalanced(array $lines): void
    {
        $debits = round(array_sum(array_column($lines, 'debit')), 2);
        $credits = round(array_sum(array_column($lines, 'credit')), 2);

        if (abs($debits - $credits) >= 0.005) {
            throw new InvalidArgumentException(sprintf(
                'Journal entry is out of balance: debits %.2f, credits %.2f.',
                $debits,
                $credits,
            ));
        }
    }
}
