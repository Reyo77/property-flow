<?php

namespace App\Actions\Engagement;

use App\Enums\ForumTopicKind;
use App\Models\Community;
use App\Models\ContentReport;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posting to the community forum and classifieds, reporting posts, and moderating them.
 * Moderation hides rather than deletes, so a moderator can always put something back.
 */
class ForumActions
{
    public function postTopic(Community $community, User $author, ForumTopicKind $kind, string $title, string $body, ?int $priceCents): ForumTopic
    {
        $topic = new ForumTopic([
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'price_cents' => $kind === ForumTopicKind::ForSale ? $priceCents : null,
        ]);
        $topic->forceFill([
            'company_id' => $community->company_id,
            'community_id' => $community->id,
            'author_id' => $author->id,
            'last_activity_at' => now(),
        ])->save();

        return $topic;
    }

    /**
     * @throws ValidationException when the topic is locked, closed or hidden
     */
    public function reply(ForumTopic $topic, User $author, string $body): ForumPost
    {
        if ($topic->isLocked() || $topic->isHidden() || $topic->closed_at !== null) {
            throw ValidationException::withMessages(['reply' => __('This conversation is closed to new replies.')]);
        }

        return DB::transaction(function () use ($topic, $author, $body): ForumPost {
            $post = new ForumPost;
            $post->forceFill([
                'company_id' => $topic->company_id,
                'forum_topic_id' => $topic->id,
                'author_id' => $author->id,
                'body' => $body,
            ])->save();

            $topic->forceFill(['last_activity_at' => now()])->save();

            return $post;
        });
    }

    /**
     * @throws ValidationException when this person already reported it
     */
    public function report(ForumTopic|ForumPost $content, User $reporter, string $reason): ContentReport
    {
        if ($content->author_id === $reporter->id) {
            throw ValidationException::withMessages(['reason' => __('You can\'t report your own post.')]);
        }

        $communityId = $content instanceof ForumTopic ? $content->community_id : $content->topic->community_id;

        try {
            $report = new ContentReport;
            $report->forceFill([
                'company_id' => $content->company_id,
                'community_id' => $communityId,
                'reportable_type' => $content->getMorphClass(),
                'reportable_id' => $content->id,
                'reported_by_id' => $reporter->id,
                'reason' => $reason,
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['reason' => __('You have already reported this.')]);
        }

        return $report;
    }

    /**
     * Hides (or restores) a topic or reply, and resolves its open reports.
     */
    public function setHidden(ForumTopic|ForumPost $content, bool $hidden, User $moderator): void
    {
        DB::transaction(function () use ($content, $hidden, $moderator): void {
            $content->forceFill([
                'hidden_at' => $hidden ? now() : null,
                'hidden_by_id' => $hidden ? $moderator->id : null,
            ])->save();

            $this->resolveReports($content, $moderator);
        });
    }

    public function resolveReports(ForumTopic|ForumPost $content, User $moderator): void
    {
        ContentReport::query()->withoutGlobalScopes()
            ->where('reportable_type', $content->getMorphClass())
            ->where('reportable_id', $content->id)
            ->whereNull('resolved_at')
            ->update(['resolved_at' => now(), 'resolved_by_id' => $moderator->id]);
    }

    public function setLocked(ForumTopic $topic, bool $locked): void
    {
        $topic->forceFill(['locked_at' => $locked ? now() : null])->save();
    }

    public function setPinned(ForumTopic $topic, bool $pinned): void
    {
        $topic->forceFill(['is_pinned' => $pinned])->save();
    }

    /**
     * The author marks a listing as sold / no longer available.
     */
    public function closeListing(ForumTopic $topic): void
    {
        $topic->forceFill(['closed_at' => now()])->save();
    }
}
