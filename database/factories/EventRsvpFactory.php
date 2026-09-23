<?php

namespace Database\Factories;

use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRsvp>
 */
class EventRsvpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'company_id' => fn (array $attributes) => Event::withoutGlobalScopes()->whereKey($attributes['event_id'])->valueOrFail('company_id'),
            'user_id' => User::factory(),
            'status' => RsvpStatus::Going,
        ];
    }
}
