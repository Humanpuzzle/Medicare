<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Availability;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_availabilities_collection(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        Availability::factory()->count(3)->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $response = $this->getJson('/api/v1/availabilities');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'doctor_id', 'starts_at', 'ends_at', 'slot_duration', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_single_availability(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $response = $this->getJson("/api/v1/availabilities/{$availability->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $availability->id,
                    'doctor_id' => $doctor->id,
                    'slot_duration' => 30,
                ],
            ]);
    }

    public function test_create_availability(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $response = $this->postJson('/api/v1/availabilities', [
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'slot_duration' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'doctor_id' => $doctor->id,
                'slot_duration' => 30,
            ]);

        $this->assertDatabaseHas('availabilities', ['doctor_id' => $doctor->id]);
    }

    public function test_create_availability_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/availabilities', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_availability_business_conflict_returns_409(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        // Create first availability
        $this->postJson('/api/v1/availabilities', [
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'slot_duration' => 30,
        ])->assertStatus(201);

        // Try to create overlapping availability
        $response = $this->postJson('/api/v1/availabilities', [
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt->addMinutes(30)->toIso8601String(),
            'ends_at' => $endsAt->addMinutes(30)->toIso8601String(),
            'slot_duration' => 30,
        ]);

        $response->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_update_availability(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $newEndsAt = $startsAt->addHours(3);

        $response = $this->putJson("/api/v1/availabilities/{$availability->id}", [
            'ends_at' => $newEndsAt->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'ends_at' => $newEndsAt->toIso8601String(),
                ],
            ]);
    }

    public function test_delete_availability(): void
    {
        $doctor = Doctor::factory()->create();
        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        $availability = Availability::factory()->create([
            'doctor_id' => $doctor->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $response = $this->deleteJson("/api/v1/availabilities/{$availability->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('availabilities', ['id' => $availability->id]);
    }

    public function test_404_for_unknown_availability(): void
    {
        $response = $this->getJson('/api/v1/availabilities/99999');

        $response->assertStatus(404);
    }

    public function test_filter_by_doctor_id(): void
    {
        $doctor1 = Doctor::factory()->create();
        $doctor2 = Doctor::factory()->create();

        $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(9, 0, 0);
        $endsAt = $startsAt->addHours(2);

        Availability::factory()->create([
            'doctor_id' => $doctor1->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);
        Availability::factory()->create([
            'doctor_id' => $doctor2->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'slot_duration' => 30,
        ]);

        $response = $this->getJson("/api/v1/availabilities?doctor_id={$doctor1->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($doctor1->id, $response->json('data.0.doctor_id'));
    }
}
