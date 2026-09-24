<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DoctorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_doctors_collection(): void
    {
        Doctor::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/doctors');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'specialty', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_single_doctor(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->getJson("/api/v1/doctors/{$doctor->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'email' => $doctor->email,
                    'specialty' => $doctor->specialty,
                ],
            ]);
    }

    public function test_create_doctor(): void
    {
        $data = [
            'name' => 'Dr. Jane Smith',
            'email' => 'jane.smith@clinic.com',
            'specialty' => 'Cardiology',
        ];

        $response = $this->postJson('/api/v1/doctors', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'Dr. Jane Smith',
                'email' => 'jane.smith@clinic.com',
                'specialty' => 'Cardiology',
            ]);

        $this->assertDatabaseHas('doctors', ['email' => 'jane.smith@clinic.com']);
    }

    public function test_create_doctor_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/doctors', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_doctor_duplicate_email_returns_422(): void
    {
        Doctor::factory()->create(['email' => 'existing@clinic.com']);

        $response = $this->postJson('/api/v1/doctors', [
            'name' => 'Dr. New',
            'email' => 'existing@clinic.com',
            'specialty' => 'Neurology',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_doctor(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->putJson("/api/v1/doctors/{$doctor->id}", [
            'name' => 'Dr. Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Dr. Updated Name']);
    }

    public function test_delete_doctor(): void
    {
        $doctor = Doctor::factory()->create();

        $response = $this->deleteJson("/api/v1/doctors/{$doctor->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('doctors', ['id' => $doctor->id]);
    }

    public function test_404_for_unknown_doctor(): void
    {
        $response = $this->getJson('/api/v1/doctors/99999');

        $response->assertStatus(404);
    }

    public function test_pagination_default_per_page(): void
    {
        Doctor::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/doctors');

        $response->assertStatus(200);
        $this->assertCount(25, $response->json('data'));
    }

    public function test_pagination_custom_per_page(): void
    {
        Doctor::factory()->count(50)->create();

        $response = $this->getJson('/api/v1/doctors?per_page=50');

        $response->assertStatus(200);
        $this->assertCount(50, $response->json('data'));
    }

    public function test_pagination_rejects_per_page_over_100(): void
    {
        $response = $this->getJson('/api/v1/doctors?per_page=101');

        $response->assertStatus(422);
    }
}
