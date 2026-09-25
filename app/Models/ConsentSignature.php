<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\IsAppendOnly;
use Database\Factories\ConsentSignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $consent_form_id
 * @property int $user_id
 * @property string $signed_name
 * @property string $signature_disk_path
 * @property string $body_hash
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $signed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ConsentForm $form
 * @property-read User $user
 */
class ConsentSignature extends Model
{
    /** @use HasFactory<ConsentSignatureFactory> */
    use BelongsToCompany, HasFactory, IsAppendOnly;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ConsentForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(ConsentForm::class, 'consent_form_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
