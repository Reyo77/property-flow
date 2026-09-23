<?php

namespace App\Models;

use App\Enums\ContactCategory;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A phone book entry: staff, emergency or vendor contact for a community.
 *
 * @property int $id
 * @property int $community_id
 * @property string $name
 * @property string|null $title
 * @property ContactCategory $category
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property bool $visible_to_residents
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Community $community
 */
#[Fillable(['name', 'title', 'category', 'phone', 'email', 'notes', 'visible_to_residents'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToCompany, HasFactory, LogsActivity, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ContactCategory::class,
            'visible_to_residents' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
