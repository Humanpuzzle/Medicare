<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_be_created_with_normalized_email(): void
    {
        $doctor = Doctor::factory()->create([
            'email' => 'John@Example.COM',
        ]);

        $this->assertEquals('john@example.com', $doctor->email);
        $this->assertDatabaseHas('doctors', [
            'email' => 'john@example.com',
        ]);
    }

    public function test_patient_can_be_created_with_normalized_email(): void
    {
        $patient = Patient::factory()->create([
            'email' => 'Jane@Example.COM',
        ]);

        $this->assertEquals('jane@example.com', $patient->email);
        $this->assertDatabaseHas('patients', [
            'email' => 'jane@example.com',
        ]);
    }

    public function test_doctor_has_availabilities_relationship(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create(['doctor_id' => $doctor->id]);

        $this->assertEquals($doctor->id, $availability->doctor->id);
        $this->assertCount(1, $doctor->availabilities);
    }

    public function test_doctor_has_appointments_relationship(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertEquals($doctor->id, $appointment->doctor->id);
        $this->assertCount(1, $doctor->appointments);
    }

    public function test_patient_has_appointments_relationship(): void
    {
        $patient = Patient::factory()->create();
        $doctor = Doctor::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertEquals($patient->id, $appointment->patient->id);
        $this->assertCount(1, $patient->appointments);
    }

    public function test_availability_belongs_to_doctor(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create(['doctor_id' => $doctor->id]);

        $this->assertInstanceOf(Doctor::class, $availability->doctor);
        $this->assertEquals($doctor->id, $availability->doctor->id);
    }

    public function test_appointment_belongs_to_doctor_and_patient(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertInstanceOf(Doctor::class, $appointment->doctor);
        $this->assertInstanceOf(Patient::class, $appointment->patient);
        $this->assertEquals($doctor->id, $appointment->doctor->id);
        $this->assertEquals($patient->id, $appointment->patient->id);
    }

    public function test_appointment_status_cast_works(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->assertInstanceOf(AppointmentStatus::class, $appointment->status);
        $this->assertEquals(AppointmentStatus::Confirmed, $appointment->status);
        $this->assertEquals('confirmed', $appointment->status->value);
    }

    public function test_appointment_datetime_cast_works(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);

        $this->assertInstanceOf(Carbon::class, $appointment->start_time);
        $this->assertInstanceOf(Carbon::class, $appointment->end_time);
    }

    public function test_availability_datetime_cast_works(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create(['doctor_id' => $doctor->id]);

        $this->assertInstanceOf(Carbon::class, $availability->starts_at);
        $this->assertInstanceOf(Carbon::class, $availability->ends_at);
    }

    public function test_doctor_email_uniqueness_is_case_insensitive(): void
    {
        Doctor::factory()->create(['email' => 'john@example.com']);

        $this->expectException(QueryException::class);
        Doctor::factory()->create(['email' => 'JOHN@example.com']);
    }

    public function test_patient_email_uniqueness_is_case_insensitive(): void
    {
        Patient::factory()->create(['email' => 'jane@example.com']);

        $this->expectException(QueryException::class);
        Patient::factory()->create(['email' => 'JANE@example.com']);
    }

    public function test_soft_deleted_doctor_allows_email_reuse(): void
    {
        $doctor = Doctor::factory()->create(['email' => 'john@example.com']);
        $doctor->delete();

        $newDoctor = Doctor::factory()->create(['email' => 'john@example.com']);

        $this->assertNotEquals($doctor->id, $newDoctor->id);
        $this->assertDatabaseCount('doctors', 2);
    }

    public function test_soft_deleted_patient_allows_email_reuse(): void
    {
        $patient = Patient::factory()->create(['email' => 'jane@example.com']);
        $patient->delete();

        $newPatient = Patient::factory()->create(['email' => 'jane@example.com']);

        $this->assertNotEquals($patient->id, $newPatient->id);
        $this->assertDatabaseCount('patients', 2);
    }

    public function test_foreign_key_restrict_on_delete_doctors(): void
    {
        $doctor = Doctor::factory()->create();
        $availability = Availability::factory()->create(['doctor_id' => $doctor->id]);

        $this->expectException(QueryException::class);
        $doctor->forceDelete();
    }

    public function test_foreign_key_restrict_on_delete_patients(): void
    {
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create(['patient_id' => $patient->id]);

        $this->expectException(QueryException::class);
        $patient->forceDelete();
    }

    public function test_foreign_key_restrict_on_delete_doctors_to_appointments(): void
    {
        $doctor = Doctor::factory()->create();
        $patient = Patient::factory()->create();
        $appointment = Appointment::factory()->create([
            'doctor_id' => $doctor->id,
            'patient_id' => $patient->id,
        ]);

        $this->expectException(QueryException::class);
        $doctor->forceDelete();
    }

    public function test_appointment_factory_states_work(): void
    {
        $confirmed = Appointment::factory()->confirmed()->create();
        $this->assertEquals(AppointmentStatus::Confirmed, $confirmed->status);

        $completed = Appointment::factory()->completed()->create();
        $this->assertEquals(AppointmentStatus::Completed, $completed->status);

        $cancelled = Appointment::factory()->cancelled()->create();
        $this->assertEquals(AppointmentStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancellation_reason);
    }
}
