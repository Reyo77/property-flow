<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ViolationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A rule from the community's bylaws, with how long owners get to fix a breach and what it
 * costs if they don't.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $title
 * @property string|null $description
 * @property string|null $reference
 * @property int $cure_days
 * @property int|null $fine_cents
 * @property int $max_fines
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
#[Fillable(['title', 'description', 'reference', 'cure_days', 'fine_cents', 'max_fines', 'is_active'])]
class ViolationRule extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ViolationRuleFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cure_days' => 'integer',
            'fine_cents' => 'integer',
            'max_fines' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
