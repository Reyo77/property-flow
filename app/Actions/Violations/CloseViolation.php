<?php

namespace App\Actions\Violations;

use App\Enums\ViolationStatus;
use App\Models\User;
use App\Models\Violation;
use LogicException;

/**
 * Resolves (fixed) or dismisses (not a breach after all) a violation, stopping any further
 * escalation. Fines already invoiced stay on the unit's account; waive one by voiding its
 * invoice in Finance.
 */
class CloseViolation
{
    /**
     * @throws LogicException
     */
    public function handle(Violation $violation, ViolationStatus $status, ?string $notes, User $closedBy): void
    {
        if ($status === ViolationStatus::Open) {
            throw new LogicException('Use Resolved or Dismissed to close a violation.');
        }

        if (! $violation->isOpen()) {
            throw new LogicException(__('This violation is already closed.'));
        }

        $violation->forceFill([
            'status' => $status,
            'next_action_on' => null,
            'closed_at' => now(),
            'closed_by_id' => $closedBy->id,
            'resolution_notes' => $notes,
        ])->save();
    }
}
