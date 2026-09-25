<?php

namespace App\Actions\ArchitecturalRequests;

use App\Enums\ArchitecturalRequestStatus;
use App\Enums\NotificationCategory;
use App\Models\ArchitecturalRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\ArchitecturalRequestDecided;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The board's review: take a request under review, then approve it (optionally with
 * conditions) or deny it. A decision is final; the owner can submit a new request.
 */
class DecideArchitecturalRequest
{
    /**
     * @throws LogicException when the request is no longer open
     */
    public function startReview(ArchitecturalRequest $request, User $reviewer): void
    {
        if ($request->status !== ArchitecturalRequestStatus::Submitted) {
            throw new LogicException(__('Only a newly submitted request can be taken under review.'));
        }

        $request->forceFill(['status' => ArchitecturalRequestStatus::UnderReview])->save();

        activity()->performedOn($request)->causedBy($reviewer)->log('review started');
    }

    /**
     * @throws LogicException when the request is no longer open
     * @throws ValidationException when conditions or reasons are missing
     */
    public function decide(ArchitecturalRequest $request, ArchitecturalRequestStatus $decision, ?string $conditions, ?string $notes, User $decidedBy): void
    {
        if (! $decision->isDecided()) {
            throw new LogicException('A decision must approve, approve with conditions, or deny.');
        }

        if (! $request->status->isOpen()) {
            throw new LogicException(__('This request has already been decided or withdrawn.'));
        }

        if ($decision === ArchitecturalRequestStatus::ApprovedWithConditions && trim((string) $conditions) === '') {
            throw ValidationException::withMessages(['conditions' => __('List the conditions of approval.')]);
        }

        if ($decision === ArchitecturalRequestStatus::Denied && trim((string) $notes) === '') {
            throw ValidationException::withMessages(['decision_notes' => __('Give the owner a reason for the denial.')]);
        }

        $request->forceFill([
            'status' => $decision,
            'conditions' => $decision === ArchitecturalRequestStatus::ApprovedWithConditions ? $conditions : null,
            'decision_notes' => $notes,
            'decided_by_id' => $decidedBy->id,
            'decided_at' => now(),
        ])->save();

        $submitter = $request->submittedBy;

        if ($submitter !== null && NotificationPreference::inAppEnabled($submitter, NotificationCategory::ArchitecturalRequests)) {
            $submitter->notify(new ArchitecturalRequestDecided($request));
        }
    }

    /**
     * @throws LogicException
     */
    public function withdraw(ArchitecturalRequest $request, User $owner): void
    {
        if (! $request->isSubmittedBy($owner) || ! $request->status->isOpen()) {
            throw new LogicException(__('Only an open request can be withdrawn, by whoever submitted it.'));
        }

        $request->forceFill(['status' => ArchitecturalRequestStatus::Withdrawn])->save();
    }
}
