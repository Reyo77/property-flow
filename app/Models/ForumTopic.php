<?php

namespace App\Models;

use App\Enums\ForumTopicKind;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ForumTopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A forum discussion, or a classified listing (for sale, wanted, free, services).
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property int $author_id
 * @property ForumTopicKind $kind
 * @property string $title
 * @property string $body
 * @property int|null $price_cents
 * @property bool $is_pinned
 * @property Carbon|null $locked_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by_id
 * @property Carbon $last_activity_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 * @property-read User $author
 */
#[Fillable(['kind', 'title', 'body', 'price_cents'])]
class ForumTopic extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ForumTopicFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ForumTopicKind::class,
            'price_cents' => 'integer',
            'is_pinned' => 'boolean',
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
            'hidden_at' => 'datetime',
            'last_activity_at' => 'datetime',
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<ForumPost, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    /**
     * @param  Builder<ForumTopic>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
