<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use App\Models\Patient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class AppointmentService
{
    /**
     * Create a new appointment.
     *
     * @param array{
     *     patient_id: int,
     *     doctor_id: int,
     *     start_time: CarbonImmutable,
     *     end_time: CarbonImmutable,
     * } $data
     */
    public function create(array $data): Appointment
    {
        return DB::transaction(function () use ($data): Appointment {
            $patient = Patient::find($data['patient_id']);
            $doctor = Doctor::find($data['doctor_id']);

            if (! $patient || ! $doctor) {
                throw new \InvalidArgumentException('Invalid patient or doctor ID.');
            }

            $startTime = $data['start_time'];
            $endTime = $data['end_time'];

            $this->validateTimeInterval($startTime, $endTime);
            $this->ensureFutureAppointment($startTime);
            $this->ensureMinimumDuration($startTime, $endTime);
            $this->ensureFifteenMinuteGrid($startTime);

            $availability = $this->findContainingAvailability($doctor->id, $startTime, $endTime);
            $this->ensureDurationMultiple($startTime, $endTime, $availability->slot_duration);

            $this->ensureNoDoctorConflict($doctor->id, $startTime, $endTime);
            $this->ensureNoPatientConflict($patient->id, $startTime, $endTime);

            return Appointment::create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => AppointmentStatus::Pending,
                'cancellation_reason' => null,
            ]);
        });
    }

    /**
     * Update appointment status with validation.
     */
    public function updateStatus(Appointment $appointment, AppointmentStatus $newStatus, ?string $cancellationReason = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $newStatus, $cancellationReason): Appointment {
            // Status is cast to AppointmentStatus enum by the model
            $this->validateTransition($appointment->status, $newStatus);

            if ($newStatus === AppointmentStatus::Cancelled) {
                $this->validateCancellation($appointment);
            }

            $appointment->status = $newStatus;
            $appointment->cancellation_reason = $newStatus === AppointmentStatus::Cancelled
                ? $cancellationReason
                : null;

            $appointment->save();

            return $appointment->fresh();
        });
    }

    /**
     * Cancel an appointment (shortcut for confirmed/pending -> cancelled).
     */
    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        return $this->updateStatus($appointment, AppointmentStatus::Cancelled, $reason);
    }

    /**
     * Validate time interval: start < end.
     */
    private function validateTimeInterval(CarbonImmutable $startTime, CarbonImmutable $endTime): void
    {
        if ($startTime->gte($endTime)) {
            throw new \InvalidArgumentException('Appointment start time must be before end time.');
        }
    }

    /**
     * Ensure appointment is in the future (strict).
     */
    private function ensureFutureAppointment(CarbonImmutable $startTime): void
    {
        $now = CarbonImmutable::now('UTC');
        if ($startTime->lte($now)) {
            throw new \InvalidArgumentException('The appointment must be scheduled in the future.');
        }
    }

    /**
     * Ensure minimum 30-minute duration.
     */
    private function ensureMinimumDuration(CarbonImmutable $startTime, CarbonImmutable $endTime): void
    {
        $durationMinutes = $startTime->diffInMinutes($endTime);
        if ($durationMinutes < 30) {
            throw new \InvalidArgumentException('The appointment duration must be at least 30 minutes.');
        }
    }

    /**
     * Ensure start time is on 15-minute grid.
     */
    private function ensureFifteenMinuteGrid(CarbonImmutable $startTime): void
    {
        $minute = $startTime->minute;
        $second = $startTime->second;
        $microsecond = (int) $startTime->format('u');

        if ($minute % 15 !== 0 || $second !== 0 || $microsecond !== 0) {
            throw new \InvalidArgumentException('The appointment start time must be on a 15-minute grid.');
        }
    }

    /**
     * Find the single availability that contains the appointment.
     */
    private function findContainingAvailability(int $doctorId, CarbonImmutable $startTime, CarbonImmutable $endTime): Availability
    {
        $availability = Availability::query()
            ->where('doctor_id', $doctorId)
            ->where('starts_at', '<=', $startTime)
            ->where('ends_at', '>=', $endTime)
            ->first();

        if (! $availability) {
            throw new \InvalidArgumentException('The appointment must be completely contained within a single availability period.');
        }

        return $availability;
    }

    /**
     * Ensure appointment duration is a multiple of availability's slot_duration.
     */
    private function ensureDurationMultiple(CarbonImmutable $startTime, CarbonImmutable $endTime, int $slotDuration): void
    {
        $durationMinutes = $startTime->diffInMinutes($endTime);

        if ($durationMinutes % $slotDuration !== 0) {
            throw new \InvalidArgumentException('The appointment duration must be a multiple of the availability slot duration.');
        }
    }

    /**
     * Check for doctor conflicts (pending/confirmed appointments).
     */
    private function ensureNoDoctorConflict(int $doctorId, CarbonImmutable $startTime, CarbonImmutable $endTime): void
    {
        $conflict = Appointment::query()
            ->where('doctor_id', $doctorId)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            throw new \InvalidArgumentException('The doctor already has an appointment during the requested time.');
        }
    }

    /**
     * Check for patient conflicts (pending/confirmed appointments).
     */
    private function ensureNoPatientConflict(int $patientId, CarbonImmutable $startTime, CarbonImmutable $endTime): void
    {
        $conflict = Appointment::query()
            ->where('patient_id', $patientId)
            ->whereIn('status', [AppointmentStatus::Pending, AppointmentStatus::Confirmed])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();

        if ($conflict) {
            throw new \InvalidArgumentException('The patient already has an appointment during the requested time.');
        }
    }

    /**
     * Validate allowed status transitions.
     */
    private function validateTransition(AppointmentStatus $from, AppointmentStatus $to): void
    {
        if ($from === $to) {
            return; // No-op, allowed
        }

        $allowed = [
            'pending' => [
                AppointmentStatus::Confirmed,
                AppointmentStatus::Cancelled,
            ],
            'confirmed' => [
                AppointmentStatus::Completed,
                AppointmentStatus::Cancelled,
            ],
            // Completed and Cancelled are terminal - no allowed transitions
        ];

        $fromValue = $from->value;

        if (! isset($allowed[$fromValue]) || ! in_array($to, $allowed[$fromValue], true)) {
            throw new \InvalidArgumentException("The appointment cannot transition from {$from->value} to {$to->value}.");
        }
    }

    /**
     * Validate cancellation-specific rules.
     */
    private function validateCancellation(Appointment $appointment): void
    {
        // Status is cast to AppointmentStatus enum by the model
        if ($appointment->status === AppointmentStatus::Confirmed) {
            $now = CarbonImmutable::now('UTC');
            $hoursUntilStart = $now->diffInHours($appointment->start_time, false);

            // Check if strictly less than 24 hours
            $exactHours = $now->diffInHours($appointment->start_time);
            $diffMinutes = $now->diffInMinutes($appointment->start_time);

            // If 24 hours or more in minutes (>= 1440 minutes = 24 hours)
            if ($diffMinutes < 1440) {
                throw new \InvalidArgumentException('A confirmed appointment can only be cancelled at least 24 hours before its start time.');
            }
        }
        // pending -> cancelled does not require 24-hour check
    }
}
