<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Availability;
use App\Models\Doctor;
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
            'slot_duration' => 30,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['doctor_id', 'availability_id', 'start_time', 'end_time'],
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
            'slot_duration' => 30,
        ]);

        $from = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $to = $from->addHours(4);

        // Use Z suffix to avoid + being decoded as space in query string
        $fromStr = $from->format('Y-m-d\TH:i:s\Z');
        $toStr = $to->format('Y-m-d\TH:i:s\Z');

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots?from={$fromStr}&to={$toStr}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['doctor_id', 'availability_id', 'start_time', 'end_time'],
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
            'slot_duration' => 30,
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
            'slot_duration' => 30,
        ]);

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}/available-slots?per_page=5");

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }
}
