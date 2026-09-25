<?php

namespace Database\Factories;

use App\Enums\Audience;
use App\Models\Community;
use App\Models\ConsentForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentForm>
 */
class ConsentFormFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => 'Consent to electronic notices',
            'body' => 'I agree to receive notices from the corporation electronically.',
            'audience' => Audience::Owners,
            'published_at' => now(),
        ];
    }
}
