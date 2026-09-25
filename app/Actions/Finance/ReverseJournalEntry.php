<?php

namespace App\Actions\Finance;

use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Finance\JournalLine;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Cancels a posted entry the only way the ledger allows: by posting its exact mirror image.
 * The original stays in history; the pair nets to zero.
 */
class ReverseJournalEntry
{
    public function __construct(private readonly PostJournalEntry $postJournalEntry) {}

    /**
     * @throws LogicException when the entry is itself a reversal or was already reversed
     */
    public function handle(JournalEntry $entry, CarbonInterface $postedOn, string $memo, ?User $reversedBy = null, ?Model $source = null): JournalEntry
    {
        if ($entry->isReversal()) {
            throw new LogicException('A reversal cannot itself be reversed; post a new entry instead.');
        }

        if ($entry->reversal()->exists()) {
            throw new LogicException('This entry has already been reversed.');
        }

        $entry->loadMissing(['community', 'lines.account']);

        $lines = array_values($entry->lines
            ->map(fn ($line) => JournalLine::fromStored($line->account, $line->debit_cents, $line->credit_cents, $line->unit_id, $line->memo)->flipped())
            ->all());

        return $this->postJournalEntry->handle(
            $entry->community,
            $postedOn,
            $memo,
            $lines,
            $source ?? ($entry->source_type !== null ? $entry->source : null),
            $reversedBy,
            reverses: $entry,
        );
    }
}
