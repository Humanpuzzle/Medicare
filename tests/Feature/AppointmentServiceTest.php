<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AppointmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AppointmentService;
    }

    public function test_valid_appointment_creation(): void
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

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $endsAt->setTime(11, 0, 0),
        ]);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($patient->id, $appointment->patient_id);
        $this->assertEquals($doctor->id, $appointment->doctor_id);
        $this->assertEquals($startsAt->setTime(9, 0, 0), $appointment->start_time);
        $this->assertEquals($endsAt->setTime(11, 0, 0), $appointment->end_time);
        $this->assertEquals(AppointmentStatus::Pending, $appointment->status);
        $this->assertNull($appointment->cancellation_reason);
    }

    public function test_unknown_doctor_rejected(): void
    {
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => 9999, // Non-existent doctor
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(2),
        ]);
    }

    public function test_unknown_patient_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => 9999, // Non-existent patient
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(2),
        ]);
    }

    public function test_past_appointment_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->subHour();
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHour(),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->addHour(),
            'end_time' => $endsAt->addHour(),
        ]);
    }

    public function test_start_equal_to_end_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->setTime(9, 0, 0),
        ]);
    }

    public function test_end_before_start_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0, 0);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(11, 0, 0),
            'end_time' => $startsAt->setTime(10, 0, 0),
        ]);
    }

    public function test_appointment_shorter_than_30_minutes_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addMinutes(29);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addMinutes(29),
        ]);
    }

    public function test_appointment_not_on_15_minute_grid_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 10, 0); // 9:10 (not on 15-min grid)

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 10, 0), // 9:10 (not on 15-min grid)
            'end_time' => $startsAt->addHour(),
        ]);
    }

    public function test_appointment_not_contained_in_availability_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->subHour()->setTime(8, 0, 0), // Outside availability
            'end_time' => $endsAt->subHour()->setTime(10, 0, 0),
        ]);
    }

    public function test_appointment_duration_not_multiple_of_slot_duration_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addMinutes(45); // 45 minutes, not multiple of 30

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addMinutes(45),
        ]);
    }

    public function test_doctor_conflict_overlap_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment1 = $this->service->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $patient2 = Patient::factory()->create();
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->addMinutes(30)->setTime(9, 30, 0), // Overlaps with existing
            'end_time' => $startsAt->addHours(1, 30),
        ]);
    }

    public function test_doctor_conflict_adjacent_allowed(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment1 = $this->service->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $patient2 = Patient::factory()->create();
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_patient_conflict_overlap_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment1 = $this->service->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $patient2 = Patient::factory()->create();
        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->addMinutes(30)->setTime(9, 30, 0), // Overlaps with patient1's appointment
            'end_time' => $startsAt->addHours(1, 30),
        ]);
    }

    public function test_patient_conflict_adjacent_allowed(): void
    {
        $doctor = Doctor::factory()->create();
        $patient1 = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment1 = $this->service->create([
            'patient_id' => $patient1->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $patient2 = Patient::factory()->create();
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_pending_to_confirmed_transition(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $this->assertEquals(AppointmentStatus::Pending, $appointment->status);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        $this->assertEquals(AppointmentStatus::Confirmed, $confirmed->status);
        $this->assertNull($confirmed->cancellation_reason);
    }

    public function test_pending_to_cancelled_transition(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $cancelled = $this->service->updateStatus($appointment, AppointmentStatus::Cancelled);

        $this->assertEquals(AppointmentStatus::Cancelled, $cancelled->status);
        $this->assertNull($cancelled->cancellation_reason);
    }

    public function test_confirmed_to_completed_transition(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        $completed = $this->service->updateStatus($confirmed, AppointmentStatus::Completed);

        $this->assertEquals(AppointmentStatus::Completed, $completed->status);
        $this->assertNull($completed->cancellation_reason);
    }

    public function test_confirmed_to_cancelled_transition_within_24_hours(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        // Appointment starts in 12 hours (2026-10-15 22:00)
        $startsAt = CarbonImmutable::create(2026, 10, 15, 22, 0, 0, 'UTC');
        $endsAt = $startsAt->addHour();

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 60,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        // Now try to cancel - should fail because it's less than 24 hours before start
        // Current time is 2026-10-15 10:00, appointment starts 2026-10-15 22:00 = 12 hours
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 24 hours before its start time');

        $this->service->updateStatus($confirmed, AppointmentStatus::Cancelled);
    }

    public function test_confirmed_to_cancelled_transition_exactly_24_hours(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        // Appointment starts in 25 hours (2026-10-16 11:00)
        $startsAt = CarbonImmutable::create(2026, 10, 16, 11, 0, 0, 'UTC');
        $endsAt = $startsAt->addHour();

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 60,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        // Move time forward by 1 hour (now 2026-10-15 11:00)
        // Appointment starts 2026-10-16 11:00 = exactly 24 hours from now
        CarbonImmutable::setTestNow('2026-10-15 11:00:00');

        $cancelled = $this->service->updateStatus($confirmed, AppointmentStatus::Cancelled);

        $this->assertEquals(AppointmentStatus::Cancelled, $cancelled->status);
        $this->assertNull($cancelled->cancellation_reason);
    }

    public function test_confirmed_to_cancelled_transition_exactly_24_hours_with_reason(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::create(2026, 10, 16, 11, 0, 0, 'UTC');
        $endsAt = $startsAt->addHour();

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 60,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        CarbonImmutable::setTestNow('2026-10-15 11:00:00');

        $cancelled = $this->service->updateStatus($confirmed, AppointmentStatus::Cancelled, 'Patient requested');

        $this->assertEquals(AppointmentStatus::Cancelled, $cancelled->status);
        $this->assertEquals('Patient requested', $cancelled->cancellation_reason);
    }

    public function test_confirmed_to_cancelled_transition_less_than_24_hours_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::create(2026, 10, 15, 22, 0, 0, 'UTC');
        $endsAt = $startsAt->addHour();

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 60,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 24 hours before its start time');

        $this->service->updateStatus($confirmed, AppointmentStatus::Cancelled);
    }

    public function test_invalid_status_transitions_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);
        $completed = $this->service->updateStatus($confirmed, AppointmentStatus::Completed);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus($completed, AppointmentStatus::Pending);
    }

    public function test_cancelled_appointment_cannot_change_status(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $cancelled = $this->service->updateStatus($appointment, AppointmentStatus::Cancelled);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus($cancelled, AppointmentStatus::Confirmed);
    }

    public function test_completed_appointment_cannot_change_status(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);
        $completed = $this->service->updateStatus($confirmed, AppointmentStatus::Completed);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->updateStatus($completed, AppointmentStatus::Cancelled);
    }

    public function test_cancellation_reason_only_for_cancelled(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');

        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::create(2026, 10, 16, 11, 0, 0, 'UTC');
        $endsAt = $startsAt->addHour();

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 60,
        ]);

        $appointment = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        // Transition to confirmed - cancellation_reason should be null
        $confirmed = $this->service->updateStatus($appointment, AppointmentStatus::Confirmed);
        $this->assertNull($confirmed->cancellation_reason);

        // Transition to completed - cancellation_reason should be null
        $completed = $this->service->updateStatus($confirmed, AppointmentStatus::Completed);
        $this->assertNull($completed->cancellation_reason);

        // Create another appointment and cancel with reason
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');
        $patient2 = Patient::factory()->create();
        $appointment2 = $this->service->create([
            'patient_id' => $patient2->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $confirmed2 = $this->service->updateStatus($appointment2, AppointmentStatus::Confirmed);
        CarbonImmutable::setTestNow('2026-10-15 11:00:00'); // 24 hours before

        $cancelled = $this->service->updateStatus($confirmed2, AppointmentStatus::Cancelled, 'Patient cancelled');
        $this->assertEquals('Patient cancelled', $cancelled->cancellation_reason);

        // Cancel pending appointment with reason
        CarbonImmutable::setTestNow('2026-10-15 10:00:00');
        $patient3 = Patient::factory()->create();
        $appointment3 = $this->service->create([
            'patient_id' => $patient3->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt,
            'end_time' => $endsAt,
        ]);

        $cancelledPending = $this->service->updateStatus($appointment3, AppointmentStatus::Cancelled, 'Changed mind');
        $this->assertEquals('Changed mind', $cancelledPending->cancellation_reason);
    }

    public function test_transaction_rollback_on_failure(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->setTime(9, 0, 0),
            'ends_at' => $startsAt->addHours(2),
            'slot_duration' => 30,
        ]);

        $appointment1 = $this->service->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->setTime(9, 0, 0),
            'end_time' => $startsAt->addHours(1),
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->create([
            'patient_id' => Patient::factory()->create()->id,
            'doctor_id' => $doctor->id,
            'start_time' => $startsAt->addMinutes(30)->setTime(9, 30, 0), // Overlap
            'end_time' => $startsAt->addHours(1, 30),
        ]);

        $this->assertDatabaseCount('appointments', 1);
    }
}
