<?php

namespace App\Models;

use App\Enums\Audience;
use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ConsentFormFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A document residents consent to by signing it online (a move-in agreement, a pet waiver, a
 * consent to electronic notices).
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $title
 * @property string $body
 * @property Audience $audience
 * @property Carbon|null $published_at
 * @property Carbon|null $closes_at
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
#[Fillable(['title', 'body', 'audience', 'closes_at'])]
class ConsentForm extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ConsentFormFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return HasMany<ConsentSignature, $this>
     */
    public function signatures(): HasMany
    {
        return $this->hasMany(ConsentSignature::class);
    }

    public function isOpen(): bool
    {
        return $this->published_at !== null && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /**
     * A fingerprint of the text, stored with each signature.
     */
    public function bodyHash(): string
    {
        return hash('sha256', $this->title."\n".$this->body);
    }
}
