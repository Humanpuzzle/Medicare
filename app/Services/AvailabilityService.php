<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Availability;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class AvailabilityService
{
    /**
     * Create a new availability period for a doctor.
     *
     * @param array{
     *     doctor_id: int,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     slot_duration: int
     * } $data
     */
    public function create(array $data): Availability
    {
        return DB::transaction(function () use ($data): Availability {
            $this->validateAvailabilityData($data);

            $doctor = Doctor::findOrFail($data['doctor_id']);

            $this->ensureNoOverlap($doctor->id, $data['starts_at'], $data['ends_at']);

            return Availability::create([
                'doctor_id' => $data['doctor_id'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'slot_duration' => $data['slot_duration'],
            ]);
        });
    }

    /**
     * Update an existing availability period.
     */
    public function update(Availability $availability, array $data): Availability
    {
        return DB::transaction(function () use ($availability, $data): Availability {
            $startsAt = $data['starts_at'] ?? $availability->starts_at;
            $endsAt = $data['ends_at'] ?? $availability->ends_at;
            $slotDuration = $data['slot_duration'] ?? $availability->slot_duration;

            $this->validateAvailabilityData([
                'doctor_id' => $availability->doctor_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'slot_duration' => $slotDuration,
            ], $availability->id);

            $this->ensureNoOverlap($availability->doctor_id, $startsAt, $endsAt, $availability->id);

            $availability->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'slot_duration' => $slotDuration,
            ]);

            return $availability->fresh();
        });
    }

    /**
     * Delete (soft delete) an availability period.
     */
    public function delete(Availability $availability): void
    {
        $availability->delete();
    }

    /**
     * Validate availability data according to business rules.
     *
     * @param array{
     *     doctor_id: int,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable,
     *     slot_duration: int
     * } $data
     */
    private function validateAvailabilityData(array $data, ?int $excludeAvailabilityId = null): void
    {
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'];
        $slotDuration = $data['slot_duration'];

        // Start must be before end (strict)
        if ($startsAt->gte($endsAt)) {
            throw new \InvalidArgumentException('Availability must start before it ends.');
        }

        // Must be in the future (relative to now in UTC)
        $now = CarbonImmutable::now('UTC');
        if ($startsAt->lte($now)) {
            throw new \InvalidArgumentException('Availability must be in the future.');
        }

        // Minimum 30 minutes duration
        $durationMinutes = $startsAt->diffInMinutes($endsAt);
        if ($durationMinutes < 30) {
            throw new \InvalidArgumentException('Availability must be at least 30 minutes long.');
        }

        // Slot duration must be valid (positive integer, at least 30 per spec)
        if ($slotDuration < 30) {
            throw new \InvalidArgumentException('Slot duration must be a positive integer of at least 30 minutes.');
        }
    }

    /**
     * Ensure no overlapping availability for the same doctor.
     *
     * Uses half-open interval [starts_at, ends_at)
     */
    private function ensureNoOverlap(int $doctorId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $excludeAvailabilityId = null): void
    {
        $query = Availability::query()
            ->where('doctor_id', $doctorId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeAvailabilityId !== null) {
            $query->where('id', '!=', $excludeAvailabilityId);
        }

        $overlapping = $query->exists();

        if ($overlapping) {
            throw new \InvalidArgumentException('Availability overlaps with an existing availability period for this doctor.');
        }
    }

    /**
     * Get all availabilities for a doctor.
     *
     * @return Collection<int, Availability>
     */
    public function getByDoctor(int $doctorId): Collection
    {
        return Availability::query()
            ->where('doctor_id', $doctorId)
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Get availabilities for a doctor within a date range.
     *
     * @return Collection<int, Availability>
     */
    public function getByDoctorAndDateRange(int $doctorId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Availability::query()
            ->where('doctor_id', $doctorId)
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->orderBy('starts_at')
            ->get();
    }
}
