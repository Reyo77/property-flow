<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @group Communication
 */
class EventRsvpController extends Controller
{
    /**
     * RSVP to an event
     *
     * Sets (or changes) your answer. Returns the event with updated counts.
     *
     * @bodyParam status string required `going`, `maybe` or `not_going`. Example: going
     *
     * @apiResource App\Http\Resources\Api\V1\EventResource
     *
     * @apiResourceModel App\Models\Event with=rsvps
     */
    public function update(Request $request, Community $community, Event $event): EventResource
    {
        Gate::authorize('rsvp', $event);

        $validated = $request->validate(['status' => ['required', Rule::enum(RsvpStatus::class)]]);

        /** @var User $user */
        $user = $request->user();
        $event->rsvps()->updateOrCreate(['user_id' => $user->id], ['status' => RsvpStatus::from($validated['status'])]);

        return new EventResource($event->load('rsvps'));
    }
}
