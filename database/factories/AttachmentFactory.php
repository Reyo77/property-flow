<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'disk_path' => 'attachments/'.fake()->uuid().'.jpg',
            'original_filename' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1000, 5_000_000),
        ];
    }
}
