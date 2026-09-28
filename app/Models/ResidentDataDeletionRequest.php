<?php

namespace App\Models;

use App\Enums\DataDeletionStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ResidentDataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A resident's request that the company erase their personal data (privacy). Staff review and
 * decide; approving anonymizes the resident's record at once, rather than deleting it outright,
 * so the community's history (past residencies, invoices, votes) stays intact but no longer
 * names them.
 *
 * @property int $id
 * @property int $company_id
 * @property int $resident_id
 * @property int $requested_by_id
 * @property DataDeletionStatus $status
 * @property string|null $notes
 * @property int|null $reviewed_by_id
 * @property Carbon|null $reviewed_at
 * @property string|null $decision_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 * @property-read User $requestedBy
 * @property-read User|null $reviewedBy
 */
#[Fillable(['notes'])]
class ResidentDataDeletionRequest extends Model
{
    /** @use HasFactory<ResidentDataDeletionRequestFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DataDeletionStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }
}
