<?php

namespace App\Models;

use App\Enums\DataExportStatus;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\DataExportRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A company admin's request for a full download of their company's data (privacy/portability).
 * Built by a queued job into a zip of CSVs on the private disk, then offered as a download.
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $requested_by_id
 * @property DataExportStatus $status
 * @property string|null $disk_path
 * @property string|null $failure_reason
 * @property Carbon $requested_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $requestedBy
 */
class DataExportRequest extends Model
{
    /** @use HasFactory<DataExportRequestFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }
}
