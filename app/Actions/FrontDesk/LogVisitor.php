<?php

namespace App\Actions\FrontDesk;

use App\Enums\WebhookEvent;
use App\Events\FrontDeskActivity;
use App\Models\Community;
use App\Models\User;
use App\Models\Visitor;
use App\Support\Webhooks\Webhooks;

class LogVisitor
{
    /**
     * Checks a visitor in at the front desk and tells the live front-desk screens.
     *
     * @param  array{unit_id?: int|string|null, visitor_name: string, purpose?: string|null, notes?: string|null}  $validated
     */
    public function handle(Community $community, User $loggedBy, array $validated): Visitor
    {
        $validated = array_map(fn (mixed $value) => $value === '' ? null : $value, $validated);

        $visitor = $community->visitors()->make($validated);
        $visitor->forceFill(['logged_by_id' => $loggedBy->id])->save();
        app(Webhooks::class)->dispatch(WebhookEvent::VisitorCheckedIn, $visitor);

        FrontDeskActivity::dispatch($community->id, 'visitor', __(':name checked in.', ['name' => $visitor->visitor_name]));

        return $visitor;
    }
}
