<?php

namespace App\Actions\Residents;

use App\Enums\ResidencyType;
use App\Enums\WebhookEvent;
use App\Models\Residency;
use App\Models\Resident;
use App\Models\Unit;
use App\Support\Webhooks\Webhooks;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AddResidentToUnit
{
    /**
     * Link a resident to the unit, creating the resident first when no existing one is given.
     *
     * @param  array{name: string, email: string|null, phone: string|null}|null  $newResident
     */
    public function handle(
        Unit $unit,
        ?Resident $existingResident,
        ?array $newResident,
        ResidencyType $type,
        bool $isPrimary,
        ?string $movedInOn,
    ): Residency {
        return DB::transaction(function () use ($unit, $existingResident, $newResident, $type, $isPrimary, $movedInOn): Residency {
            $resident = $existingResident ?? (new Resident($newResident ?? throw new InvalidArgumentException('A resident is required.')))
                ->forceFill(['company_id' => $unit->company_id]);
            $resident->save();

            if ($isPrimary) {
                $unit->residencies()->active()->update(['is_primary' => false]);
            }

            $residency = $unit->residencies()->create([
                'resident_id' => $resident->id,
                'type' => $type,
                'is_primary' => $isPrimary,
                'moved_in_on' => $movedInOn,
            ]);

            app(Webhooks::class)->dispatch(WebhookEvent::ResidentMovedIn, $residency);

            return $residency;
        });
    }
}
