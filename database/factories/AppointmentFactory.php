<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+30 days');
        // Ensure 15-minute grid
        $minute = (int) $startsAt->format('i');
        $minute = (int) (round($minute / 15) * 15) % 60;
        $startsAt->setTime((int) $startsAt->format('H'), $minute, 0);

        $durationMinutes = fake()->randomElement([30, 60, 90, 120]);
        $endsAt = (clone $startsAt)->modify("+{$durationMinutes} minutes");

        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => Doctor::factory(),
            'start_time' => $startsAt,
            'end_time' => $endsAt,
            'status' => AppointmentStatus::Pending,
            'cancellation_reason' => null,
        ];
    }

    /**
     * Indicate that the appointment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Confirmed,
        ]);
    }

    /**
     * Indicate that the appointment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Completed,
        ]);
    }

    /**
     * Indicate that the appointment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
