<?php

namespace Database\Factories;

use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => CompanyRole::Staff->value,
            'community_ids' => [],
            'token_hash' => Invitation::hashToken(Str::random(40)),
            'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
        ];
    }

    /**
     * Use a known link token, so a test can open the invitation.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => ['token_hash' => Invitation::hashToken($token)]);
    }

    public function forResident(Resident $resident): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => $resident->company_id,
            'resident_id' => $resident->id,
            'name' => $resident->name,
            'email' => $resident->email,
            'role' => null,
            'community_ids' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => ['expires_at' => now()->subMinute()]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => ['accepted_at' => now()->subHour()]);
    }
}
