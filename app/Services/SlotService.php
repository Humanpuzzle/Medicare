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
        public readonly CarbonImmutable $endsAt,
        public readonly int $slotDuration,
        public readonly bool $isAvailable = true,
    ) {}

    public function toArray(): array
    {
        return [
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'slot_duration' => $this->slotDuration,
            'is_available' => $this->isAvailable,
        ];
    }
}

final class SlotService
{
    /**
     * Generate slots from an availability period.
     *
     * Slots are generated using the availability's slot_duration.
     * Each slot represents a bookable appointment start time.
     * Uses half-open interval [starts_at, ends_at) - no slot at availability end.
     *
     * @return list<Slot>
     */
    public function generateSlotsFromAvailability(Availability $availability): array
    {
        $slots = [];
        $slotDuration = $availability->slot_duration;
        $startsAt = $this->toImmutable($availability->starts_at);
        $endsAt = $this->toImmutable($availability->ends_at);

        $current = $startsAt;

        while ($current->addMinutes($slotDuration)->lte($endsAt)) {
            $slotEnd = $current->addMinutes($slotDuration);
            $slots[] = new Slot(
                startsAt: $current,
                endsAt: $slotEnd,
                slotDuration: $slotDuration,
                isAvailable: true
            );
            $current = $slotEnd;
        }

        return $slots;
    }

    /**
     * Get available slots for a doctor within a date range.
     *
     * Takes into account existing appointments that would block slots.
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

        // Get active appointments that could block slots
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

        // Generate slots from each availability and check against appointments
        $allSlots = [];

        foreach ($availabilities as $availability) {
            $slots = $this->generateSlotsFromAvailability($availability);

            // Filter slots to only those within the requested date range
            $slots = array_filter($slots, function (Slot $slot) use ($from, $to): bool {
                return $slot->startsAt->gte($from) && $slot->startsAt->lt($to);
            });

            // Mark slots as unavailable if they conflict with existing appointments
            foreach ($slots as &$slot) {
                foreach ($appointments as $appointment) {
                    // Only check appointments for the same doctor
                    if ($appointment->doctor_id === $availability->doctor_id) {
                        if ($this->slotsOverlap($slot, $appointment)) {
                            $slot = new Slot(
                                startsAt: $slot->startsAt,
                                endsAt: $slot->endsAt,
                                slotDuration: $slot->slotDuration,
                                isAvailable: false
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
     * Get available slots for a specific doctor on a specific date.
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
     * Check if a slot overlaps with an appointment.
     *
     * Uses half-open interval semantics [start, end)
     * Slot interval: [slot.startsAt, slot.endsAt)
     * Appointment interval: [appointment.start_time, appointment.end_time)
     */
    private function slotsOverlap(Slot $slot, Appointment $appointment): bool
    {
        $slotStart = $slot->startsAt;
        $slotEnd = $slot->endsAt;
        $appointmentStart = $this->toImmutable($appointment->start_time);
        $appointmentEnd = $this->toImmutable($appointment->end_time);

        // Half-open interval overlap: existing.start < new.end AND existing.end > new.start
        return $appointmentStart->lt($slotEnd) && $appointmentEnd->gt($slotStart);
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
