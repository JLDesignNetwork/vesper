<?php

namespace Database\Factories;

use App\Enums\ReportOrigin;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'subject_user_id' => User::factory(),
            'origin' => ReportOrigin::Direct,
            'reason' => ReportReason::Spam,
            'details' => null,
            'status' => ReportStatus::Open,
        ];
    }
}
