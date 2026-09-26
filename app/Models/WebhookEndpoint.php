<?php

namespace App\Models;

use App\Enums\WebhookEvent;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A URL on another system that receives the company's events as signed JSON POSTs.
 *
 * @property int $id
 * @property int $company_id
 * @property string $url
 * @property string|null $description
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property Carbon|null $last_delivered_at
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company $company
 */
#[Fillable(['url', 'description', 'events'])]
#[Hidden(['secret'])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToCompany, HasFactory;

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(40);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
            'last_delivered_at' => 'datetime',
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
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(WebhookEvent $event): bool
    {
        return in_array($event->value, $this->events, true);
    }
}
