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

final class AvailableSlotsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_doctor_available_slots(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['doctor_id', 'start_time', 'is_available'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_available_slots_with_date_range(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $from = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $to = $from->addHours(4);

        $fromStr = $from->format('Y-m-d\TH:i:s\Z');
        $toStr = $to->format('Y-m-d\TH:i:s\Z');

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots?from={$fromStr}&to={$toStr}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['doctor_id', 'start_time', 'is_available'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_available_slots_defaults_to_30_days(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200);
    }

    public function test_404_for_unknown_doctor(): void
    {
        $response = $this->getJson('/api/v1/doctors/99999/available-slots');

        $response->assertStatus(404);
    }

    public function test_pagination(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(8);

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots?per_page=5");

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_occupied_start_time_is_excluded(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC');
        $endsAt = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200);
        $data = $response->json('data');

        $slot0900 = collect($data)->firstWhere('start_time', '2026-10-15T09:00:00+00:00');
        $slot0915 = collect($data)->firstWhere('start_time', '2026-10-15T09:15:00+00:00');
        $slot0930 = collect($data)->firstWhere('start_time', '2026-10-15T09:30:00+00:00');
        $slot0945 = collect($data)->firstWhere('start_time', '2026-10-15T09:45:00+00:00');
        $slot1000 = collect($data)->firstWhere('start_time', '2026-10-15T10:00:00+00:00');

        $this->assertNotNull($slot0900);
        $this->assertTrue($slot0900['is_available']);
        $this->assertFalse($slot0915['is_available']);
        $this->assertFalse($slot0930['is_available']);
        $this->assertFalse($slot0945['is_available']);
        $this->assertTrue($slot1000['is_available']);
    }

    public function test_cancelled_appointment_does_not_block(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC');
        $endsAt = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $slot) {
            $this->assertTrue($slot['is_available'], "Slot {$slot['start_time']} should be available");
        }
    }

    public function test_completed_appointment_does_not_block(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $startsAt = CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC');
        $endsAt = CarbonImmutable::parse('2026-10-15 11:00:00', 'UTC');

        Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'start_time' => CarbonImmutable::parse('2026-10-15 09:30:00', 'UTC'),
            'end_time' => CarbonImmutable::parse('2026-10-15 10:00:00', 'UTC'),
            'status' => AppointmentStatus::Completed,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $slot) {
            $this->assertTrue($slot['is_available'], "Slot {$slot['start_time']} should be available");
        }
    }
}
