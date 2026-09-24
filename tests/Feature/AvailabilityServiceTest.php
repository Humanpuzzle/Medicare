<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Availability;
use App\Models\Doctor;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AvailabilityService;
    }

    public function test_valid_availability_can_be_created(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->assertInstanceOf(Availability::class, $availability);
        $this->assertEquals($doctor->id, $availability->doctor_id);
        $this->assertEquals($startsAt, $availability->starts_at);
        $this->assertEquals($endsAt, $availability->ends_at);
    }

    public function test_past_availability_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->subHour();
        $endsAt = $startsAt->addHours(2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability must be in the future.');

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_equal_start_and_end_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt; // Same time

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability must start before it ends.');

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0, 0);
        $endsAt = $startsAt->subHour(); // End before start

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability must start before it ends.');

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_availability_shorter_than_30_minutes_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addMinutes(29); // 29 minutes

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability must be at least 30 minutes long.');

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    public function test_availability_exactly_30_minutes_is_valid(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addMinutes(30); // Exactly 30 minutes

        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->assertInstanceOf(Availability::class, $availability);
    }

    public function test_invalid_slot_duration_is_rejected(): void
    {
        $this->markTestIncomplete('Variable-duration model: slot_duration no longer exists on availability');
    }

    public function test_valid_slot_duration_is_accepted(): void
    {
        $this->markTestIncomplete('Variable-duration model: slot_duration no longer exists on availability');
    }

    public function test_overlapping_availability_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        // Create first availability
        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Try to create overlapping availability (partial overlap at beginning)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Availability overlaps with an existing availability period for this doctor.');

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->addMinutes(30),
            'ends_at' => $endsAt->addMinutes(30),
        ]);
    }

    public function test_overlapping_availability_at_end_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Overlap at end
        $this->expectException(\InvalidArgumentException::class);
        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subMinutes(30),
            'ends_at' => $endsAt->subMinutes(30),
        ]);
    }

    public function test_new_availability_inside_existing_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(4);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->addHour(),
            'ends_at' => $endsAt->subHour(),
        ]);
    }

    public function test_existing_availability_inside_new_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHour(),
            'ends_at' => $endsAt->addHour(),
        ]);
    }

    public function test_same_start_time_is_rejected(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt, // Same start
            'ends_at' => $endsAt->addHour(),
        ]);
    }

    public function test_adjacent_availability_before_is_accepted(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(10, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Adjacent before: ends exactly when existing starts
        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->subHours(2),
            'ends_at' => $startsAt, // Ends exactly at existing start
        ]);

        $this->assertInstanceOf(Availability::class, $availability);
    }

    public function test_adjacent_availability_after_is_accepted(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Adjacent after: starts exactly when existing ends
        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $endsAt, // Starts exactly at existing end
            'ends_at' => $endsAt->addHours(2),
        ]);

        $this->assertInstanceOf(Availability::class, $availability);
    }

    public function test_different_doctors_can_have_overlapping_availability(): void
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $this->service->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Different doctor - should be allowed
        $availability = $this->service->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->assertInstanceOf(Availability::class, $availability);
    }

    public function test_availability_update_respects_overlap_rules(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability1 = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $availability2 = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $endsAt,
            'ends_at' => $endsAt->addHours(2),
        ]);

        // Try to update availability2 to overlap with availability1
        $this->expectException(\InvalidArgumentException::class);
        $this->service->update($availability2, [
            'starts_at' => $startsAt->addHour(),
            'ends_at' => $endsAt->addHour(),
        ]);
    }

    public function test_availability_update_excludes_current_record_from_overlap_check(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        // Update to same times (should not conflict with itself)
        $updated = $this->service->update($availability, [
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->assertEquals($startsAt, $updated->starts_at);
        $this->assertEquals($endsAt, $updated->ends_at);
    }

    public function test_availability_delete_soft_deletes(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $this->service->delete($availability);

        $this->assertSoftDeleted('availabilities', ['id' => $availability->id]);
    }

    public function test_get_by_doctor_returns_ordered_availabilities(): void
    {
        $doctor = Doctor::factory()->create();
        $base = CarbonImmutable::now('UTC')->addDay();

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $base->setTime(14, 0, 0),
            'ends_at' => $base->setTime(16, 0, 0),
        ]);

        $this->service->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $base->setTime(9, 0, 0),
            'ends_at' => $base->setTime(11, 0, 0),
        ]);

        $availabilities = $this->service->getByDoctor($doctor->id);

        $this->assertCount(2, $availabilities);
        $this->assertTrue($availabilities[0]->starts_at->lt($availabilities[1]->starts_at));
    }
}
