<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NestedAppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_doctor_appointments(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->count(3)->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/appointments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'patient_id', 'doctor_id', 'start_time', 'end_time', 'status', 'cancellation_reason', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_patient_appointments(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->count(3)->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $response = $this->getJson("/api/v1/patients/{$patient->id}/appointments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'patient_id', 'doctor_id', 'start_time', 'end_time', 'status', 'cancellation_reason', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_doctor_appointments_filter_by_status(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
            'status' => AppointmentStatus::Pending,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(10, 0, 0),
            'end_time' => $startsAt->addHours(2),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/appointments?status=confirmed");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
    }

    public function test_patient_appointments_filter_by_status(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
            'status' => AppointmentStatus::Pending,
        ]);
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(10, 0, 0),
            'end_time' => $startsAt->addHours(2),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson("/api/v1/patients/{$patient->id}/appointments?status=confirmed");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
    }

    public function test_404_for_unknown_doctor_appointments(): void
    {
        $response = $this->getJson('/api/v1/doctors/99999/appointments');

        $response->assertStatus(404);
    }

    public function test_404_for_unknown_patient_appointments(): void
    {
        $response = $this->getJson('/api/v1/patients/99999/appointments');

        $response->assertStatus(404);
    }

    public function test_pagination_on_nested_endpoints(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->count(30)->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/appointments");

        $response->assertStatus(200);
        $this->assertCount(25, $response->json('data'));
    }
}
