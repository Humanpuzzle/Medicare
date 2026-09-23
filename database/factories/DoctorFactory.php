<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dr. '.fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'specialty' => fake()->randomElement([
                'Cardiology',
                'Neurology',
                'Dermatology',
                'Orthopedics',
                'Pediatrics',
                'Psychiatry',
                'Radiology',
                'Anesthesiology',
            ]),
        ];
    }
}
