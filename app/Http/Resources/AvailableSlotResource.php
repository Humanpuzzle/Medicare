<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int|null $doctor_id
 * @property int|null $availability_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_available
 */
final class AvailableSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->doctor_id,
            'availability_id' => $this->availability_id,
            'start_time' => $this->starts_at->toIso8601String(),
            'end_time' => $this->ends_at->toIso8601String(),
        ];
    }
}
