<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property int|null $doctor_id
 * @property Carbon $starts_at
 * @property bool $is_available
 */
final class AvailableSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'doctor_id' => $this->doctor_id,
            'start_time' => $this->starts_at->toIso8601String(),
            'is_available' => $this->is_available,
        ];
    }
}
