<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with demo data.
     */
    public function run(AppointmentService $appointmentService): void
    {
        // Use a single base date: 7 days in the future at midnight UTC
        $baseDate = CarbonImmutable::now('UTC')->addDays(7)->startOfDay();

        // Create Doctors
        $doctor1 = Doctor::factory()->create([
            'name' => 'Dr. János Kovács',
            'email' => 'kovacs.janos@medicare.example',
            'specialty' => 'Cardiology',
        ]);

        $doctor2 = Doctor::factory()->create([
            'name' => 'Dr. Eszter Nagy',
            'email' => 'nagy.eszter@medicare.example',
            'specialty' => 'Neurology',
        ]);

        // Create Patients
        $patient1 = Patient::factory()->create([
            'name' => 'Kovács Péter',
            'email' => 'peter.kovacs@example.com',
            'phone' => '+36301234567',
        ]);

        $patient2 = Patient::factory()->create([
            'name' => 'Szabó Anna',
            'email' => 'anna.szabo@example.com',
            'phone' => '+36309876543',
        ]);

        $patient3 = Patient::factory()->create([
            'name' => 'Tóth Gábor',
            'email' => 'gabor.toth@example.com',
            'phone' => '+36204567890',
        ]);

        // Doctor 1: Availability 09:00-12:00
        $availability1 = Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => $baseDate->setTime(9, 0, 0),
            'ends_at' => $baseDate->setTime(12, 0, 0),
        ]);

        // Doctor 1 second availability: 12:00-15:00 (adjacent)
        $availability2 = Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => $baseDate->setTime(12, 0, 0),
            'ends_at' => $baseDate->setTime(15, 0, 0),
        ]);

        // Doctor 2: Overlapping availability with Doctor 1 (10:00-14:00)
        $availability3 = Availability::factory()->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => $baseDate->setTime(10, 0, 0),
            'ends_at' => $baseDate->setTime(14, 0, 0),
        ]);

        // Doctor 1 appointments (within 09:00-12:00 availability)
        // 09:00-09:45 (45 min)
        $appointment1 = $appointmentService->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor1->id,
            'start_time' => $baseDate->setTime(9, 0, 0),
            'end_time' => $baseDate->setTime(9, 45, 0),
        ]);

        // 10:00-11:00 (60 min)
        $appointment2 = $appointmentService->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor1->id,
            'start_time' => $baseDate->setTime(10, 0, 0),
            'end_time' => $baseDate->setTime(11, 0, 0),
        ]);

        // 11:15-11:45 (30 min)
        $appointment3 = $appointmentService->create([
            'patient_id' => $patient3->id,
            'doctor_id' => $doctor1->id,
            'start_time' => $baseDate->setTime(11, 15, 0),
            'end_time' => $baseDate->setTime(11, 45, 0),
        ]);

        // Doctor 2 appointments (within 10:00-14:00 availability)
        // 10:15-11:15 (60 min)
        $appointment4 = $appointmentService->create([
            'patient_id' => $patient3->id,  // Changed from patient2 to patient3 to avoid patient conflict
            'doctor_id' => $doctor2->id,
            'start_time' => $baseDate->setTime(10, 15, 0),
            'end_time' => $baseDate->setTime(11, 15, 0),
        ]);

        // Patient 1 with Doctor 2: 12:00-13:00 (60 min) - same patient different doctor
        $appointment4 = $appointmentService->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor2->id,
            'start_time' => $baseDate->setTime(12, 0, 0),
            'end_time' => $baseDate->setTime(13, 0, 0),
        ]);

        // Create some confirmed appointments to demonstrate slot blocking
        $appointment2->update(['status' => AppointmentStatus::Confirmed]);

        // Create a completed appointment
        $appointment5 = $appointmentService->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor2->id,
            'start_time' => $baseDate->setTime(13, 0, 0),
            'end_time' => $baseDate->setTime(14, 0, 0),
        ]);
        $appointment5->update(['status' => AppointmentStatus::Completed]);

        // Create a cancelled appointment
        $appointment6 = $appointmentService->create([
            'patient_id' => $patient3->id,
            'doctor_id' => $doctor1->id,
            'start_time' => $baseDate->setTime(13, 0, 0),
            'end_time' => $baseDate->setTime(14, 0, 0),
        ]);
        $appointment6->update([
            'status' => AppointmentStatus::Cancelled,
            'cancellation_reason' => 'Patient could not make it',
        ]);
    }
}
