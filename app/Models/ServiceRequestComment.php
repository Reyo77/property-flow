<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ServiceRequestCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_request_id
 * @property int|null $author_id
 * @property string $body
 * @property bool $visible_to_resident
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ServiceRequest $serviceRequest
 * @property-read User|null $author
 */
#[Fillable(['author_id', 'body', 'visible_to_resident'])]
class ServiceRequestComment extends Model
{
    /** @use HasFactory<ServiceRequestCommentFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visible_to_resident' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
