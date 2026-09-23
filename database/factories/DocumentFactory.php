<?php

namespace Database\Factories;

use App\Enums\DocumentVisibility;
use App\Models\Community;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'company_id' => fn (array $attributes) => Community::withoutGlobalScopes()->whereKey($attributes['community_id'])->valueOrFail('company_id'),
            'title' => fake()->sentence(3),
            'visibility' => DocumentVisibility::Residents,
        ];
    }

    /**
     * Give the document a first version, as the upload flow always does.
     */
    public function withVersion(): static
    {
        return $this->afterCreating(function (Document $document): void {
            $version = DocumentVersion::factory()->for($document)->create([
                'version_number' => 1,
                'original_filename' => 'document.pdf',
                'mime_type' => 'application/pdf',
            ]);

            $document->forceFill(['current_version_id' => $version->id])->save();
        });
    }
}
