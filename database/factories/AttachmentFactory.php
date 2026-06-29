<?php

namespace Database\Factories;

use App\Enums\AttachmentCategory;
use App\Models\Asset;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Attachment> */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        $original = fake()->word().'.pdf';

        return [
            'attachable_type' => Asset::class,
            'attachable_id' => Asset::factory(),
            'path' => 'attachments/'.fake()->uuid().'.pdf',
            'original_name' => $original,
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5_000_000),
            'type' => 'document',
            'category' => AttachmentCategory::DOKUMENT,
            'title' => $original,
            'note' => null,
            'uploaded_by' => null,
        ];
    }
}
