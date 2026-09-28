<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A tier of usage limits a company can be put on. Not tenant-scoped: plans are shared reference
 * data set up by the platform (App\Livewire\Platform in Phase 10c), not by companies themselves.
 * A company with no plan (the default) is unlimited, so existing companies are never constrained
 * by a plan they never chose.
 *
 * @property int $id
 * @property string $name
 * @property int|null $max_communities
 * @property int|null $max_units
 * @property int|null $max_team_members
 * @property bool $is_default
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'max_communities', 'max_units', 'max_team_members', 'is_default'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_communities' => 'integer',
            'max_units' => 'integer',
            'max_team_members' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
