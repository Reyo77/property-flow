<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'company_id' => fn (array $attributes) => Document::withoutGlobalScopes()->whereKey($attributes['document_id'])->valueOrFail('company_id'),
            'version_number' => 1,
            'disk_path' => 'documents/'.fake()->uuid().'.pdf',
            'original_filename' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(1000, 500000),
        ];
    }
}
