<?php

namespace App\Actions\ArchitecturalRequests;

use App\Enums\ArchitecturalRequestStatus;
use App\Enums\WebhookEvent;
use App\Models\ArchitecturalRequest;
use App\Models\Unit;
use App\Models\User;
use App\Support\Governance\VotingRoll;
use App\Support\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * An owner asks the board for permission to alter their unit, attaching plans or photos.
 */
class SubmitArchitecturalRequest
{
    public function __construct(private readonly VotingRoll $votingRoll) {}

    /**
     * @param  list<UploadedFile>  $plans
     *
     * @throws AuthorizationException when the user isn't a current owner of the unit
     */
    public function handle(Unit $unit, User $owner, string $title, string $description, ?string $contractor, ?CarbonImmutable $plannedStartOn, array $plans): ArchitecturalRequest
    {
        if (! $this->votingRoll->isOwner($owner, $unit)) {
            throw new AuthorizationException(__('Only an owner of the unit can ask for changes to it.'));
        }

        return DB::transaction(function () use ($unit, $owner, $title, $description, $contractor, $plannedStartOn, $plans): ArchitecturalRequest {
            $request = new ArchitecturalRequest([
                'unit_id' => $unit->id,
                'title' => $title,
                'description' => $description,
                'contractor' => $contractor,
                'planned_start_on' => $plannedStartOn?->toDateString(),
            ]);
            $request->forceFill([
                'company_id' => $unit->company_id,
                'community_id' => $unit->community_id,
                'submitted_by_id' => $owner->id,
                'status' => ArchitecturalRequestStatus::Submitted,
            ])->save();

            foreach ($plans as $plan) {
                $path = $plan->storeAs(
                    "attachments/{$request->company_id}/architectural-requests/{$request->id}",
                    Str::uuid().'.'.$plan->getClientOriginalExtension(),
                    'local',
                );

                $request->attachments()->make([
                    'uploaded_by_id' => $owner->id,
                    'disk_path' => $path,
                    'original_filename' => $plan->getClientOriginalName(),
                    'mime_type' => (string) $plan->getClientMimeType(),
                    'size_bytes' => $plan->getSize() ?: 0,
                ])->forceFill(['company_id' => $request->company_id])->save();
            }

            app(Webhooks::class)->dispatch(WebhookEvent::ArchitecturalRequestSubmitted, $request);

            return $request;
        });
    }
}
