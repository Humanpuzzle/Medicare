<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Availability;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Availability>
 */
class AvailabilityFactory extends Factory
{
    protected $model = Availability::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+30 days');
        $durationMinutes = fake()->randomElement([60, 90, 120, 180]);
        $endsAt = (clone $startsAt)->modify("+{$durationMinutes} minutes");

        return [
            'doctor_id' => Doctor::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }
}
