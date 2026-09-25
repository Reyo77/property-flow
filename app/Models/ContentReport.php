<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ContentReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A resident flagging a forum post or listing for the moderators.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $reportable_type
 * @property int $reportable_id
 * @property int $reported_by_id
 * @property string $reason
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ForumTopic|ForumPost $reportable
 * @property-read User $reportedBy
 */
class ContentReport extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ContentReportFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }
}
