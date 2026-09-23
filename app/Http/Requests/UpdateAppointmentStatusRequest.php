<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAppointmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.implode(',', array_column(AppointmentStatus::cases(), 'value'))],
            'cancellation_reason' => ['nullable', 'string'],
        ];
    }
}
