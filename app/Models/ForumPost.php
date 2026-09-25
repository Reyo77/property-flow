<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\ForumPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $forum_topic_id
 * @property int $author_id
 * @property string $body
 * @property Carbon|null $hidden_at
 * @property int|null $hidden_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ForumTopic $topic
 * @property-read User $author
 */
class ForumPost extends Model
{
    /** @use HasFactory<ForumPostFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return MorphMany<ContentReport, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany(ContentReport::class, 'reportable');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
