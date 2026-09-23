<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use App\Services\SlotService;
use App\Services\SlotService\Slot;
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

    public function test_normal_slot_generation(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $slots = $this->service->generateSlotsFromAvailability($availability);

        $this->assertCount(4, $slots);
        $this->assertEquals('2026-10-15T09:00:00+00:00', $slots[0]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T09:30:00+00:00', $slots[0]->endsAt->toIso8601String());
        $this->assertEquals('2026-10-15T09:30:00+00:00', $slots[1]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:00:00+00:00', $slots[1]->endsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:00:00+00:00', $slots[2]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:30:00+00:00', $slots[2]->endsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:30:00+00:00', $slots[3]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T11:00:00+00:00', $slots[3]->endsAt->toIso8601String());
    }

    public function test_first_slot_matches_availability_start(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:15:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 10:15:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $slots = $this->service->generateSlotsFromAvailability($availability);

        $this->assertEquals('2026-10-15T09:15:00+00:00', $slots[0]->startsAt->toIso8601String());
    }

    public function test_no_slot_at_availability_end(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $slots = $this->service->generateSlotsFromAvailability($availability);

        // Should have 2 slots: 09:00-09:30 and 09:30-10:00
        // No slot at 10:00 (availability end)
        $this->assertCount(2, $slots);
        $lastSlot = $slots[count($slots) - 1];
        $this->assertEquals('2026-10-15T10:00:00+00:00', $lastSlot->endsAt->toIso8601String());
        $this->assertNotEquals('2026-10-15T10:00:00+00:00', $lastSlot->startsAt->toIso8601String());
    }

    public function test_slot_duration_handling(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 60, // 60 minutes
        ]);

        $slots = $this->service->generateSlotsFromAvailability($availability);

        // 2 hours / 60 minutes = 2 slots
        $this->assertCount(2, $slots);
        $this->assertEquals(60, $slots[0]->slotDuration);
        $this->assertEquals('2026-10-15T09:00:00+00:00', $slots[0]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:00:00+00:00', $slots[0]->endsAt->toIso8601String());
        $this->assertEquals('2026-10-15T10:00:00+00:00', $slots[1]->startsAt->toIso8601String());
        $this->assertEquals('2026-10-15T11:00:00+00:00', $slots[1]->endsAt->toIso8601String());
    }

    public function test_correct_number_of_generated_starts(): void
    {
        $doctor = Doctor::factory()->create();

        // 30 min slots, 2 hours = 4 slots
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);
        $slots = $this->service->generateSlotsFromAvailability($availability);
        $this->assertCount(4, $slots);

        // 45 min slots, 3 hours = 4 slots (9:00-9:45, 9:45-10:30, 10:30-11:15, 11:15-12:00)
        $availability2 = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC'),
            'slot_duration' => 45,
        ]);
        $slots2 = $this->service->generateSlotsFromAvailability($availability2);
        $this->assertCount(4, $slots2);
    }

    public function test_utc_datetime_correctness(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $slots = $this->service->generateSlotsFromAvailability($availability);

        foreach ($slots as $slot) {
            $this->assertEquals('UTC', $slot->startsAt->getTimezone()->getName());
            $this->assertEquals('UTC', $slot->endsAt->getTimezone()->getName());
        }
    }

    public function test_occupied_slot_is_excluded(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Create an appointment that blocks the 09:30-10:00 slot
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots);
        // 09:00 slot should be available
        $this->assertTrue($slots[0]->isAvailable);
        // 09:30 slot should NOT be available (blocked by appointment)
        $this->assertFalse($slots[1]->isAvailable);
        // 10:00 slot should be available
        $this->assertTrue($slots[2]->isAvailable);
        // 10:30 slot should be available
        $this->assertTrue($slots[3]->isAvailable);
    }

    public function test_free_slot_remains_available(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Create an appointment that does NOT block any slot (outside availability)
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 14:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 15:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots);
        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_adjacent_appointment_does_not_block_slot(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Appointment ends exactly when slot starts (adjacent - should NOT block)
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 08:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots);
        // 09:00 slot should be available (adjacent appointment ends at 09:00)
        $this->assertTrue($slots[0]->isAvailable);
    }

    public function test_appointment_starting_when_slot_ends_does_not_block(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Appointment starts exactly when slot ends (adjacent - should NOT block 09:30 slot)
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        // 09:30 slot should be available (appointment starts at 10:00, slot ends at 10:00)
        $this->assertTrue($slots[1]->isAvailable);
    }

    public function test_only_pending_and_confirmed_appointments_block_slots(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Cancelled appointment should NOT block
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled,
        ]);

        // Completed appointment should NOT block
        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Completed,
        ]);

        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots);
        // All slots should be available since cancelled/completed don't block
        foreach ($slots as $slot) {
            $this->assertTrue($slot->isAvailable);
        }
    }

    public function test_doctor_filtering_works(): void
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();
        $patient = Patient::factory()->create();

        $availability1 = Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $availability2 = Availability::factory()->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        // Get slots for doctor1 only
        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor1->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots);

        // Get slots for doctor2 only
        $slots2 = $this->service->getAvailableSlots([
            'doctor_id' => $doctor2->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots2);
    }

    public function test_date_range_filtering(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 17:00:00', 'UTC'), // 8 hours
            'slot_duration' => 60, // 60 min slots = 8 slots
        ]);

        // Request only morning (09:00-13:00)
        $slots = $this->service->getAvailableSlots([
            'doctor_id' => $doctor->id,
            'from' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'to' => CarbonImmutable::parse('2026-10-15 13:00:00', 'UTC'),
        ]);

        $this->assertCount(4, $slots); // 4 hours / 60 min = 4 slots
    }

    public function test_get_available_slots_for_doctor_on_date(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC'),
            'slot_duration' => 30,
        ]);

        $slots = $this->service->getAvailableSlotsForDoctorOnDate(
            $doctor->id,
            CarbonImmutable::parse('2026-10-15', 'UTC')
        );

        $this->assertCount(4, $slots);
    }
}
