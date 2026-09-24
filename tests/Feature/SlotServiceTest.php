<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    private SlotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlotService;
    }

    public function test_normal_start_time_generation(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $slots = $this->service->generateStartTimesFromAvailability($availability);

        $this->assertCount(7, $slots);
        $this->assertEquals('2026-10-15T09:00:00+00:00', $slots[0]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T09:15:00+00:00', $slots[1]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T09:30:00+00:00', $slots[2]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T09:45:00+00:00', $slots[3]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:00:00+00:00', $slots[4]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:15:00+00:00', $slots[5]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:30:00+00:00', $slots[6]->startsAt->toIso8601String());
    }

    public function test_first_start_time_matches_availability_start(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:15:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 10:15:00', 'UTC'),
        ]);

        $slots = $this->service->generateStartTimesFromAvailability($availability);

        $this->assertEquals('2026-10-15T09:15:00+00:00', $slots[0]->startsAt->toIso8601String());
    }

    public function test_no_start_time_at_availability_end(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
        ]);

        $slots = $this->service->generateStartTimesFromAvailability($availability);

        $this->assertCount(3, $slots);
        $lastSlot = $slots[count($slots) - 1];
        $this->assertEquals('2026-10-15T09:30:00+00:00', $lastSlot->startsAt->toIso8601String());
    }

    public function test_utc_datetime_correctness(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $slots = $this->service->generateStartTimesFromAvailability($availability);

        foreach ($slots as $slot) {
            $this->assertEquals('UTC', $slot->startsAt->getTimezone()->getName());
        }
    }

    public function test_occupied_start_time_is_excluded(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots);
        $this->assertTrue($slots[0]->isAvailable);   // 09:00
        $this->assertFalse($slots[1]->isAvailable);  // 09:15
        $this->assertFalse($slots[2]->isAvailable);  // 09:30
        $this->assertFalse($slots[3]->isAvailable);  // 09:45
        $this->assertTrue($slots[4]->isAvailable);   // 10:00
        $this->assertTrue($slots[5]->isAvailable);   // 10:15
        $this->assertTrue($slots[6]->isAvailable);   // 10:30
    }

    public function test_free_start_time_remains_available(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 14:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots);
        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_adjacent_appointment_does_not_block_start_time(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 08:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots);
        $this->assertTrue($slots[0]->isAvailable);
    }

    public function test_appointment_starting_when_proposed_ends_does_not_block(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertTrue($slots[2]->isAvailable);
    }

    public function test_only_pending_and_confirmed_appointments_block_start_times(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => Patient::factory()->create()->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Completed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots);
        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_doctor_filtering_works(): void
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();

        Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        Availability::factory()->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor1->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots);

        $slots2 = $this->service->getAvailableSlots([
            'doctor_id' => $doctor2->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(7, $slots2);
    }

    public function test_date_range_filtering(): void
    {
        $doctor = Doctor::factory()->create();
        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 17:00:00', 'UTC'),
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 13:00:00', 'UTC'),
        ]);

        $this->assertCount(15, $slots);
    }

    public function test_get_available_start_times_for_doctor_on_date(): void
    {
        $doctor = Doctor::factory()->create();
        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $slots = $this->service->getAvailableSlotsForDoctorOnDate(
            $doctor->id,
            CarbonImmutable::parse('2026-10-15', 'UTC')
        );

        $this->assertCount(7, $slots);
    }
}
