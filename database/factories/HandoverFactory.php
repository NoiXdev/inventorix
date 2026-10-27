<?php

namespace Database\Factories;

use App\Enums\HandoverType;
use App\Enums\RecipientKind;
use App\Models\Handover;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Handover> */
class HandoverFactory extends Factory
{
    protected $model = Handover::class;

    public function definition(): array
    {
        return [
            'type' => HandoverType::ISSUE->value,
            'recipient_kind' => RecipientKind::INTERNAL->value,
            'recipient_user_id' => User::factory(),
            'recipient_name' => fake()->name(),
            'recipient_email' => fake()->safeEmail(),
            'accessories' => null,
            'condition_notes' => null,
            'terms_text' => config('handover.terms'),
            'signature_path' => 'handovers/test/signature.png',
            'signature_ip' => '127.0.0.1',
            'signature_user_agent' => 'phpunit',
            'pdf_path' => null,
            'signed_at' => now(),
            'created_by' => User::factory(),
            'email_sent_at' => null,
        ];
    }
}
