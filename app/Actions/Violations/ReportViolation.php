<?php

namespace App\Actions\Violations;

use App\Enums\ViolationStage;
use App\Enums\ViolationStatus;
use App\Models\Unit;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationRule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logs a breach at a unit, with photos, and sends the owners a courtesy notice straight away.
 */
class ReportViolation
{
    public function __construct(private readonly IssueViolationNotice $issueNotice) {}

    /**
     * @param  list<UploadedFile>  $photos
     *
     * @throws ValidationException
     */
    public function handle(ViolationRule $rule, Unit $unit, CarbonImmutable $observedAt, string $description, ?string $location, array $photos, User $reportedBy): Violation
    {
        if ($unit->community_id !== $rule->community_id) {
            throw ValidationException::withMessages(['unit_id' => __('That unit is not in this community.')]);
        }

        if (! $rule->is_active) {
            throw ValidationException::withMessages(['violation_rule_id' => __('That rule is no longer in force.')]);
        }

        $violation = DB::transaction(function () use ($rule, $unit, $observedAt, $description, $location, $photos, $reportedBy): Violation {
            $violation = new Violation;
            $violation->forceFill([
                'company_id' => $rule->company_id,
                'community_id' => $rule->community_id,
                'violation_rule_id' => $rule->id,
                'unit_id' => $unit->id,
                'reported_by_id' => $reportedBy->id,
                'observed_at' => $observedAt->utc(),
                'location' => $location,
                'description' => $description,
                'status' => ViolationStatus::Open,
            ])->save();

            foreach ($photos as $photo) {
                $path = $photo->storeAs(
                    "attachments/{$violation->company_id}/violations/{$violation->id}",
                    Str::uuid().'.'.$photo->getClientOriginalExtension(),
                    'local',
                );

                $violation->attachments()->make([
                    'uploaded_by_id' => $reportedBy->id,
                    'disk_path' => $path,
                    'original_filename' => $photo->getClientOriginalName(),
                    'mime_type' => (string) $photo->getClientMimeType(),
                    'size_bytes' => $photo->getSize() ?: 0,
                ])->forceFill(['company_id' => $violation->company_id])->save();
            }

            return $violation;
        });

        $today = CarbonImmutable::now($unit->loadMissing('community')->community->timezone)->startOfDay();
        $this->issueNotice->handle($violation, ViolationStage::Courtesy, $today, $reportedBy);

        return $violation->refresh();
    }
}
