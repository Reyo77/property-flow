<?php

namespace App\Actions\Violations;

use App\Enums\ViolationStage;
use App\Enums\WebhookEvent;
use App\Models\User;
use App\Models\Violation;
use App\Models\ViolationNotice;
use App\Support\Webhooks\Webhooks;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Takes an open violation one rung up the ladder once its cure period has passed: courtesy
 * notice → warning → fine → further fines, up to the rule's limit. Does nothing if it isn't
 * due yet, so running it repeatedly (the daily job, or a manager clicking twice) is harmless.
 */
class EscalateViolation
{
    public function __construct(private readonly IssueViolationNotice $issueNotice) {}

    /**
     * @param  bool  $force  escalate now even if the cure period hasn't run out (a manager's call)
     */
    public function handle(Violation $violation, CarbonImmutable $today, ?User $by = null, bool $force = false): ?ViolationNotice
    {
        return DB::transaction(function () use ($violation, $today, $by, $force): ?ViolationNotice {
            $violation = Violation::query()->withoutGlobalScopes()->whereKey($violation->id)->lockForUpdate()->with('rule')->firstOrFail();

            if (! $violation->isOpen() || $violation->next_action_on === null) {
                return null;
            }

            if (! $force && $violation->next_action_on->toDateString() > $today->toDateString()) {
                return null;
            }

            $next = match ($violation->stage) {
                null, ViolationStage::Courtesy => ViolationStage::Warning,
                ViolationStage::Warning, ViolationStage::Fine => ViolationStage::Fine,
            };

            $notice = $this->issueNotice->handle($violation, $next, $today, $by);
            $violation->refresh();

            app(Webhooks::class)->dispatch(WebhookEvent::ViolationEscalated, $violation);

            return $notice;
        });
    }
}
