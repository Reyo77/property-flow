<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Database\Factories\NotificationPreferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property NotificationCategory $category
 * @property bool $in_app
 * @property bool $email
 * @property bool $sms
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['category', 'in_app', 'email', 'sms'])]
class NotificationPreference extends Model
{
    /** @use HasFactory<NotificationPreferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => NotificationCategory::class,
            'in_app' => 'boolean',
            'email' => 'boolean',
            'sms' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the user wants in-app notifications for this category (defaults to yes).
     */
    public static function inAppEnabled(User $user, NotificationCategory $category): bool
    {
        $preference = $user->notificationPreferences()->where('category', $category)->first();

        return $preference === null || $preference->in_app;
    }
}
