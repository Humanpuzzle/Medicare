<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DoctorAppointmentController extends Controller
{
    public function index(Request $request, Doctor $doctor): AnonymousResourceCollection
    {
        $perPage = $request->integer('per_page', 25);
        $page = max($request->integer('page', 1), 1);

        if ($perPage < 1 || $perPage > 100) {
            abort(422, 'The per_page parameter must be between 1 and 100.');
        }

        $query = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->with('patient')
            ->orderBy('start_time')
            ->orderBy('id');

        if ($request->has('status')) {
            $query->where('status', $request->string('status'));
        }

        $appointments = $query->paginate($perPage, ['*'], 'page', $page);

        return AppointmentResource::collection($appointments);
    }
}
