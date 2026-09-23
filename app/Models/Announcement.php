<?php

namespace App\Models;

use App\Enums\AnnouncementAudience;
use App\Enums\ResidencyType;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $community_id
 * @property int|null $created_by_id
 * @property string $title
 * @property string $body
 * @property AnnouncementAudience $audience_type
 * @property ResidencyType|null $residency_type
 * @property bool $pinned
 * @property Carbon|null $publish_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 * @property-read User|null $createdBy
 */
#[Fillable(['created_by_id', 'title', 'body', 'audience_type', 'residency_type', 'pinned', 'publish_at'])]
class Announcement extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<AnnouncementFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audience_type' => AnnouncementAudience::class,
            'residency_type' => ResidencyType::class,
            'pinned' => 'boolean',
            'publish_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return BelongsToMany<Building, $this>
     */
    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class);
    }

    /**
     * @return BelongsToMany<Unit, $this>
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function isScheduled(): bool
    {
        return $this->published_at === null && $this->publish_at !== null && $this->publish_at->isFuture();
    }

    public function isDraft(): bool
    {
        return $this->published_at === null && $this->publish_at === null;
    }

    /**
     * Whether the announcement's audience covers this residency (their unit, building or type).
     *
     * The relations used here (buildings/units) must already be loaded to avoid N+1 queries
     * when checking many residencies against many announcements.
     */
    public function matchesResidency(Residency $residency): bool
    {
        return match ($this->audience_type) {
            AnnouncementAudience::Community => true,
            AnnouncementAudience::Buildings => $residency->unit->building_id !== null
                && $this->buildings->contains('id', $residency->unit->building_id),
            AnnouncementAudience::Units => $this->units->contains('id', $residency->unit_id),
            AnnouncementAudience::ResidencyType => $this->residency_type === $residency->type,
        };
    }

    /**
     * Announcements that are visible right now: published, not scheduled for the future.
     *
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Announcements a scheduler run should publish now.
     *
     * @param  Builder<Announcement>  $query
     */
    #[Scope]
    protected function dueToPublish(Builder $query): void
    {
        $query->whereNull('published_at')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
