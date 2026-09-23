<?php

namespace App\Models;

use App\Enums\AreaUnit;
use App\Enums\CommunityType;
use App\Enums\Permission;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property CommunityType $type
 * @property string|null $address_line_1
 * @property string|null $address_line_2
 * @property string|null $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string $country
 * @property string $timezone
 * @property string $currency
 * @property AreaUnit $area_unit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name', 'type', 'address_line_1', 'address_line_2', 'city', 'region',
    'postal_code', 'country', 'timezone', 'currency', 'area_unit',
])]
class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CommunityType::class,
            'area_unit' => AreaUnit::class,
        ];
    }

    /**
     * @return HasMany<Building, $this>
     */
    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class);
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @return HasMany<Contact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Announcement, $this>
     */
    public function announcements(): HasMany
    {
        return $this->hasMany(Announcement::class);
    }

    /**
     * @return HasMany<DocumentFolder, $this>
     */
    public function documentFolders(): HasMany
    {
        return $this->hasMany(DocumentFolder::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    /**
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * @return HasMany<Residency, $this>
     */
    public function residencies(): HasMany
    {
        return $this->hasMany(Residency::class);
    }

    /**
     * Everyone who has ever lived in or owned a unit here.
     *
     * @return BelongsToMany<Resident, $this>
     */
    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'residencies')->distinct();
    }

    /**
     * Team members assigned to this community.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Communities the user may work in: all of them with the "access every community" permission, otherwise their assignments.
     *
     * @param  Builder<Community>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        if ($user->hasCompanyPermission(Permission::AccessAllCommunities)) {
            return;
        }

        $query->whereHas('users', fn (Builder $query) => $query->whereKey($user->id));
    }

    /**
     * The sum of all unit factors as an exact decimal string, or null when no unit has one.
     *
     * @return numeric-string|null
     */
    public function totalUnitFactor(): ?string
    {
        $total = $this->units()->toBase()->selectRaw('SUM(unit_factor) as total')->value('total');

        return is_string($total) && is_numeric($total) ? $total : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
