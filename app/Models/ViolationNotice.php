<?php

namespace App\Models;

use App\Enums\ViolationStage;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ViolationNoticeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step on a violation's ladder: the letter sent (and, for a fine, the invoice raised).
 *
 * @property int $id
 * @property int $company_id
 * @property int $violation_id
 * @property ViolationStage $stage
 * @property Carbon $issued_on
 * @property Carbon|null $cure_by
 * @property int|null $invoice_id
 * @property int|null $issued_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Violation $violation
 * @property-read Invoice|null $invoice
 */
class ViolationNotice extends Model
{
    /** @use HasFactory<ViolationNoticeFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ViolationStage::class,
            'issued_on' => 'date',
            'cure_by' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Violation, $this>
     */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
