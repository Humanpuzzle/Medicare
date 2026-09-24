<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Slot DTO representing a bookable appointment start time.
 */
final class Slot
{
    public function __construct(
        public readonly CarbonImmutable $startsAt,
        public readonly bool $isAvailable = true,
    ) {}

    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601String(),
            'is_available' => $this->isAvailable,
        ];
    }
}

final class SlotService
{
    /**
     * Generate valid bookable start times from an availability period.
     *
     * Generates 15-minute candidate start times within the availability period.
     * Only includes start times where at least 30 minutes remain before availability end.
     * Uses half-open interval [starts_at, ends_at) - no start time at availability end.
     *
     * @return list<Slot>
     */
    public function generateStartTimesFromAvailability(Availability $availability): array
    {
        $startsAt = $this->toImmutable($availability->starts_at);
        $endsAt = $this->toImmutable($availability->ends_at);

        $startTimes = [];
        $current = $startsAt;

        // Generate 15-minute candidate start times
        // Only include if at least 30 minutes remain before availability end
        while ($current->addMinutes(30)->lte($endsAt)) {
            $startTimes[] = new Slot(
                startsAt: $current,
                isAvailable: true,
            );
            $current = $current->addMinutes(15);
        }

        return $startTimes;
    }

    /**
     * Get available start times for a doctor within a date range.
     *
     * Takes into account existing appointments that would block start times.
     *
     * @param array{
     *     doctor_id?: int,
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     * } $params
     * @return list<Slot>
     */
    public function getAvailableSlots(array $params): array
    {
        $doctorId = $params['doctor_id'] ?? null;
        $from = $params['from'];
        $to = $params['to'];

        // Get availabilities that intersect with the date range
        $query = Availability::query()
            ->where('starts_at', '<', $to)
            ->where('ends_at', '>', $from)
            ->with('doctor')
            ->orderBy('starts_at');

        if ($doctorId !== null) {
            $query->where('doctor_id', $doctorId);
        }

        $availabilities = $query->get();

        // Get active appointments that could block start times
        // Active appointments: pending, confirmed (not completed, cancelled, or soft deleted)
        $appointmentQuery = Appointment::query()
            ->where('start_time', '<', $to)
            ->where('end_time', '>', $from)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('doctor');

        if ($doctorId !== null) {
            $appointmentQuery->where('doctor_id', $doctorId);
        }

        $appointments = $appointmentQuery->get();

        // Generate start times from each availability and check against appointments
        $allSlots = [];

        foreach ($availabilities as $availability) {
            $slots = $this->generateStartTimesFromAvailability($availability);

            // Filter start times to only those within the requested date range
            // Also ensure at least 30 minutes remain before the 'to' boundary
            $slots = array_filter($slots, function (Slot $slot) use ($from, $to): bool {
                return $slot->startsAt->gte($from)
                    && $slot->startsAt->lt($to)
                    && $slot->startsAt->addMinutes(30)->lte($to);
            });

            // Mark start times as unavailable if they would conflict with existing appointments
            // A start time is blocked if a minimum 30-minute appointment starting at that time
            // would overlap with an existing appointment
            foreach ($slots as &$slot) {
                foreach ($appointments as $appointment) {
                    // Only check appointments for the same doctor
                    if ($appointment->doctor_id === $availability->doctor_id) {
                        if ($this->startTimeConflictsWithAppointment($slot->startsAt, $appointment)) {
                            $slot = new Slot(
                                startsAt: $slot->startsAt,
                                isAvailable: false,
                            );
                            break;
                        }
                    }
                }
            }

            $allSlots = array_merge($allSlots, array_values($slots));
        }

        return $allSlots;
    }

    /**
     * Get available start times for a specific doctor on a specific date.
     *
     * @return list<Slot>
     */
    public function getAvailableSlotsForDoctorOnDate(int $doctorId, CarbonImmutable $date): array
    {
        $startOfDay = $date->startOfDay();
        $endOfDay = $date->endOfDay();

        return $this->getAvailableSlots([
            'doctor_id' => $doctorId,
            'from' => $startOfDay,
            'to' => $endOfDay,
        ]);
    }

    /**
     * Check if a start time would conflict with an appointment.
     *
     * A start time conflicts if a minimum 30-minute appointment starting at that time
     * would overlap with the existing appointment.
     *
     * Uses half-open interval semantics [start, end)
     * Proposed appointment: [startTime, startTime + 30 minutes)
     * Existing appointment: [appointment.start_time, appointment.end_time)
     */
    private function startTimeConflictsWithAppointment(CarbonImmutable $startTime, Appointment $appointment): bool
    {
        $proposedStart = $startTime;
        $proposedEnd = $startTime->addMinutes(30); // Minimum 30-minute appointment
        $appointmentStart = $this->toImmutable($appointment->start_time);
        $appointmentEnd = $this->toImmutable($appointment->end_time);

        // Half-open interval overlap: proposed.start < existing.end AND proposed.end > existing.start
        return $proposedStart->lt($appointmentEnd) && $proposedEnd->gt($appointmentStart);
    }

    /**
     * Convert Carbon (mutable), CarbonInterface, or string to CarbonImmutable.
     */
    private function toImmutable(CarbonInterface|string $carbon): CarbonImmutable
    {
        if (is_string($carbon)) {
            return CarbonImmutable::parse($carbon);
        }

        return CarbonImmutable::instance($carbon);
    }
}
