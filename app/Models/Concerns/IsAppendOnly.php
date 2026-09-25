<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Refuses to update or delete a saved record. Ledger history is corrected by posting a
 * reversing entry, never by rewriting what was already posted. (The database enforces the
 * same rule with triggers; this fails earlier with a clearer message.)
 */
trait IsAppendOnly
{
    public static function bootIsAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw new LogicException(class_basename($model).' records are append-only; post a reversal instead of editing.');
        });

        static::deleting(function (Model $model): void {
            throw new LogicException(class_basename($model).' records are append-only; post a reversal instead of deleting.');
        });
    }
}
