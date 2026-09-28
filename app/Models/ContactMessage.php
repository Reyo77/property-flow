<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCommunity;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Contracts\BelongsToOneCommunity;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A message submitted through a community's public website contact form.
 *
 * @property int $id
 * @property int $company_id
 * @property int $community_id
 * @property string $name
 * @property string $email
 * @property string $message
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Community $community
 */
class ContactMessage extends Model implements BelongsToOneCommunity
{
    /** @use HasFactory<ContactMessageFactory> */
    use BelongsToCommunity, BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
