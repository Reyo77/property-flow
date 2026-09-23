<?php

namespace App\Models;

use App\Enums\Permission;
use App\Support\Tenancy\PermissionTeam;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deactivated_at
 * @property-read Company|null $company
 * @property-read Resident|null $resident
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'deactivated_at' => null,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Communities this team member is assigned to.
     *
     * @return BelongsToMany<Community, $this>
     */
    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Community::class)->withTimestamps();
    }

    /**
     * The resident record behind this login, for people who live in or own a unit.
     *
     * @return HasOne<Resident, $this>
     */
    public function resident(): HasOne
    {
        return $this->hasOne(Resident::class);
    }

    /**
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    /**
     * The name of the user's role in their company, or null for residents without a team role.
     */
    public function companyRoleName(): ?string
    {
        if ($this->company_id === null) {
            return null;
        }

        return PermissionTeam::run($this->company_id, fn (): ?string => $this->roles()->value('name'));
    }

    /**
     * Whether the user may work in the community as a team member.
     */
    public function canAccessCommunity(Community $community): bool
    {
        return $this->canAccessCommunityById($community->company_id, $community->id);
    }

    /**
     * Whether the user may work in a community, by its ids, without loading it.
     */
    public function canAccessCommunityById(int $companyId, int $communityId): bool
    {
        if ($this->company_id === null || $this->company_id !== $companyId) {
            return false;
        }

        if ($this->hasCompanyPermission(Permission::AccessAllCommunities)) {
            return true;
        }

        return $this->communities->contains($communityId);
    }

    /**
     * Whether the user's role in their own company grants the permission.
     *
     * Always evaluated against the user's company, even when another company's roles are active.
     */
    public function hasCompanyPermission(Permission $permission): bool
    {
        $companyId = $this->company_id;

        if ($companyId === null) {
            return false;
        }

        if (getPermissionsTeamId() === $companyId) {
            return $this->checkPermissionTo($permission->value);
        }

        $this->unsetRelation('roles')->unsetRelation('permissions');

        try {
            return PermissionTeam::run($companyId, fn (): bool => $this->checkPermissionTo($permission->value));
        } finally {
            $this->unsetRelation('roles')->unsetRelation('permissions');
        }
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
