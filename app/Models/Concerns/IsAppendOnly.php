<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Refuses to update or delete a saved record: ledger lines (corrected by posting a reversal,
 * backed by database triggers too) and ballot votes (final once cast).
 */
trait IsAppendOnly
{
    public static function bootIsAppendOnly(): void
    {
        static::updating(function (Model $model): void {
            throw new LogicException(class_basename($model).' records are append-only and cannot be edited.');
        });

        static::deleting(function (Model $model): void {
            throw new LogicException(class_basename($model).' records are append-only and cannot be deleted.');
        });
    }
}
