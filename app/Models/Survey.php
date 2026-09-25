<?php

namespace App\Models;

use App\Enums\Audience;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\SurveyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A survey (several questions, results for staff) or a poll (one question, results shown to
 * everyone who has answered).
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $title
 * @property string|null $description
 * @property bool $is_poll
 * @property Audience $audience
 * @property bool $is_anonymous
 * @property Carbon|null $published_at
 * @property Carbon|null $closes_at
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
#[Fillable(['title', 'description', 'is_poll', 'audience', 'is_anonymous', 'closes_at'])]
class Survey extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<SurveyFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_poll' => 'boolean',
            'is_anonymous' => 'boolean',
            'audience' => Audience::class,
            'published_at' => 'datetime',
            'closes_at' => 'datetime',
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
     * @return HasMany<SurveyQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @return HasMany<SurveyResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function isOpen(): bool
    {
        return $this->published_at !== null && ($this->closes_at === null || $this->closes_at->isFuture());
    }
}
