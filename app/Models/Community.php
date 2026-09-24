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
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
     * @return HasMany<Amenity, $this>
     */
    public function amenities(): HasMany
    {
        return $this->hasMany(Amenity::class);
    }

    /**
     * @return HasMany<AmenityBooking, $this>
     */
    public function amenityBookings(): HasMany
    {
        return $this->hasMany(AmenityBooking::class);
    }

    /**
     * @return HasMany<Package, $this>
     */
    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    /**
     * @return HasMany<Visitor, $this>
     */
    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }

    /**
     * @return HasMany<GuestPass, $this>
     */
    public function guestPasses(): HasMany
    {
        return $this->hasMany(GuestPass::class);
    }

    /**
     * @return HasMany<ParkingPermit, $this>
     */
    public function parkingPermits(): HasMany
    {
        return $this->hasMany(ParkingPermit::class);
    }

    /**
     * @return HasMany<IncidentReport, $this>
     */
    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    /**
     * @return HasMany<AccessKey, $this>
     */
    public function accessKeys(): HasMany
    {
        return $this->hasMany(AccessKey::class);
    }

    /**
     * @return HasMany<EntryAuthorization, $this>
     */
    public function entryAuthorizations(): HasMany
    {
        return $this->hasMany(EntryAuthorization::class);
    }

    /**
     * @return HasMany<PatrolRoute, $this>
     */
    public function patrolRoutes(): HasMany
    {
        return $this->hasMany(PatrolRoute::class);
    }

    /**
     * Only for route-model-binding scoped to a community (`communities/{community}/patrol-checkpoints/{patrolCheckpoint}`);
     * use a route's own `checkpoints()` relation for everything else.
     *
     * @return HasManyThrough<PatrolCheckpoint, PatrolRoute, $this>
     */
    public function patrolCheckpoints(): HasManyThrough
    {
        return $this->hasManyThrough(PatrolCheckpoint::class, PatrolRoute::class);
    }

    /**
     * @return HasMany<ShiftLogEntry, $this>
     */
    public function shiftLogEntries(): HasMany
    {
        return $this->hasMany(ShiftLogEntry::class);
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
