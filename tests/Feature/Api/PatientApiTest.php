<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_patients_collection(): void
    {
        Patient::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'phone', 'created_at', 'updated_at'],
                ],
                'links',
                'meta',
            ]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_single_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->getJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'email' => $patient->email,
                    'phone' => $patient->phone,
                ],
            ]);
    }

    public function test_create_patient(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1-555-1234',
        ];

        $response = $this->postJson('/api/v1/patients', $data);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'phone' => '+1-555-1234',
            ]);

        $this->assertDatabaseHas('patients', ['email' => 'john.doe@example.com']);
    }

    public function test_create_patient_validation_fails(): void
    {
        $response = $this->postJson('/api/v1/patients', []);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_create_patient_duplicate_email_returns_422(): void
    {
        Patient::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/v1/patients', [
            'name' => 'New Patient',
            'email' => 'existing@example.com',
            'phone' => '+1-555-0000',
        ]);

        $response->assertStatus(422);
    }

    public function test_update_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->putJson("/api/v1/patients/{$patient->id}", [
            'phone' => '+1-555-9999',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['phone' => '+1-555-9999']);
    }

    public function test_delete_patient(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->deleteJson("/api/v1/patients/{$patient->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_404_for_unknown_patient(): void
    {
        $response = $this->getJson('/api/v1/patients/99999');

        $response->assertStatus(404);
    }

    public function test_pagination_default_per_page(): void
    {
        Patient::factory()->count(30)->create();

        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(200);
        $this->assertCount(25, $response->json('data'));
    }

    public function test_pagination_rejects_per_page_over_100(): void
    {
        $response = $this->getJson('/api/v1/patients?per_page=101');

        $response->assertStatus(422);
    }
}
