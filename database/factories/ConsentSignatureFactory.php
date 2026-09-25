<?php

namespace Database\Factories;

use App\Models\ConsentForm;
use App\Models\ConsentSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentSignature>
 */
class ConsentSignatureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consent_form_id' => ConsentForm::factory(),
            'company_id' => fn (array $attributes) => ConsentForm::withoutGlobalScopes()->whereKey($attributes['consent_form_id'])->valueOrFail('company_id'),
            'user_id' => fn (array $attributes) => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'signed_name' => fake()->name(),
            'signature_disk_path' => 'signatures/test.png',
            'body_hash' => str_repeat('0', 64),
            'signed_at' => now(),
        ];
    }
}
