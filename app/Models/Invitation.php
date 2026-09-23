<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invite to join a company, as a team member (with a role) or as a resident (for the resident portal).
 *
 * The link token is only ever stored as a hash.
 *
 * @property int $id
 * @property int|null $invited_by_id
 * @property int|null $resident_id
 * @property string $name
 * @property string $email
 * @property string|null $role
 * @property list<int>|null $community_ids
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $invitedBy
 * @property-read Resident|null $resident
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToCompany, HasFactory;

    public const int EXPIRES_AFTER_DAYS = 7;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'community_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Resident, $this>
     */
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Find the invitation behind a link, across all companies.
     */
    public static function findByToken(string $token): ?self
    {
        return self::withoutGlobalScopes()->where('token_hash', self::hashToken($token))->first();
    }

    /**
     * Invitations that can still be accepted.
     *
     * @param  Builder<Invitation>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function isForTeam(): bool
    {
        return $this->resident_id === null;
    }
}
