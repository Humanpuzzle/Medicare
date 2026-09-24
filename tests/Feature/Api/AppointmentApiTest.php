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

final class AppointmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_appointments_collection(): void
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

        $response = $this->getJson('/api/v1/appointments');

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

    public function test_get_single_appointment(): void
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

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $response = $this->getJson("/api/v1/appointments/{$appointment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $appointment->id,
                    'doctor_id' => $doctor->id,
                    'patient_id' => $patient->id,
                    'status' => 'pending',
                ],
            ]);
    }

    public function test_create_appointment(): void
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

        $response = $this->postJson('/api/v1/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0)->toIso8601String(),
            'end_time' => $startsAt->addHours(1)->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);
    }

    public function test_create_appointment_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/appointments', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_appointment_business_conflict_returns_409(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        // Create first appointment
        $this->postJson('/api/v1/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient1->id,
            'start_time' => $startsAt->setTime(9, 0, 0)->toIso8601String(),
            'end_time' => $startsAt->addHours(1)->toIso8601String(),
        ])->assertStatus(201);

        // Try to create overlapping appointment for same doctor (9:30-10:00 overlaps with 9:00-10:00)
        $response = $this->postJson('/api/v1/appointments', [
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'start_time' => $startsAt->setTime(9, 30, 0)->toIso8601String(),
            'end_time' => $startsAt->setTime(10, 0, 0)->toIso8601String(),
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_invalid_status_transition_returns_409(): void
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

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
            'status' => AppointmentStatus::Completed,
        ]);

        // Try to transition from completed to pending (invalid)
        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => 'pending',
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_valid_status_transition_pending_to_confirmed(): void
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

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
            'status' => AppointmentStatus::Pending,
        ]);

        $response = $this->patchJson("/api/v1/appointments/{$appointment->id}/status", [
            'status' => 'confirmed',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'confirmed']);
    }

    public function test_cancellation_endpoint(): void
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

        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
            'status' => AppointmentStatus::Pending,
        ]);

        $response = $this->postJson("/api/v1/appointments/{$appointment->id}/cancel", [
            'cancellation_reason' => 'Patient requested',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'cancelled',
                'cancellation_reason' => 'Patient requested',
            ]);
    }

    public function test_24_hour_cancellation_rule_exposed_via_http(): void
    {
        $this->markTestIncomplete('Requires time manipulation for 24-hour boundary test');
    }

    public function test_404_for_unknown_appointment(): void
    {
        $response = $this->getJson('/api/v1/appointments/99999');

        $response->assertStatus(404);
    }

    public function test_filter_by_doctor_id(): void
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability1 = Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);
        $availability2 = Availability::factory()->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor1->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);
        Appointment::factory()->create([
            'doctor_id' => $doctor2->id,
            'patient_id' => $patient->id,
            'start_time' => $startsAt->setTime(10, 0, 0),
            'end_time' => $startsAt->addHours(2),
        ]);

        $response = $this->getJson("/api/v1/appointments?doctor_id={$doctor1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($doctor1->id, $response->json('data.0.doctor_id'));
    }

    public function test_filter_by_patient_id(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $patient2 = Patient::factory()->create();
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
            'patient_id' => $patient1->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient2->id,
            'start_time' => $startsAt->setTime(10, 0, 0),
            'end_time' => $startsAt->addHours(2),
        ]);

        $response = $this->getJson("/api/v1/appointments?patient_id={$patient1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($patient1->id, $response->json('data.0.patient_id'));
    }

    public function test_filter_by_status(): void
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

        $response = $this->getJson('/api/v1/appointments?status=confirmed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('confirmed', $response->json('data.0.status'));
    }

    public function test_pagination(): void
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

        $response = $this->getJson('/api/v1/appointments');

        $response->assertStatus(200);
        $this->assertCount(25, $response->json('data'));
    }

    public function test_pagination_rejects_per_page_over_100(): void
    {
        $response = $this->getJson('/api/v1/appointments?per_page=101');

        $response->assertStatus(422);
    }
}
