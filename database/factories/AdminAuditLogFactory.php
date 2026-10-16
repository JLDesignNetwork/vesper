<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Enums\AuditVisibility;
use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
class AdminAuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => AuditAction::RankChanged,
            'visibility' => AuditVisibility::AdminOneOnly,
            'details' => null,
        ];
    }

    /**
     * Indicate that the entry is a moderation entry visible to Admin II.
     */
    public function moderation(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => AuditVisibility::Moderation,
        ]);
    }
}
